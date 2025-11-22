<?php
// Enable error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Log file access
error_log("process_student.php accessed at " . date('Y-m-d H:i:s'));

// Include required configuration files
try {
    require_once '../config/session.php';
    require_once '../config/db.php';
     require_once '../config/email_helper.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Configuration error: ' . $e->getMessage()
    ]);
    exit();
}

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify user authentication and authorization
if (!isset($_SESSION['user_id']) || 
    !isset($_SESSION['role']) || 
    ($_SESSION['role'] !== 'faculty' && $_SESSION['role'] !== 'admin')) {

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Access denied. Faculty or admin access required.'
    ]);
    exit();
}

// Test database connection
if (!isset($conn) || !$conn) {
    error_log("Database connection failed: " . (mysqli_connect_error() ?: 'Connection variable not set'));
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed'
    ]);
    exit();
}

// Set response headers
header('Content-Type: application/json');

// Log request data for debugging
error_log("Request method: " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown'));
error_log("POST data: " . print_r($_POST, true));

// Get requested action
$action = $_POST['action'] ?? $_GET['action'] ?? '';
error_log("Action requested: " . $action);

// Route to appropriate function
try {
    switch ($action) {
        case 'add':
            addStudent();
            break;
        case 'update':
            updateStudent();
            break;
        case 'delete':
            deleteStudent();
            break;
        default:
            error_log("Invalid action received: " . $action);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    error_log("Student management error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
function addStudent() {
    global $conn;

    error_log("=== addStudent() function called ===");

    // Extract and sanitize input data
    $student_id = trim($_POST['student_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_initial = trim($_POST['middle_initial'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
    $status = 'active'; // Always set to active initially

    // Log received data for debugging
    error_log("Processing student data:");
    error_log("- Student ID: '$student_id'");
    error_log("- First Name: '$first_name'");
    error_log("- Last Name: '$last_name'");
    error_log("- Middle Initial: '$middle_initial'");
    error_log("- Email: '$email'");
    error_log("- Birthday: '$birthday'");

    // Validate required fields
    if (empty($student_id) || empty($first_name) || empty($last_name) || empty($email)) {
        error_log("ERROR: Validation failed - missing required fields");
        echo json_encode([
            'success' => false, 
            'message' => 'Required fields missing'
        ]);
        return;
    }

    // Validate student ID format (YYYY-XXXXXX)
    if (!preg_match('/^\d{4}-\d{6}$/', $student_id)) {
        error_log("ERROR: Invalid student ID format: $student_id");
        echo json_encode([
            'success' => false, 
            'message' => 'Student ID must follow format YYYY-XXXXXX (e.g., 2024-123456)'
        ]);
        return;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("ERROR: Validation failed - invalid email format: $email");
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid email format'
        ]);
        return;
    }
    
    error_log("Validation passed, starting database operations");

    $transaction_started = false;

    try {
        // Check for existing student ID
        // Check for existing student ID in student table
error_log("Checking for existing student ID: $student_id");
$check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM student WHERE student_id = ?");

if (!$check_stmt) {
    throw new Exception('Database prepare failed: ' . $conn->error);
}

$check_stmt->bind_param("s", $student_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    error_log("Student ID already exists: $student_id");
    echo json_encode([
        'success' => false, 
        'message' => 'Student ID already exists'
    ]);
    return;
}

// ADDED: Check if ID exists in faculty (users table)
error_log("Checking if ID exists in faculty records: $student_id");
$check_faculty_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE employee_id = ? AND role = 'faculty'");

if (!$check_faculty_stmt) {
    throw new Exception('Faculty check prepare failed: ' . $conn->error);
}

$check_faculty_stmt->bind_param("s", $student_id);
$check_faculty_stmt->execute();
$faculty_result = $check_faculty_stmt->get_result();
$faculty_row = $faculty_result->fetch_assoc();

if ($faculty_row['count'] > 0) {
    error_log("ID already exists as faculty employee ID: $student_id");
    echo json_encode([
        'success' => false, 
        'message' => 'This ID is already used by a faculty member'
    ]);
    return;
}

        if (!$check_stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }

        $check_stmt->bind_param("s", $student_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            error_log("Student ID already exists: $student_id");
            echo json_encode([
                'success' => false, 
                'message' => 'Student ID already exists'
            ]);
            return;
        }

        // Check for existing email
        // Check for existing email in student table
error_log("Checking for existing email: $email");
$email_stmt = $conn->prepare("SELECT COUNT(*) as count FROM student WHERE email = ?");
if (!$email_stmt) {
    throw new Exception('Email check prepare failed: ' . $conn->error);
}

$email_stmt->bind_param("s", $email);
$email_stmt->execute();
$email_result = $email_stmt->get_result();
$email_row = $email_result->fetch_assoc();

if ($email_row['count'] > 0) {
    error_log("Email already exists: $email");
    echo json_encode([
        'success' => false, 
        'message' => 'Email already exists'
    ]);
    return;
}

// ADDED: Check if email exists in users table (faculty/admin)
error_log("Checking if email exists in faculty/admin records: $email");
$check_users_email = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");

if (!$check_users_email) {
    throw new Exception('Users email check prepare failed: ' . $conn->error);
}

$check_users_email->bind_param("s", $email);
$check_users_email->execute();
$users_email_result = $check_users_email->get_result();
$users_email_row = $users_email_result->fetch_assoc();

if ($users_email_row['count'] > 0) {
    error_log("Email already exists in users table: $email");
    echo json_encode([
        'success' => false, 
        'message' => 'This email is already used by a faculty or admin account'
    ]);
    return;
}
        if (!$email_stmt) {
            throw new Exception('Email check prepare failed: ' . $conn->error);
        }

        $email_stmt->bind_param("s", $email);
        $email_stmt->execute();
        $email_result = $email_stmt->get_result();
        $email_row = $email_result->fetch_assoc();

        if ($email_row['count'] > 0) {
            error_log("Email already exists: $email");
            echo json_encode([
                'success' => false, 
                'message' => 'Email already exists'
            ]);
            return;
        }
        
        // Generate temporary password
        $temporary_password = generateRandomPassword(12);
        $hashed_password = password_hash($temporary_password, PASSWORD_DEFAULT);
        error_log("Generated temporary password for student");
            
        // Start transaction
        error_log("Starting database transaction");
        $conn->autocommit(false);
        $transaction_started = true;

        // Insert student record with password and account status
        error_log("Inserting student record");
        
        $student_query = "INSERT INTO student (
            last_name, 
            first_name, 
            middle_initial, 
            student_id, 
            email, 
            birthday, 
            status,
            password,
            must_change_password,
            account_status,
            enrollment_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'pending', NOW())";
        
        $student_stmt = $conn->prepare($student_query);

        if (!$student_stmt) {
            throw new Exception('Student insert prepare failed: ' . $conn->error);
        }

        // Handle empty middle_initial properly
        $middle_initial_value = !empty($middle_initial) ? $middle_initial : null;

        $student_stmt->bind_param("ssssssss", 
            $last_name, 
            $first_name, 
            $middle_initial_value, 
            $student_id, 
            $email, 
            $birthday, 
            $status,
            $hashed_password
        );

        if (!$student_stmt->execute()) {
            throw new Exception('Student insert failed: ' . $student_stmt->error);
        }

        $affected_rows = $student_stmt->affected_rows;
        error_log("Student record inserted successfully. Affected rows: $affected_rows");

        if ($affected_rows === 0) {
            throw new Exception('No rows were inserted');
        }

        // Build full name
        $full_name = $first_name . ' ' . ($middle_initial ? $middle_initial . '. ' : '') . $last_name;

        // Log audit action
        logAuditAction($_SESSION['user_id'], $_SESSION['name'], 'ADD_STUDENT', 
                      "Added student: $student_id - $full_name");

        // Commit transaction
        $conn->commit();
        $conn->autocommit(true);
        $transaction_started = false;
        error_log("Transaction committed successfully");

        // Send email with credentials
        require_once '../config/email_helper.php';
        $email_result = sendStudentAccountCreationEmail($email, $full_name, $student_id, $temporary_password);
        
        // Prepare student data for response
        $student_data = [
            'student_id' => $student_id,
            'full_name' => $full_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'middle_initial' => $middle_initial,
            'email' => $email,
            'birthday' => $birthday,
            'status' => $status
        ];
        
        if ($email_result['success']) {
            echo json_encode([
                'success' => true, 
                'message' => 'Student account created successfully! Login credentials have been sent to ' . $email,
                'student' => $student_data,
                'email_sent' => true
            ]);
        } else {
            // Account was created but email failed
            echo json_encode([
                'success' => true, 
                'message' => 'Student account created, but email sending failed. Please provide credentials manually.',
                'student' => $student_data,
                'email_sent' => false,
                'email_error' => $email_result['message'],
                'manual_credentials' => [
                    'student_id' => $student_id,
                    'password' => $temporary_password
                ]
            ]);
        }
            
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($transaction_started) {
            try {
                $conn->rollback();
                $conn->autocommit(true);
                error_log("Transaction rolled back");
            } catch (Exception $rollback_error) {
                error_log("Rollback failed: " . $rollback_error->getMessage());
            }
        }

        error_log("Add student error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}


function updateStudent() {
    global $conn;

    error_log("=== updateStudent() function called ===");

    // Extract and sanitize input data
    $student_id = trim($_POST['student_id'] ?? '');
    $original_id = trim($_POST['original_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_initial = trim($_POST['middle_initial'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthday = $_POST['birthday'] ?? null;
    $status = trim($_POST['status'] ?? 'active');

    // Validate required fields
    if (empty($student_id) || empty($original_id) || empty($first_name) || empty($last_name) || empty($email)) {
        error_log("Update validation failed - missing required fields");
        echo json_encode([
            'success' => false, 
            'message' => 'Required fields missing'
        ]);
        return;
    }

    // Validate student ID format
    if (!preg_match('/^\d{4}-\d{6}$/', $student_id)) {
        error_log("ERROR: Invalid student ID format: $student_id");
        echo json_encode([
            'success' => false, 
            'message' => 'Student ID must follow format YYYY-XXXXXX (e.g., 2024-123456)'
        ]);
        return;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Update validation failed - invalid email format");
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid email format'
        ]);
        return;
    }

    // Validate status
    $valid_statuses = ['active', 'inactive', 'graduated', 'transferred', 'suspended', 'pending'];
    if (!in_array($status, $valid_statuses)) {
        error_log("ERROR: Invalid status: $status");
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid status value'
        ]);
        return;
    }
    
    // Start database transaction
    $conn->begin_transaction();

    try {
        // Check if new student ID already exists (if different from original)
        if ($student_id !== $original_id) {
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM student WHERE student_id = ?");
            $check_stmt->bind_param("s", $student_id);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();
            
            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => 'Student ID already exists']);
                return;
            }
        }

        // Check if email already exists (excluding current student)
        $check_email_stmt = $conn->prepare("SELECT COUNT(*) FROM student WHERE email = ? AND student_id != ?");
        $check_email_stmt->bind_param("ss", $email, $original_id);
        $check_email_stmt->execute();
        $check_email_stmt->bind_result($email_count);
        $check_email_stmt->fetch();
        $check_email_stmt->close();

        if ($email_count > 0) {
            echo json_encode(['success' => false, 'message' => 'Email address already exists']);
            return;
        }

        // Update student record - CORRECTED ORDER
        error_log("Updating student record for ID: $original_id");
        
        $middle_initial_value = !empty($middle_initial) ? $middle_initial : null;
        
        $update_student = $conn->prepare("UPDATE student 
                                         SET last_name = ?, first_name = ?, middle_initial = ?, student_id = ?, email = ?, birthday = ?, status = ? 
                                         WHERE student_id = ?");
        $update_student->bind_param("ssssssss", $last_name, $first_name, $middle_initial_value, $student_id, $email, $birthday, $status, $original_id);

        if (!$update_student->execute()) {
            throw new Exception('Failed to update student record: ' . $update_student->error);
        }

        if ($update_student->affected_rows === 0) {
            throw new Exception('Student not found or no changes made');
        }

        // Log audit action
        logAuditAction($_SESSION['user_id'], $_SESSION['name'], 'UPDATE_STUDENT', 
                      "Updated student: $first_name $last_name (ID: $student_id)");

        // Commit transaction
        $conn->commit();
        error_log("Student update completed successfully");

        echo json_encode([
            'success' => true, 
            'message' => 'Student updated successfully!'
        ]);
            
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Update student error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Update failed: ' . $e->getMessage()
        ]);
    }
}

