<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
try {
    require_once '../config/session.php';
    require_once '../config/db.php';
    require_once '../config/encryption.php';
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
$student_id = trim($_GET['student_id'] ?? $_GET['id'] ?? '');

// Validate input
if (empty($student_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit();
}

try {
    Encryption::init();
    
    // Helper to check if encrypted
    $is_encrypted = function($data) {
        return !empty($data) && preg_match('/^[A-Za-z0-9+\/=]+$/', $data) && (strlen($data) % 4) == 0;
    };
    
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
            account_status,
            enrollment_date,
            first_login_at
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
    
    // Decrypt names if needed
    if ($is_encrypted($student['first_name'])) {
        try { $student['first_name'] = Encryption::decrypt($student['first_name']); } catch (Exception $e) {}
    }
    if ($is_encrypted($student['last_name'])) {
        try { $student['last_name'] = Encryption::decrypt($student['last_name']); } catch (Exception $e) {}
    }
    if ($is_encrypted($student['middle_initial'])) {
        try { $student['middle_initial'] = Encryption::decrypt($student['middle_initial']); } catch (Exception $e) {}
    }
    
    // Decrypt email if needed
    if ($is_encrypted($student['email'])) {
        try { $student['email'] = Encryption::decrypt($student['email']); } catch (Exception $e) {}
    }
    
    // Decrypt birthday if needed
    if ($is_encrypted($student['birthday'])) {
        try { $student['birthday'] = Encryption::decrypt($student['birthday']); } catch (Exception $e) {}
    }
    
    // Build full name
    $student['full_name'] = $student['last_name'] . ', ' . $student['first_name'];
    if (!empty($student['middle_initial'])) {
        $student['full_name'] .= ' ' . $student['middle_initial'] . '.';
    }
    
    // Format dates
    if ($student['birthday']) {
        $student['birthday'] = date('F j, Y', strtotime($student['birthday']));
    }
    if ($student['enrollment_date']) {
        $student['enrollment_date'] = date('F j, Y', strtotime($student['enrollment_date']));
    }
    
    // Get student enrollments with class details
    $enrollments_stmt = $conn->prepare("
        SELECT DISTINCT
            ce.class_code,
            c.course_code,
            c.section,
            c.term,
            c.academic_year,
            s.course_title,
            ce.status
        FROM class_enrollments ce
        LEFT JOIN class c ON ce.class_code = c.class_code
        LEFT JOIN subjects s ON c.course_code = s.course_code
        WHERE ce.student_id = ?
        ORDER BY c.academic_year DESC, c.term, c.course_code
    ");
    
    $classes = [];
    if ($enrollments_stmt) {
        $enrollments_stmt->bind_param("s", $student_id);
        $enrollments_stmt->execute();
        $enrollments_result = $enrollments_stmt->get_result();
        
        while ($enrollment = $enrollments_result->fetch_assoc()) {
            $classes[] = $enrollment;
        }
    }
    
    echo json_encode([
        'success' => true,
        'student' => $student,
        'classes' => $classes,
        'enrollments' => $classes // For backward compatibility
    ]);
    
} catch (Exception $e) {
    error_log("Get student details error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
}
?>