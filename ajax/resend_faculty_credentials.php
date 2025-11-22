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

// Get faculty ID
$faculty_id = intval($_POST['faculty_id'] ?? 0);

if ($faculty_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid faculty ID']);
    exit;
}

try {
    // Get faculty details
    $faculty_stmt = executeQuery($conn,
        "SELECT id, name, email, employee_id, role, must_change_password 
         FROM users 
         WHERE id = ? AND role = 'faculty'",
        [$faculty_id], 'i'
    );
    
    $result = $faculty_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Faculty member not found']);
        exit;
    }
    
    $faculty = $result->fetch_assoc();
    
    // Check if faculty is still pending (hasn't changed password yet)
    if ($faculty['must_change_password'] != 1) {
        echo json_encode([
            'success' => false, 
            'message' => 'This faculty member has already activated their account'
        ]);
        exit;
    }
    
    // Generate new temporary password
    $new_password = generateRandomPassword(12);
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password in database
    $update_stmt = executeQuery($conn,
        "UPDATE users 
         SET password = ?, must_change_password = 1 
         WHERE id = ?",
        [$hashed_password, $faculty_id], 'si'
    );
    
    // Log the action
    try {
        $admin_user = getCurrentUser();
        $audit_stmt = executeQuery($conn,
            "INSERT INTO audit_log (user_id, username, action, details, timestamp, ip_address) 
             VALUES (?, ?, 'CREDENTIALS_RESENT', ?, NOW(), ?)",
            [
                $admin_user['id'],
                $admin_user['name'],
                "Resent credentials for {$faculty['name']} (Employee ID: {$faculty['employee_id']})",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ],
            'isss'
        );
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
    
    // Send email with new credentials
    $email_result = sendAccountCreationEmail(
        $faculty['email'], 
        $faculty['name'], 
        $faculty['employee_id'], 
        $new_password, 
        $faculty['role']
    );
    
    if ($email_result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Credentials have been resent successfully to ' . $faculty['email']
        ]);
    } else {
        // Password was updated but email failed
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email: ' . $email_result['message'],
            'password_updated' => true,
            'manual_credentials' => [
                'employee_id' => $faculty['employee_id'],
                'password' => $new_password
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Resend credentials error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error resending credentials: ' . $e->getMessage()
    ]);
}
?>