function deleteStudent() {
    global $conn;

    error_log("=== deleteStudent() function called ===");

    // Get student ID from POST or GET
    $student_id = trim($_POST['id'] ?? $_GET['id'] ?? '');

    if (empty($student_id)) {
        error_log("Delete failed - invalid student ID");
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid student ID'
        ]);
        return;
    }
        
    // Get student name for logging before deletion
    $get_name = $conn->prepare("SELECT first_name, last_name FROM student WHERE student_id = ?");
    $get_name->bind_param("s", $student_id);
    $get_name->execute();
    $name_result = $get_name->get_result();
    $student_name = 'Unknown Student';

    if ($name_result->num_rows > 0) {
        $name_data = $name_result->fetch_assoc();
        $student_name = $name_data['first_name'] . ' ' . $name_data['last_name'];
    }

    // Start database transaction
    $conn->begin_transaction();

    try {
        // Delete class enrollments first (foreign key constraints)
        error_log("Deleting class enrollments for student ID: $student_id");
        $delete_enrollments = $conn->prepare("DELETE FROM class_enrollments WHERE student_id = ?");
        $delete_enrollments->bind_param("s", $student_id);
        $delete_enrollments->execute();
            
        // Delete student record
        error_log("Deleting student record for ID: $student_id");
        $delete_student = $conn->prepare("DELETE FROM student WHERE student_id = ?");
        $delete_student->bind_param("s", $student_id);

        if (!$delete_student->execute() || $delete_student->affected_rows === 0) {
            throw new Exception('Student not found or already deleted');
        }
            
        // Log audit action
        logAuditAction($_SESSION['user_id'], $_SESSION['name'], 'DELETE_STUDENT', 
                      "Deleted student: $student_name (ID: $student_id)");

        // Commit transaction
        $conn->commit();
        error_log("Student deletion completed successfully");

        echo json_encode([
            'success' => true, 
            'message' => 'Student deleted successfully!'
        ]);
            
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Delete student error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to delete student: ' . $e->getMessage()
        ]);
    }
}

/**
 * Log audit actions for security and tracking
 */
function logAuditAction($user_id, $username, $action, $details) {
    global $conn;
        
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $timestamp = date('Y-m-d H:i:s');

        // Check if audit_log table exists
        $check_table = $conn->query("SHOW TABLES LIKE 'audit_log'");
        if ($check_table && $check_table->num_rows > 0) {
            $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, username, action, details, timestamp, ip_address) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("isssss", $user_id, $username, $action, $details, $timestamp, $ip_address);
                $log_stmt->execute();
                error_log("Audit log entry created: $action for user $username");
            }
        } else {
            error_log("Audit log table does not exist - skipping audit logging");
        }
    } catch (Exception $e) {
        // Log audit errors silently
        error_log("Audit log error: " . $e->getMessage());
    }
}
 
?>