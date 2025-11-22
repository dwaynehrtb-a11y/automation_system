<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check user role
$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'];

// Get user details based on role
if ($role === 'student') {
    $student_id = $_SESSION['student_id'];
    $check_query = "SELECT must_change_password, 'student' as role, CONCAT(first_name, ' ', last_name) as name, email FROM student WHERE student_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $student_id);
} else {
    $check_query = "SELECT must_change_password, role, name, email FROM users WHERE id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// If user doesn't need to change password, redirect to appropriate dashboard
if ($user['must_change_password'] == 0) {
    if ($user['role'] == 'admin') {
        header('Location: ../admin_dashboard.php');
    } elseif ($user['role'] == 'faculty') {
        header('Location: ../dashboards/faculty_dashboard.php');
    } else {
        header('Location: ../student/student_dashboard.php');
    }
    exit();
}

$user_info = $user;

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - First Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/change-password.css">
</head>
<body>
    <div class="change-password-container">
        <div class="header-section">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1>Change Your Password</h1>
            <p>First Time Login - Security Setup Required</p>
        </div>

        <div class="content-section">
            <div class="welcome-message">
                <h3>Welcome, <?= htmlspecialchars($user_info['name']) ?>!</h3>
                <p><?= htmlspecialchars($user_info['email']) ?></p>
                <?php if ($role === 'student'): ?>
                    <p class="role-badge student">Student Account</p>
                <?php else: ?>
                    <p class="role-badge"><?= htmlspecialchars(ucfirst($user_info['role'])) ?> Account</p>
                <?php endif; ?>
            </div>

            <div class="security-notice">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="security-notice-text">
                    <strong>Security Notice:</strong> For your account security, you must change your temporary password before accessing the system.
                </div>
            </div>

            <div class="error-message" id="errorMessage"></div>

            <form id="changePasswordForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="user_role" value="<?= htmlspecialchars($role) ?>">

                <div class="form-group">
                    <label class="form-label" for="current_password">
                        <i class="fas fa-key"></i> Current Password (Temporary)
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="current_password" 
                               name="current_password" 
                               class="form-control" 
                               placeholder="Enter your temporary password"
                               required>
                        <button type="button" class="toggle-password" data-target="current_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">
                        <i class="fas fa-lock"></i> New Password
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-control" 
                               placeholder="Enter your new password"
                               required>
                        <button type="button" class="toggle-password" data-target="new_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <div class="password-requirements">
                        <h4>Password Requirements:</h4>
                        <div class="requirement-item" id="req-length">
                            <i class="fas fa-circle"></i>
                            <span>At least 8 characters long</span>
                        </div>
                        <div class="requirement-item" id="req-uppercase">
                            <i class="fas fa-circle"></i>
                            <span>Contains uppercase letter (A-Z)</span>
                        </div>
                        <div class="requirement-item" id="req-lowercase">
                            <i class="fas fa-circle"></i>
                            <span>Contains lowercase letter (a-z)</span>
                        </div>
                        <div class="requirement-item" id="req-number">
                            <i class="fas fa-circle"></i>
                            <span>Contains number (0-9)</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">
                        <i class="fas fa-lock"></i> Confirm New Password
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-control" 
                               placeholder="Re-enter your new password"
                               required>
                        <button type="button" class="toggle-password" data-target="confirm_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-check-circle"></i>
                    <span id="btnText">Change Password & Continue</span>
                    <span id="btnLoading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Updating...
                    </span>
                </button>
            </form>

            <div class="logout-link">
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/change-password.js"></script>
</body>
</html>