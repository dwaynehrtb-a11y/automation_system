<?php
// Start session first
session_start();

// Prevent HTML output
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';

// Clean output buffer
ob_clean();

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$faculty_id = $_SESSION['user_id'];
$class_code = $_GET['class_code'] ?? '';

if (empty($class_code)) {
    echo json_encode(['success' => false, 'message' => 'Class code required']);
    exit;
}
error_log("DEBUG: Looking for class_code=$class_code with faculty_id=$faculty_id");

try {
    // Get class information with course details and faculty name
    $stmt = $conn->prepare("
    SELECT 
        c.class_code,
        c.section,
        c.academic_year,
        c.term,
        c.course_code,
        c.day,
        c.time,
        c.room,
        c.midterm_weight,
        c.finals_weight,
        s.course_title,
        s.course_desc,
        s.units,
        u.name as faculty_name
    FROM class c
    LEFT JOIN subjects s ON c.course_code = s.course_code
    LEFT JOIN users u ON c.faculty_id = u.id
    WHERE c.class_code = ? AND c.faculty_id = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]);
    exit;
}
    $stmt->bind_param("si", $class_code, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Class not found or access denied',
        'debug' => [
            'class_code' => $class_code,
            'faculty_id' => $faculty_id,
            'query_returned_rows' => $result->num_rows
        ]
    ]);
    $stmt->close();
    exit;
}
    
    $class_info = $result->fetch_assoc();
    $stmt->close();
    
    // Get student count
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT student_id) as student_count
        FROM class_enrollments
        WHERE class_code = ?
    ");
    
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_count = $result->fetch_assoc()['student_count'];
    $stmt->close();
    
    // Format academic year and term for display
    $ay = $class_info['academic_year'];
    $term = $class_info['term'];
    
    // Convert term code to readable format
    $term_display = '';
    switch($term) {
        case 'T1':
            $term_display = '1st Term';
            break;
        case 'T2':
            $term_display = '2nd Term';
            break;
        case 'T3':
            $term_display = '3rd Term';
            break;
        default:
            $term_display = $term;
    }
    
    // Format academic year (e.g., "24" -> "2024-2025")
    $year_start = 2000 + intval($ay);
    $year_end = $year_start + 1;
    $ay_display = "{$year_start}-{$year_end}";
    
    echo json_encode([
        'success' => true,
        'class_info' => [
            'class_code' => $class_info['class_code'],
            'course_code' => $class_info['course_code'],
            'course_title' => $class_info['course_title'],
            'course_description' => $class_info['course_desc'],
            'units' => $class_info['units'],
            'section' => $class_info['section'],
            'academic_year' => $ay_display,
            'academic_year_raw' => $ay,
            'term' => $term_display,
            'term_raw' => $term,
            'schedule' => $class_info['day'] . ' ' . $class_info['time'],
            'day' => $class_info['day'],
            'time' => $class_info['time'],
            'room' => $class_info['room'],
            'faculty_name' => $class_info['faculty_name'],
            'student_count' => $student_count,
            'midterm_weight' => floatval($class_info['midterm_weight']),
            'finals_weight' => floatval($class_info['finals_weight'])
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get class info error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving class information: ' . $e->getMessage()
    ]);
}

exit;
?>