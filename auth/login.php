<?php
require_once '../config/session.php';
require_once '../config/db.php';

if (isAuthenticated()) {
    $current_user = getCurrentUser();
    
    // // Check if first login
    // if (isset($_SESSION['first_login']) && $_SESSION['first_login'] === true) {
    //     header("Location: change_password.php");
    //     exit();
    // }
    
    // Redirect based on role
    if ($current_user['role'] === 'admin') {
        header("Location: ../admin_dashboard.php");
    } elseif ($current_user['role'] === 'faculty') {
        header("Location: ../dashboards/faculty_dashboard.php");
    } else {
        header("Location: ../student/student_dashboard.php");
    }
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $user_id = trim($_POST['user_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        if (empty($user_id) || empty($password) || empty($role)) {
            $error_message = 'Please fill in all required fields';
        } else {
            try {
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $current_time = time();

                if (!isset($_SESSION['login_attempts'])) {
                    $_SESSION['login_attempts'] = [];
                }

                $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], 
                    function($attempt_time) use ($current_time) {
                        return ($current_time - $attempt_time) < 900;
                    }
                );

                if (count($_SESSION['login_attempts']) >= 5) {
                    $error_message = 'Too many failed login attempts. Please try again in 15 minutes.';
                } else {
                    if ($role === 'student') {
                        $stmt = executeQuery($conn, 
                            "SELECT student_id as id, CONCAT(first_name, ' ', last_name) as name, 
                                    student_id as identifier, 'student' as role, email, first_login, 
                                    password, must_change_password, account_status, first_login_at
                             FROM student WHERE student_id = ?",
                            [$user_id], 's'
                        );
                    } else {
                        $stmt = executeQuery($conn, 
                            "SELECT id, name, employee_id as identifier, password, role, email, first_login 
                             FROM users WHERE employee_id = ? AND role = ? AND id IS NOT NULL",
                            [$user_id, $role], 'ss'
                        );
                    }
                    
                    $result = $stmt->get_result();
                    
                    if ($user = $result->fetch_assoc()) {
                        $login_successful = false;
                        
                        if ($role === 'student') {
                            // Verify password for students
                            if (password_verify($password, $user['password'])) {
                                $login_successful = true;
                                
                                // Update first_login_at if this is the first login
                                if (empty($user['first_login_at'])) {
                                    $update_stmt = executeQuery($conn,
                                        "UPDATE student SET first_login_at = NOW() WHERE student_id = ?",
                                        [$user_id], 's'
                                    );
                                }
                            }
                        } else {
                            if (password_verify($password, $user['password'])) {
                                $login_successful = true;
                            }
                        }
                        
                        if ($login_successful) {
                            unset($_SESSION['login_attempts']);
                            
                            $session_data = [
                                'id' => $user['id'],
                                'name' => $user['name'],
                                'role' => $user['role'],
                                'email' => $user['email'] ?? '',
                                'employee_id' => $user['role'] !== 'student' ? $user['identifier'] : '',
                                'student_id' => $user['role'] === 'student' ? $user['identifier'] : ''
                            ];
                            
                            createUserSession($session_data);
                            
                            // Check if first login or must change password
                            if ($role === 'student') {
                                if ((isset($user['first_login']) && $user['first_login'] == 1) || 
                                    (isset($user['must_change_password']) && $user['must_change_password'] == 1)) {
                                    $_SESSION['first_login'] = true;
                                    header("Location: change_password.php");
                                    exit();
                                }
                            } else {
                                if (isset($user['first_login']) && $user['first_login'] == 1) {
                                    $_SESSION['first_login'] = true;
                                    header("Location: change_password.php");
                                    exit();
                                }
                            }
                            
                            try {
                                $login_method = 'ID and password';
                                $audit_stmt = executeQuery($conn,
                                    "INSERT INTO audit_log (user_id, username, action, details, timestamp, ip_address) VALUES (?, ?, 'LOGIN', ?, NOW(), ?)",
                                    [$user['id'], $user['name'], "User logged in successfully ($login_method)", $ip_address], 'isss'
                                );
                            } catch (Exception $e) {
                                error_log("Audit log error: " . $e->getMessage());
                            }
                            
                            if ($role === 'admin') {
                                header("Location: ../admin_dashboard.php");
                            } elseif ($role === 'faculty') {
                                header("Location: ../dashboards/faculty_dashboard.php");
                            } else {
                                header("Location: ../student/student_dashboard.php");
                            }
                            exit();
                        } else {
                            $_SESSION['login_attempts'][] = $current_time;
                            
                            try {
                                $audit_stmt = executeQuery($conn,
                                    "INSERT INTO audit_log (user_id, username, action, details, timestamp, ip_address) VALUES (?, ?, 'LOGIN_FAILED', 'Invalid password attempt', NOW(), ?)",
                                    [$user['id'], $user['name'], $ip_address], 'iss'
                                );
                            } catch (Exception $e) {
                                error_log("Audit log error: " . $e->getMessage());
                            }
                            
                            $error_message = 'Invalid password';
                        }
                    } else {
                        $_SESSION['login_attempts'][] = $current_time;
                        
                        try {
                            $audit_stmt = executeQuery($conn,
                                "INSERT INTO audit_log (user_id, username, action, details, timestamp, ip_address) VALUES (NULL, ?, 'LOGIN_FAILED', ?, NOW(), ?)",
                                [$user_id, "Invalid user ID: $user_id for role: $role", $ip_address], 'sss'
                            );
                        } catch (Exception $e) {
                            error_log("Audit log error: " . $e->getMessage());
                        }
                        
                        if ($role === 'student') {
                            $error_message = 'Student ID not found or inactive';
                        } else {
                            $error_message = 'User not found or invalid role';
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error_message = 'An error occurred during login. Please try again.';
            }
        }
    }
}

if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NU Academic System - Login</title>
    <link rel="icon" type="image/png" href="/auth/assets/images/favicon.png">
    <link rel="stylesheet" href="../admin/assets/css/login.css?v=<?= time() ?>">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">        
            <div class="login-right"> 
                <div class="login-header"> 
                    <div class="logo"><img src="../assets/images/nu_logo.png" alt="NU Logo" style="max-width: 100px; height: auto;"></div> 
                    <h1>NU Academic System</h1> 
                    <p class="subtitle">Sign in to your account</p> 
                </div>  

                <div class="instructions"> 
                    <h3>Login Instructions:</h3> 
                    <p><strong>All Users:</strong> Enter your ID and Password</p> 
                    <p><strong>Students:</strong> Use Student ID</p> 
                    <p><strong>Faculty/Admin:</strong> Use Employee ID</p>
                    <p style="margin-top: 0.5rem; color: #6b7280; font-size: 0.875rem;">
                        📧 Check your email for your temporary password
                    </p>
                </div>  

                <?php if (isset($_SESSION['login_attempts']) && count($_SESSION['login_attempts']) >= 3): ?> 
                <div class="security-info">     
                    ⚠️ Security Notice: Multiple failed login attempts detected. Account will be temporarily locked after 5 failed attempts. 
                </div> 
                <?php endif; ?>  

                <?php if ($error_message): ?> 
                <div class="alert alert-error">     
                    <?= htmlspecialchars($error_message) ?> 
                </div> 
                <?php endif; ?>  

                <?php if ($success_message): ?> 
                <div class="alert alert-success">     
                    <?= htmlspecialchars($success_message) ?> 
                </div> 
                <?php endif; ?>  

                <form method="POST" action=""> 
                    <?php if (function_exists('getCSRFTokenInput')): ?>     
                        <?= getCSRFTokenInput() ?> 
                    <?php else: ?>     
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"> 
                    <?php endif; ?>  

                    <div class="form-group">     
                        <label for="role">Select Role</label>     
                        <select name="role" id="role" required onchange="updateUserIdLabel()">         
                            <option value="">Choose your role</option>         
                            <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Administrator</option>         
                            <option value="faculty" <?= (isset($_POST['role']) && $_POST['role'] === 'faculty') ? 'selected' : '' ?>>Faculty</option>         
                            <option value="student" <?= (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : '' ?>>Student</option>     
                        </select> 
                    </div>  

                    <div class="form-group">     
                        <label for="user_id" id="user_id_label">User ID</label>     
                        <input type="text" name="user_id" id="user_id" placeholder="Enter your ID" required              
                               value="<?= isset($_POST['user_id']) ? htmlspecialchars($_POST['user_id']) : '' ?>"             
                               autocomplete="username"> 
                    </div>  

                    <div class="form-group"> 
                        <label for="password">Password</label> 
                        <div class="password-wrapper">     
                            <input type="password" name="password" id="password" placeholder="Enter your password" required         
                                   autocomplete="current-password">     
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">         
                                <span id="password-toggle-text">Show</span>     
                            </button> 
                        </div> 
                    </div>  

                    <div class="form-group">     
                        <button type="submit" class="btn">Sign In</button> 
                    </div> 
                </form>  

                <div class="info-section" style="text-align: center; padding: 1rem; margin-top: 1rem; background: #f3f4f6; border-radius: 8px;">
                    <p style="color: #6b7280; font-size: 0.875rem; margin: 0;">
                        📧 Need an account? Contact your administrator
                    </p>
                </div>
            </div> 
        </div> 
    </div>

    <script>
    function updateUserIdLabel() {
        const roleSelect = document.getElementById('role');
        const userIdLabel = document.getElementById('user_id_label');
        const userIdInput = document.getElementById('user_id');

        if (roleSelect.value === 'student') {
            userIdLabel.textContent = 'Student ID';
            userIdInput.placeholder = 'Enter your Student ID';
        } else if (roleSelect.value === 'admin' || roleSelect.value === 'faculty') {
            userIdLabel.textContent = 'Employee ID';
            userIdInput.placeholder = 'Enter your Employee ID';
        } else {
            userIdLabel.textContent = 'User ID';
            userIdInput.placeholder = 'Enter your ID';
        }
    }

    function togglePassword(fieldId) {
        const passwordField = document.getElementById(fieldId);
        const toggleText = document.getElementById(fieldId + '-toggle-text');

        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleText.textContent = 'Hide';
        } else {
            passwordField.type = 'password';
            toggleText.textContent = 'Show';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateUserIdLabel();
    });
    </script>
</body>
</html>