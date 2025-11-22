<?php
// recompute_class_term_grades.php
// Recompute all term grades for a class using helper (lowercase status logic)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../config/encryption.php';
require_once '../../config/helpers.php';
require_once '../../config/middleware.php';
require_once '../../config/error_handler.php';
require_once '../../config/decryption_access.php';
require_once '../../includes/GradesModel.php';
require_once '../../includes/grade_recompute_helper.php';
require_once '../../config/audit_logger.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$faculty_id = $_SESSION['user_id'];
$class_code = $_POST['class_code'] ?? '';
if ($class_code === '') { echo json_encode(['success' => false, 'message' => 'Class code required']); exit(); }

$startTime = microtime(true);

try {
    // Ownership check
    $own = $conn->prepare("SELECT class_id FROM class WHERE class_code=? AND faculty_id=? LIMIT 1");
    $own->bind_param('si', $class_code, $faculty_id);
    $own->execute();
    if ($own->get_result()->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'Class not found or access denied']); exit(); }
    $own->close();

    // Enrolled students
    $stStmt = $conn->prepare("SELECT s.student_id FROM class_enrollments ce JOIN student s ON ce.student_id=s.student_id WHERE ce.class_code=? AND ce.status='enrolled' ORDER BY s.student_id");
    $stStmt->bind_param('s', $class_code);
    $stStmt->execute();
    $studentsRes = $stStmt->get_result();
    $students = [];
    while ($r = $studentsRes->fetch_assoc()) { $students[] = $r['student_id']; }
    $stStmt->close();

    $results = [];
    foreach ($students as $sid) {
        $res = recompute_term_grade($conn, $sid, $class_code);
        // Round output values for response (DB already saved inside helper)
        $results[] = [
            'student_id' => $sid,
            'midterm_percentage' => number_format($res['midterm_percentage'],2,'.',''),
            'finals_percentage' => number_format($res['finals_percentage'],2,'.',''),
            'term_percentage' => number_format($res['term_percentage'],2,'.',''),
            'grade_status' => $res['grade_status'],
            'term_grade' => $res['term_grade']
        ];
    }
    $durationMs = round((microtime(true) - $startTime)*1000,2);

    audit_log($faculty_id, 'faculty', 'recompute_class_term_grades', $class_code, ['student_count'=>count($students)], count($results), $durationMs);

    echo json_encode([
        'success' => true,
        'class_code' => $class_code,
        'rows' => $results,
        'duration_ms' => $durationMs
    ]);
    exit();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: '.$e->getMessage()]);
    exit();
}
