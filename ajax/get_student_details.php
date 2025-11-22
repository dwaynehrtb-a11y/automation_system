<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
try {
    require_once '../config/session.php';
    require_once '../config/db.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Configuration error']);
    exit();
}

// Initialize session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON response header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || 
    !isset($_SESSION['role']) || 
    ($_SESSION['role'] !== 'faculty' && $_SESSION['role'] !== 'admin')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get the student ID from the request
$student_id = trim($_GET['id'] ?? '');

// Validate input
if (empty($student_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit();
}

try {
    // Get student details
    $student_stmt = $conn->prepare("
        SELECT 
            student_id,
            first_name,
            last_name,
            middle_initial,
            email,
            birthday,
            status,
            enrollment_date as created_at
        FROM student 
        WHERE student_id = ?
    ");
    
    if (!$student_stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $student_stmt->bind_param("s", $student_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    
    if ($student_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }
    
    $student = $student_result->fetch_assoc();
    
    // Get student enrollments
    $enrollments_stmt = $conn->prepare("
        SELECT 
            ce.enrollment_id,
            ce.status,
            c.class_id,
            s.course_code as class_code,
            s.course_title,
            u.name as instructor
        FROM class_enrollments ce
        LEFT JOIN class c ON ce.class_id = c.class_id
        LEFT JOIN subjects s ON c.course_code = s.course_code
        LEFT JOIN users u ON c.faculty_id = u.id
        WHERE ce.student_id = ?
        ORDER BY ce.enrollment_date DESC
    ");
    
    $enrollments = [];
    if ($enrollments_stmt) {
        $enrollments_stmt->bind_param("s", $student_id);
        $enrollments_stmt->execute();
        $enrollments_result = $enrollments_stmt->get_result();
        
        while ($enrollment = $enrollments_result->fetch_assoc()) {
            $enrollments[] = $enrollment;
        }
    }
    
    echo json_encode([
        'success' => true,
        'student' => $student,
        'enrollments' => $enrollments
    ]);
    
} catch (Exception $e) {
    error_log("Get student details error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
}
?>