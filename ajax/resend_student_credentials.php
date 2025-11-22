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

// Get student ID
$student_id = trim($_POST['student_id'] ?? '');

if (empty($student_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit;
}

try {
    // Get student details
    $student_stmt = $conn->prepare(
        "SELECT student_id, first_name, last_name, middle_initial, email, must_change_password 
         FROM student 
         WHERE student_id = ?"
    );
    
    $student_stmt->bind_param("s", $student_id);
    $student_stmt->execute();
    $result = $student_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    $student = $result->fetch_assoc();
    
    // Check if student is still pending (hasn't changed password yet)
    if ($student['must_change_password'] != 1) {
        echo json_encode([
            'success' => false, 
            'message' => 'This student has already activated their account'
        ]);
        exit;
    }
    
    // Generate new temporary password
    $new_password = generateRandomPassword(12);
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password in database
    $update_stmt = $conn->prepare(
        "UPDATE student 
         SET password = ?, must_change_password = 1 
         WHERE student_id = ?"
    );
    
    $update_stmt->bind_param("ss", $hashed_password, $student_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update password');
    }
    
    // Build full name
    $full_name = $student['first_name'] . ' ' . 
                 ($student['middle_initial'] ? $student['middle_initial'] . '. ' : '') . 
                 $student['last_name'];
    
    // Log the action
    try {
        $admin_user = getCurrentUser();
        $log_stmt = $conn->prepare(
            "INSERT INTO audit_log (user_id, username, action, details, timestamp, ip_address) 
             VALUES (?, ?, 'STUDENT_CREDENTIALS_RESENT', ?, NOW(), ?)"
        );
        
        if ($log_stmt) {
            $details = "Resent credentials for {$full_name} (Student ID: {$student_id})";
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            $log_stmt->bind_param("isss", 
                $admin_user['id'], 
                $admin_user['name'], 
                $details, 
                $ip_address
            );
            $log_stmt->execute();
        }
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
    
    // Send email with new credentials
    $email_result = resendStudentCredentials(
        $student['email'], 
        $full_name, 
        $student_id, 
        $new_password
    );
    
    if ($email_result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Credentials have been resent successfully to ' . $student['email']
        ]);
    } else {
        // Password was updated but email failed
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email: ' . $email_result['message'],
            'password_updated' => true,
            'manual_credentials' => [
                'student_id' => $student_id,
                'password' => $new_password
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Resend student credentials error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error resending credentials: ' . $e->getMessage()
    ]);
}
?>