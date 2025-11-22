<?php
// toggle_manual_freeze.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../config/audit_logger.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']);
    exit();
}

$faculty_id = $_SESSION['user_id'];
$class_code = $_POST['class_code'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$freeze = $_POST['freeze'] ?? '';

if ($class_code === '' || $student_id === '' || ($freeze !== 'yes' && $freeze !== 'no')) {
    echo json_encode(['success'=>false,'message'=>'Missing parameters']);
    exit();
}

try {
    // Verify ownership
    $own = $conn->prepare("SELECT class_id FROM class WHERE class_code=? AND faculty_id=? LIMIT 1");
    $own->bind_param('si',$class_code,$faculty_id);
    $own->execute();
    if ($own->get_result()->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'Access denied']); exit(); }
    $own->close();

    // Ensure column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM grade_term LIKE 'status_manually_set'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $alter = "ALTER TABLE grade_term ADD COLUMN status_manually_set ENUM('yes','no') DEFAULT 'no'";
        if (!$conn->query($alter)) { echo json_encode(['success'=>false,'message'=>'Failed to add status_manually_set column']); exit(); }
    }

    // Check existing row
    $rowStmt = $conn->prepare("SELECT id FROM grade_term WHERE student_id=? AND class_code=? LIMIT 1");
    $rowStmt->bind_param('ss',$student_id,$class_code);
    $rowStmt->execute();
    $rowRes = $rowStmt->get_result();
    $exists = $rowRes->num_rows > 0;
    $rowStmt->close();

    if (!$exists) {
        // Create empty row to allow freeze (percentages will compute later)
        $ins = $conn->prepare("INSERT INTO grade_term (student_id,class_code,term_grade,midterm_percentage,finals_percentage,term_percentage,grade_status,lacking_requirements,is_encrypted,status_manually_set) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $blank = '';
        $zero = '0.00';
        $status = 'INC';
        $enc = 0; $man=$freeze;
        $ins->bind_param('ssssssssiss',$student_id,$class_code,$blank,$zero,$zero,$zero,$status,$blank,$enc,$man);
        // Correction: binding types mismatch original plan; simpler: use direct query
        $ins->close();
        $conn->query("INSERT INTO grade_term (student_id,class_code,grade_status,status_manually_set) VALUES ('".$conn->real_escape_string($student_id)."','".$conn->real_escape_string($class_code)."','INC','".$conn->real_escape_string($freeze)."')");
    } else {
        $up = $conn->prepare("UPDATE grade_term SET status_manually_set=? WHERE student_id=? AND class_code=?");
        $up->bind_param('sss',$freeze,$student_id,$class_code);
        if (!$up->execute()) { echo json_encode(['success'=>false,'message'=>'Update failed']); exit(); }
        $up->close();
    }

    audit_log($faculty_id, 'faculty', 'toggle_manual_freeze', $class_code, ['student_id'=>$student_id,'freeze'=>$freeze], 1, 0);
    echo json_encode(['success'=>true,'student_id'=>$student_id,'frozen'=>$freeze==='yes']);
} catch (Exception $e) {
    error_log('toggle_manual_freeze error: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>