<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => ''
];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'Unauthorized access. Please login again.';
        echo json_encode($response);
        exit();
    }

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $response['message'] = 'Invalid security token. Please refresh and try again.';
        echo json_encode($response);
        exit();
    }

    // Get form data
    $user_id = $_SESSION['user_id'];
    $role = $_POST['user_role'] ?? $_SESSION['role'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $response['message'] = 'All fields are required.';
        echo json_encode($response);
        exit();
    }

    // Check if passwords match
    if ($new_password !== $confirm_password) {
        $response['message'] = 'New passwords do not match.';
        echo json_encode($response);
        exit();
    }

    // Validate password strength
    if (strlen($new_password) < 8) {
        $response['message'] = 'Password must be at least 8 characters long.';
        echo json_encode($response);
        exit();
    }

    if (!preg_match('/[A-Z]/', $new_password)) {
        $response['message'] = 'Password must contain at least one uppercase letter.';
        echo json_encode($response);
        exit();
    }

    if (!preg_match('/[a-z]/', $new_password)) {
        $response['message'] = 'Password must contain at least one lowercase letter.';
        echo json_encode($response);
        exit();
    }

    if (!preg_match('/[0-9]/', $new_password)) {
        $response['message'] = 'Password must contain at least one number.';
        echo json_encode($response);
        exit();
    }

    // Check if new password is same as current
    if ($current_password === $new_password) {
        $response['message'] = 'New password must be different from your current password.';
        echo json_encode($response);
        exit();
    }

    // Get user's current password based on role
    if ($role === 'student') {
        $student_id = $_SESSION['student_id'];
        $query = "SELECT password, 'student' as role FROM student WHERE student_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $student_id);
    } else {
        $query = "SELECT password, role FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        $response['message'] = 'User not found.';
        echo json_encode($response);
        exit();
    }

    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $response['message'] = 'Current password is incorrect.';
        echo json_encode($response);
        exit();
    }

    // Hash new password
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password based on role
    if ($role === 'student') {
        $student_id = $_SESSION['student_id'];
        $update_query = "UPDATE student 
                         SET password = ?, 
                             must_change_password = 0, 
                             first_login_at = NOW(), 
                             account_status = 'active',
                             first_login = 0
                         WHERE student_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ss", $new_password_hash, $student_id);
        $redirect_url = '../student/student_dashboard.php';
    } else {
        $update_query = "UPDATE users 
                         SET password = ?, 
                             must_change_password = 0, 
                             first_login_at = NOW(), 
                             account_status = 'active',
                             first_login = 0
                         WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $new_password_hash, $user_id);
        
        $redirect_url = ($user['role'] === 'admin') 
            ? '../admin_dashboard.php' 
            : '../dashboards/faculty_dashboard.php';
    }

    if ($update_stmt->execute()) {
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Clear first_login flag from session
        unset($_SESSION['first_login']);

        $response['success'] = true;
        $response['message'] = 'Password changed successfully!';
        $response['redirect'] = $redirect_url;

        // Log the password change (optional)
        $log_query = "INSERT INTO activity_logs (user_id, action, timestamp) VALUES (?, 'Password changed', NOW())";
        if ($log_stmt = $conn->prepare($log_query)) {
            $log_stmt->bind_param("i", $user_id);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } else {
        $response['message'] = 'Failed to update password. Please try again.';
    }

    $update_stmt->close();
    $stmt->close();

} catch (Exception $e) {
    error_log("Password change error: " . $e->getMessage());
    $response['message'] = 'An error occurred. Please try again later.';
}

echo json_encode($response);
exit();
?>