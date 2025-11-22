<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Ensure it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

require_once '../../config/db.php';
require_once '../../config/session.php';

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $result = addStudent($conn);
            break;
        case 'update':
            $result = updateStudent($conn);
            break;
        case 'delete':
            $result = deleteStudent($conn);
            break;
        case 'create_account':
            $result = createUserAccount($conn);
            break;
        default:
            throw new Exception('Invalid action specified');
    }
    
    echo json_encode($result);
} catch (Exception $e) {
    error_log("Student processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function addStudent($conn) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_initial = trim($_POST['middle_initial'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthday = $_POST['birthday'] ?? null;
    $status = $_POST['student_status'] ?? 'active';

    // Validation
    if (empty($first_name) || empty($last_name) || empty($student_id) || empty($email)) {
        throw new Exception('Please fill in all required fields');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address');
    }

    // Check if student ID already exists
    $check_stmt = $conn->prepare("SELECT student_id FROM student WHERE student_id = ?");
    $check_stmt->bind_param("s", $student_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        throw new Exception('Student ID already exists');
    }

    // Check if email already exists
    $email_stmt = $conn->prepare("SELECT email FROM student WHERE email = ?");
    $email_stmt->bind_param("s", $email);
    $email_stmt->execute();
    if ($email_stmt->get_result()->num_rows > 0) {
        throw new Exception('Email address already exists');
    }

    // Insert student
    $insert_stmt = $conn->prepare("
        INSERT INTO student (student_id, first_name, last_name, middle_initial, email, birthday, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $insert_stmt->bind_param("sssssss", $student_id, $first_name, $last_name, $middle_initial, $email, $birthday, $status);
    
    if (!$insert_stmt->execute()) {
        throw new Exception('Failed to add student: ' . $conn->error);
    }

    return [
        'success' => true,
        'message' => 'Student added successfully!',
        'newItem' => [
            'student_id' => $student_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'middle_initial' => $middle_initial,
            'email' => $email,
            'birthday' => $birthday,
            'status' => $status
        ]
    ];
}

function updateStudent($conn) {
    $student_id = trim($_POST['student_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_initial = trim($_POST['middle_initial'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthday = $_POST['birthday'] ?? null;

    // Validation
    if (empty($first_name) || empty($last_name) || empty($student_id) || empty($email)) {
        throw new Exception('Please fill in all required fields');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address');
    }

    // Check if student exists
    $check_stmt = $conn->prepare("SELECT student_id FROM student WHERE student_id = ?");
    $check_stmt->bind_param("s", $student_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) {
        throw new Exception('Student not found');
    }

    // Check if email already exists for other students
    $email_stmt = $conn->prepare("SELECT student_id FROM student WHERE email = ? AND student_id != ?");
    $email_stmt->bind_param("ss", $email, $student_id);
    $email_stmt->execute();
    if ($email_stmt->get_result()->num_rows > 0) {
        throw new Exception('Email address already exists for another student');
    }

    // Update student
    $update_stmt = $conn->prepare("
        UPDATE student 
        SET first_name = ?, last_name = ?, middle_initial = ?, email = ?, birthday = ?
        WHERE student_id = ?
    ");
    
    $update_stmt->bind_param("ssssss", $first_name, $last_name, $middle_initial, $email, $birthday, $student_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update student: ' . $conn->error);
    }

    // Also update the users table if user account exists
    $user_update_stmt = $conn->prepare("
        UPDATE users 
        SET name = ?
        WHERE student_id = ? AND role = 'student'
    ");
    
    $full_name = $first_name . ' ' . ($middle_initial ? $middle_initial . '. ' : '') . $last_name;
    $user_update_stmt->bind_param("ss", $full_name, $student_id);
    $user_update_stmt->execute();

    return [
        'success' => true,
        'message' => 'Student updated successfully!'
    ];
}

function deleteStudent($conn) {
    $student_id = trim($_POST['student_id'] ?? '');

    if (empty($student_id)) {
        throw new Exception('Student ID is required');
    }

    // Check if student exists
    $check_stmt = $conn->prepare("SELECT student_id FROM student WHERE student_id = ?");
    $check_stmt->bind_param("s", $student_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) {
        throw new Exception('Student not found');
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Delete from users table first (if exists)
        $delete_user_stmt = $conn->prepare("DELETE FROM users WHERE student_id = ? AND role = 'student'");
        $delete_user_stmt->bind_param("s", $student_id);
        $delete_user_stmt->execute();

        // Delete from student_enrollments table
        $delete_enrollments_stmt = $conn->prepare("DELETE FROM student_enrollments WHERE student_id = ?");
        $delete_enrollments_stmt->bind_param("s", $student_id);
        $delete_enrollments_stmt->execute();

        // Delete from grades table (if exists)
        $delete_grades_stmt = $conn->prepare("DELETE FROM grades WHERE student_id = ?");
        $delete_grades_stmt->bind_param("s", $student_id);
        $delete_grades_stmt->execute();

        // Finally delete from student table
        $delete_student_stmt = $conn->prepare("DELETE FROM student WHERE student_id = ?");
        $delete_student_stmt->bind_param("s", $student_id);
        
        if (!$delete_student_stmt->execute()) {
            throw new Exception('Failed to delete student: ' . $conn->error);
        }

        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Student deleted successfully!'
        ];
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function createUserAccount($conn) {
    $student_id = trim($_POST['student_id'] ?? '');

    if (empty($student_id)) {
        throw new Exception('Student ID is required');
    }

    // Get student details
    $student_stmt = $conn->prepare("SELECT first_name, last_name, middle_initial, email FROM student WHERE student_id = ?");
    $student_stmt->bind_param("s", $student_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    
    if ($student_result->num_rows === 0) {
        throw new Exception('Student not found');
    }
    
    $student = $student_result->fetch_assoc();

    // Check if user account already exists
    $user_check_stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? AND role = 'student'");
    $user_check_stmt->bind_param("s", $student_id);
    $user_check_stmt->execute();
    if ($user_check_stmt->get_result()->num_rows > 0) {
        throw new Exception('User account already exists for this student');
    }

    // Create user account
    $full_name = $student['first_name'] . ' ' . ($student['middle_initial'] ? $student['middle_initial'] . '. ' : '') . $student['last_name'];
    $email = $student['email'];
    $password = password_hash($student_id, PASSWORD_DEFAULT); // Default password is student ID

    $insert_user_stmt = $conn->prepare("
        INSERT INTO users (student_id, name, email, password, role, status) 
        VALUES (?, ?, ?, ?, 'student', 'active')
    ");
    
    $insert_user_stmt->bind_param("ssss", $student_id, $full_name, $email, $password);
    
    if (!$insert_user_stmt->execute()) {
        throw new Exception('Failed to create user account: ' . $conn->error);
    }

    return [
        'success' => true,
        'message' => 'Login account created successfully! Default password is the student ID.'
    ];
}
?>