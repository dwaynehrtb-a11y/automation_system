<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/email_helper.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$employee_id = trim($_POST['employee_id'] ?? '');
$role = 'faculty'; // Default role for this form

// Validation
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Valid email is required']);
    exit;
}

if (empty($employee_id)) {
    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
    exit;
}

try {
    // Check if employee ID already exists
    $check_stmt = executeQuery($conn,
        "SELECT id FROM users WHERE employee_id = ?",
        [$employee_id], 's'
    );
    
    $result = $check_stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Employee ID already exists']);
        exit;
    }
    //  Check if ID exists in student table
$check_student_stmt = executeQuery($conn,
    "SELECT student_id FROM student WHERE student_id = ?",
    [$employee_id], 's'
);

$student_result = $check_student_stmt->get_result();
if ($student_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'This ID is already used by a student']);
    exit;
}
    
    // Check if email already exists
    $check_email_stmt = executeQuery($conn,
        "SELECT id FROM users WHERE email = ?",
        [$email], 's'
    );
    
    $email_result = $check_email_stmt->get_result();
    if ($email_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    // Generate temporary password
    $temporary_password = generateRandomPassword(12);
    $hashed_password = password_hash($temporary_password, PASSWORD_DEFAULT);
    
    // Insert new faculty user with first_login = 1
    $insert_stmt = executeQuery($conn,
        "INSERT INTO users (name, email, employee_id, role, password, first_login, created_at) 
         VALUES (?, ?, ?, ?, ?, 1, NOW())",
        [$name, $email, $employee_id, $role, $hashed_password], 'sssss'
    );
    
    $new_user_id = $conn->insert_id;
    
    // Log the account creation
    try {
        $admin_user = getCurrentUser();
        $audit_stmt = executeQuery($conn,
            "INSERT INTO audit_log (user_id, username, action, details, timestamp, ip_address) 
             VALUES (?, ?, 'ACCOUNT_CREATED', ?, NOW(), ?)",
            [
                $admin_user['id'],
                $admin_user['name'],
                "Created faculty account for {$name} (Employee ID: {$employee_id})",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ],
            'isss'
        );
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
    
    // Send email with credentials
    $email_result = sendAccountCreationEmail($email, $name, $employee_id, $temporary_password, $role);
    
    // Prepare faculty data for response
$faculty_data = [
    'id' => $new_user_id,
    'name' => $name,
    'email' => $email,
    'employee_id' => $employee_id
];

if ($email_result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Faculty account created successfully! Login credentials have been sent to ' . $email,
        'faculty' => $faculty_data,
        'email_sent' => true
    ]);
} else {
    // Account was created but email failed
    echo json_encode([
        'success' => true,
        'message' => 'Faculty account created, but email sending failed. Please provide credentials manually.',
        'faculty' => $faculty_data,
        'email_sent' => false,
        'email_error' => $email_result['message'],
        'manual_credentials' => [
            'employee_id' => $employee_id,
            'password' => $temporary_password
        ]
    ]);
}
    
} catch (Exception $e) {
    error_log("Faculty account creation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error creating account: ' . $e->getMessage()
    ]);
}
?>
