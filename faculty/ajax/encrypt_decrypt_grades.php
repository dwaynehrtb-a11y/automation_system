<?php
/**
 * Faculty AJAX - Encrypt/Decrypt Grades (Transactional, Flag-Based)
 * Actions:
 *  - encrypt_all: Encrypt grade fields for class, set is_encrypted=1, mark visibility hidden
 *  - decrypt_all: Decrypt grade fields for class, set is_encrypted=0, mark visibility visible
 *  - check_status: Return flag status based on is_encrypted rows
 */
define('SYSTEM_ACCESS', true);
require_once '../../config/session.php';
require_once '../../config/db.php';
require_once '../../config/encryption.php';

header('Content-Type: application/json');
startSecureSession();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Refresh and retry.']);
    exit;
}

$action = $_POST['action'] ?? '';
$class_code = $_POST['class_code'] ?? '';
$faculty_id = $_SESSION['user_id'];
if (!$class_code) { echo json_encode(['success'=>false,'error'=>'Class code is required']); exit; }

try {
    // Ownership check
    $verify = $conn->prepare("SELECT class_id FROM class WHERE class_code = ? AND faculty_id = ? LIMIT 1");
    $verify->bind_param('si', $class_code, $faculty_id);
    $verify->execute();
    if ($verify->get_result()->num_rows === 0) { throw new Exception('You do not have access to this class'); }
    $verify->close();

    Encryption::init();
    $fields = ['term_grade','midterm_percentage','finals_percentage','term_percentage']; // core grade fields for bulk ops

    if ($action === 'encrypt_all') {
        $conn->begin_transaction();
        $select = $conn->prepare("SELECT id, term_grade, midterm_percentage, finals_percentage, term_percentage FROM grade_term WHERE class_code = ? AND (is_encrypted = 0 OR is_encrypted IS NULL)");
        $select->bind_param('s', $class_code);
        $select->execute();
        $res = $select->get_result();
        $count = 0; $errors = [];
        while ($row = $res->fetch_assoc()) {
            try {
                $enc = [];
                foreach ($fields as $f) {
                    $val = $row[$f];
                    if ($val !== null && $val !== '' && is_numeric(trim($val))) { $enc[$f] = Encryption::encrypt($val); } else { $enc[$f] = $val; }
                }
                $upd = $conn->prepare("UPDATE grade_term SET term_grade=?, midterm_percentage=?, finals_percentage=?, term_percentage=?, is_encrypted=1 WHERE id=?");
                $upd->bind_param('ssssi', $enc['term_grade'], $enc['midterm_percentage'], $enc['finals_percentage'], $enc['term_percentage'], $row['id']);
                if (!$upd->execute()) { throw new Exception($upd->error); }
                $upd->close();
                $count++;
            } catch (Exception $ie) { $errors[] = 'ID '.$row['id'].': '.$ie->getMessage(); }
        }
        $select->close();
        // visibility rows
        $students = $conn->prepare("SELECT DISTINCT student_id FROM class_enrollments WHERE class_code = ?");
        $students->bind_param('s',$class_code); $students->execute(); $sr = $students->get_result();
        while ($s = $sr->fetch_assoc()) {
            $vis = $conn->prepare("INSERT INTO grade_visibility_status (student_id, class_code, grade_visibility, changed_by) VALUES (?, ?, 'hidden', ?) ON DUPLICATE KEY UPDATE grade_visibility='hidden', changed_by=?, visibility_changed_at=NOW()");
            $vis->bind_param('ssii',$s['student_id'],$class_code,$faculty_id,$faculty_id); $vis->execute(); $vis->close();
        }
        $students->close();
        $conn->commit();
        echo json_encode(['success'=>true,'message'=>"Encrypted $count grade row(s).",'count'=>$count,'errors'=>$errors]);
        exit;
    } elseif ($action === 'decrypt_all') {
        $conn->begin_transaction();
        $select = $conn->prepare("SELECT id, term_grade, midterm_percentage, finals_percentage, term_percentage FROM grade_term WHERE class_code = ? AND is_encrypted = 1");
        $select->bind_param('s', $class_code);
        $select->execute();
        $res = $select->get_result();
        $count = 0; $errors = [];
        while ($row = $res->fetch_assoc()) {
            try {
                $dec = [];
                foreach ($fields as $f) {
                    $val = $row[$f];
                    if ($val !== null && $val !== '') { 
                        try { $dec[$f] = Encryption::decrypt($val); } catch (Exception $de) { $dec[$f] = $val; }
                    } else { $dec[$f] = $val; }
                }
                $upd = $conn->prepare("UPDATE grade_term SET term_grade=?, midterm_percentage=?, finals_percentage=?, term_percentage=?, is_encrypted=0 WHERE id=?");
                $upd->bind_param('ssssi', $dec['term_grade'], $dec['midterm_percentage'], $dec['finals_percentage'], $dec['term_percentage'], $row['id']);
                if (!$upd->execute()) { throw new Exception($upd->error); }
                $upd->close();
                $count++;
            } catch (Exception $ie) { $errors[] = 'ID '.$row['id'].': '.$ie->getMessage(); }
        }
        $select->close();
        $students = $conn->prepare("SELECT DISTINCT student_id FROM class_enrollments WHERE class_code = ?");
        $students->bind_param('s',$class_code); $students->execute(); $sr = $students->get_result();
        while ($s = $sr->fetch_assoc()) {
            $vis = $conn->prepare("INSERT INTO grade_visibility_status (student_id, class_code, grade_visibility, changed_by) VALUES (?, ?, 'visible', ?) ON DUPLICATE KEY UPDATE grade_visibility='visible', changed_by=?, visibility_changed_at=NOW()");
            $vis->bind_param('ssii',$s['student_id'],$class_code,$faculty_id,$faculty_id); $vis->execute(); $vis->close();
        }
        $students->close();
        $conn->commit();
        echo json_encode(['success'=>true,'message'=>"Decrypted $count grade row(s).",'count'=>$count,'errors'=>$errors]);
        exit;
    } elseif ($action === 'check_status') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM grade_term WHERE class_code = ? AND is_encrypted = 1");
        $stmt->bind_param('s',$class_code); $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $stmt->close();
        $is = ($r && $r['c'] > 0);
        echo json_encode(['success'=>true,'is_encrypted'=>$is,'message'=>$is ? 'Grades are encrypted' : 'Grades are decrypted']);
        exit;
    } else { throw new Exception('Invalid action'); }
} catch (Exception $e) {
    if ($conn->errno === 0) { // attempt rollback only if transaction started; silent if not.
        try { $conn->rollback(); } catch (Exception $ignored) {}
    }
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}
?>

                
