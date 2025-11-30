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

file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Script started\n", FILE_APPEND);

error_log("ENCRYPT_DECRYPT_GRADES: Script started, action=" . ($_POST['action'] ?? 'none') . ", class_code=" . ($_POST['class_code'] ?? 'none'));

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Unauthorized\n", FILE_APPEND);
    error_log("ENCRYPT_DECRYPT_GRADES: Unauthorized access - user_id=" . ($_SESSION['user_id'] ?? 'none') . ", role=" . ($_SESSION['role'] ?? 'none'));
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("ENCRYPT_DECRYPT_GRADES: Invalid CSRF token - received=" . ($_POST['csrf_token'] ?? 'none') . ", expected=" . ($_SESSION['csrf_token'] ?? 'none'));
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Refresh and retry.']);
    exit;
}

$action = $_POST['action'] ?? '';
$class_code = $_POST['class_code'] ?? '';
$faculty_id = $_SESSION['user_id'];
if (!$class_code) { 
    error_log("ENCRYPT_DECRYPT_GRADES: No class_code provided");
    echo json_encode(['success'=>false,'error'=>'Class code is required']); 
    exit; 
}

try {
    // Ownership check
    $verify = $conn->prepare("SELECT class_id FROM class WHERE class_code = ? AND faculty_id = ? LIMIT 1");
    $verify->bind_param('si', $class_code, $faculty_id);
    $verify->execute();
    if ($verify->get_result()->num_rows === 0) { 
        file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Access denied\n", FILE_APPEND);
        error_log("ENCRYPT_DECRYPT_GRADES: Access denied for class $class_code, faculty $faculty_id");
        throw new Exception('You do not have access to this class'); 
    }
    $verify->close();

    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Passed ownership check\n", FILE_APPEND);
    Encryption::init();
    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Encryption initialized\n", FILE_APPEND);
    error_log("ENCRYPT_DECRYPT_GRADES: Encryption initialized successfully");

    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - About to check action: $action\n", FILE_APPEND);
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
        error_log("DECRYPT_ALL: Starting decryption for class $class_code");
        $conn->begin_transaction();
        $select = $conn->prepare("SELECT id, term_grade, midterm_percentage, finals_percentage, term_percentage FROM grade_term WHERE class_code = ? AND is_encrypted = 1");
        $select->bind_param('s', $class_code);
        $select->execute();
        $res = $select->get_result();
        $count = 0; $errors = [];
        error_log("DECRYPT_ALL: Found " . $res->num_rows . " encrypted records");
        
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
                error_log("DECRYPT_ALL: Decrypted record id=" . $row['id']);
            } catch (Exception $ie) { $errors[] = 'ID '.$row['id'].': '.$ie->getMessage(); error_log("DECRYPT_ALL ERROR: " . $ie->getMessage()); }
        }
        $select->close();
        error_log("DECRYPT_ALL: Successfully decrypted $count records, " . count($errors) . " errors");
        
        $students = $conn->prepare("SELECT DISTINCT student_id FROM class_enrollments WHERE class_code = ?");
        $students->bind_param('s',$class_code); $students->execute(); $sr = $students->get_result();
        while ($s = $sr->fetch_assoc()) {
            $vis = $conn->prepare("INSERT INTO grade_visibility_status (student_id, class_code, grade_visibility, changed_by) VALUES (?, ?, 'visible', ?) ON DUPLICATE KEY UPDATE grade_visibility='visible', changed_by=?, visibility_changed_at=NOW()");
            $vis->bind_param('ssii',$s['student_id'],$class_code,$faculty_id,$faculty_id); $vis->execute(); $vis->close();
        }
        $students->close();
        $conn->commit();
        error_log("DECRYPT_ALL: Transaction committed");
        echo json_encode(['success'=>true,'message'=>"Decrypted $count grade row(s).",'count'=>$count,'errors'=>$errors]);
        exit;
    } elseif ($action === 'decrypt_midterm') {
        error_log("DECRYPT_MIDTERM: Starting midterm decryption for class $class_code");
        $conn->begin_transaction();
        $select = $conn->prepare("SELECT id, midterm_percentage FROM grade_term WHERE class_code = ?");
        $select->bind_param('s', $class_code);
        $select->execute();
        $res = $select->get_result();
        $count = 0; $errors = [];
        while ($row = $res->fetch_assoc()) {
            try {
                $midterm_val = $row['midterm_percentage'];
                $needs_update = false;
                
                // Check if midterm_percentage is encrypted (non-numeric)
                if ($midterm_val !== null && $midterm_val !== '' && !is_numeric($midterm_val)) {
                    $dec_midterm = Encryption::decrypt($midterm_val);
                    $needs_update = true;
                } else {
                    $dec_midterm = $midterm_val;
                }
                
                if ($needs_update) {
                    $upd = $conn->prepare("UPDATE grade_term SET midterm_percentage=?, is_encrypted=0 WHERE id=?");
                    $upd->bind_param('si', $dec_midterm, $row['id']);
                    if (!$upd->execute()) { throw new Exception($upd->error); }
                    $upd->close();
                    $count++;
                }
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
        echo json_encode(['success'=>true,'message'=>"Midterm grades decrypted for $count student(s).",'count'=>$count,'errors'=>$errors]);
        exit;
    } elseif ($action === 'decrypt_finals') {
        file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Entered decrypt_finals block\n", FILE_APPEND);
        error_log("DECRYPT_FINALS: Starting finals decryption for class $class_code");
        try {
            file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - About to begin transaction\n", FILE_APPEND);
            $conn->begin_transaction();
            file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Transaction started\n", FILE_APPEND);
            error_log("DECRYPT_FINALS: Transaction started");
        $select = $conn->prepare("SELECT id, midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE class_code = ?");
        file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Query prepared\n", FILE_APPEND);
        $select->bind_param('s', $class_code);
        file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Query bound\n", FILE_APPEND);
        $select->execute();
        file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Query executed\n", FILE_APPEND);
        $res = $select->get_result();
        file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Got result, rows: " . $res->num_rows . "\n", FILE_APPEND);
        error_log("DECRYPT_FINALS: Query executed, found " . $res->num_rows . " rows");
        $count = 0; $errors = [];
        file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Starting while loop\n", FILE_APPEND);
        while ($row = $res->fetch_assoc()) {
            file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Processing row id: " . $row['id'] . "\n", FILE_APPEND);
            try {
                $dec = [];
                $needs_update = false;
                
                // Check and decrypt midterm_percentage if it's encrypted (non-numeric)
                $midterm_val = $row['midterm_percentage'];
                file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Processing midterm: " . substr($midterm_val, 0, 20) . "...\n", FILE_APPEND);
                if ($midterm_val !== null && $midterm_val !== '' && !is_numeric($midterm_val)) {
                    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Attempting to decrypt midterm\n", FILE_APPEND);
                    $dec['midterm_percentage'] = Encryption::decrypt($midterm_val);
                    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Midterm decrypted successfully\n", FILE_APPEND);
                    $needs_update = true;
                } else {
                    $dec['midterm_percentage'] = $midterm_val;
                }
                
                // Check and decrypt finals_percentage if it's encrypted (non-numeric)
                $finals_val = $row['finals_percentage'];
                file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Processing finals: " . substr($finals_val, 0, 20) . "...\n", FILE_APPEND);
                if ($finals_val !== null && $finals_val !== '' && !is_numeric($finals_val)) {
                    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Attempting to decrypt finals\n", FILE_APPEND);
                    $dec['finals_percentage'] = Encryption::decrypt($finals_val);
                    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Finals decrypted successfully\n", FILE_APPEND);
                    $needs_update = true;
                } else {
                    $dec['finals_percentage'] = $finals_val;
                }
                
                // Check and decrypt term_percentage if it's encrypted (non-numeric)
                $term_pct_val = $row['term_percentage'];
                file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Processing term_pct: " . substr($term_pct_val, 0, 20) . "...\n", FILE_APPEND);
                if ($term_pct_val !== null && $term_pct_val !== '' && !is_numeric($term_pct_val)) {
                    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Attempting to decrypt term_pct\n", FILE_APPEND);
                    $dec['term_percentage'] = Encryption::decrypt($term_pct_val);
                    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Term_pct decrypted successfully\n", FILE_APPEND);
                    $needs_update = true;
                } else {
                    $dec['term_percentage'] = $term_pct_val;
                }
                
                // Check and decrypt term_grade if it's encrypted (non-numeric)
                $term_grade_val = $row['term_grade'];
                file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Processing term_grade: " . substr($term_grade_val, 0, 20) . "...\n", FILE_APPEND);
                if ($term_grade_val !== null && $term_grade_val !== '' && !is_numeric($term_grade_val)) {
                    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Attempting to decrypt term_grade\n", FILE_APPEND);
                    $dec['term_grade'] = Encryption::decrypt($term_grade_val);
                    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Term_grade decrypted successfully\n", FILE_APPEND);
                    $needs_update = true;
                } else {
                    $dec['term_grade'] = $term_grade_val;
                }
                
                file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Decryption complete, needs_update: " . ($needs_update ? 'true' : 'false') . "\n", FILE_APPEND);
                if ($needs_update) {
                    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Attempting direct SQL update\n", FILE_APPEND);
                    
                    // Escape the values for direct SQL
                    $midterm_esc = $conn->real_escape_string($dec['midterm_percentage']);
                    $finals_esc = $conn->real_escape_string($dec['finals_percentage']);
                    $term_pct_esc = $conn->real_escape_string($dec['term_percentage']);
                    $term_grade_esc = $conn->real_escape_string($dec['term_grade']);
                    
                    $sql = "UPDATE grade_term SET midterm_percentage='$midterm_esc', finals_percentage='$finals_esc', term_percentage='$term_pct_esc', term_grade='$term_grade_esc', is_encrypted=0 WHERE id=" . $row['id'];
                    file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - SQL: " . $sql . "\n", FILE_APPEND);
                    
                    if ($conn->query($sql) === TRUE) {
                        file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Direct SQL update successful\n", FILE_APPEND);
                        $count++;
                    } else {
                        file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Direct SQL update failed: " . $conn->error . "\n", FILE_APPEND);
                        throw new Exception("Update failed: " . $conn->error);
                    }
                    
                    // Skip the prepared statement code
                    continue;
                }
            } catch (Exception $ie) { 
                file_put_contents('c:\xampp\htdocs\automation_system\logs\debug_encrypt.log', date('Y-m-d H:i:s') . " - Exception caught for row " . $row['id'] . ": " . $ie->getMessage() . "\n", FILE_APPEND);
                $errors[] = 'ID '.$row['id'].': '.$ie->getMessage(); 
            }
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
        error_log("DECRYPT_FINALS: Transaction committed, processed $count records");
        echo json_encode(['success'=>true,'message'=>"Finals grades (and midterm grades if available) decrypted for $count student(s).",'count'=>$count,'errors'=>$errors]);
        exit;
        } catch (Exception $e) {
            if ($conn->errno === 0) { // attempt rollback only if transaction started; silent if not.
                try { $conn->rollback(); } catch (Exception $ignored) {}
            }
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
            exit;
        }
    } elseif ($action === 'check_status') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM grade_term WHERE class_code = ? AND is_encrypted = 1");
        $stmt->bind_param('s',$class_code); $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $stmt->close();
        $is = ($r && $r['c'] > 0);
        echo json_encode(['success'=>true,'is_encrypted'=>$is,'message'=>$is ? 'Grades are encrypted' : 'Grades are decrypted']);
        exit;
    } else { 
        error_log("ENCRYPT_DECRYPT_GRADES: Invalid action: $action");
        throw new Exception('Invalid action'); 
    }
} catch (Exception $e) {
    if ($conn->errno === 0) { // attempt rollback only if transaction started; silent if not.
        try { $conn->rollback(); } catch (Exception $ignored) {}
    }
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}
?>

                
