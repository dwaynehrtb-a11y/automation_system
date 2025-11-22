<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session first
if (!isset($_SESSION)) {
session_start();
}

// Create simple CSRF functions if they don't exist
if (!function_exists('generateCSRFToken')) {
function generateCSRFToken() {
if (!isset($_SESSION['csrf_token'])) {
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
return $_SESSION['csrf_token'];
}
}

if (!function_exists('verifyCSRFToken')) {
function verifyCSRFToken($token) {
return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
}

if (!function_exists('getCSRFTokenInput')) {
function getCSRFTokenInput() {
$token = generateCSRFToken();
return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}
}

try {
require_once '../config/db.php';
} catch (Exception $e) {
die("Error loading database: " . $e->getMessage());
}

// Test database connection
if (!isset($conn) || $conn->connect_error) {
die("Database connection failed: " . ($conn->connect_error ?? 'Connection variable not found'));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
// CSRF validation
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
die("CSRF validation failed. Please refresh the page and try again.");
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = strtolower($_POST['role'] ?? '');
$employee_id = trim($_POST['employee_id'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validation
$errors = [];

if (empty($name)) $errors[] = "Please enter your full name";
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid email";
if (empty($role) || !in_array($role, ['faculty', 'admin'])) $errors[] = "Please select a valid role";
if (empty($employee_id)) $errors[] = "Please enter your Employee ID";
if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
if (!preg_match('/[A-Z]/', $password)) $errors[] = "Password must contain uppercase letter";
if (!preg_match('/[a-z]/', $password)) $errors[] = "Password must contain lowercase letter";
if (!preg_match('/[0-9]/', $password)) $errors[] = "Password must contain a number";
if ($password !== $confirm_password) $errors[] = "Passwords do not match";

if (!empty($errors)) {
$error_msg = implode("\\n", $errors);
echo "<script>
alert('Validation Errors:\\n$error_msg');
</script>";
} else {
try {
// Check for existing employee ID
$check_stmt = $conn->prepare("SELECT id FROM users WHERE employee_id = ?");
if (!$check_stmt) {
throw new Exception("Prepare failed: " . $conn->error);
}

$check_stmt->bind_param("s", $employee_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
echo "<script>alert('Employee ID already exists. Please use a different ID.');</script>";
$check_stmt->close();
} else {
$check_stmt->close();

// Check for existing email
$email_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if (!$email_stmt) {
throw new Exception("Prepare failed: " . $conn->error);
}

$email_stmt->bind_param("s", $email);
$email_stmt->execute();
$email_result = $email_stmt->get_result();

if ($email_result->num_rows > 0) {
echo "<script>alert('Email already exists. Please use a different email.');</script>";
$email_stmt->close();
} else {
$email_stmt->close();

// Hash password and insert user
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$insert_stmt = $conn->prepare("INSERT INTO users (name, email, employee_id, role, password, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
if (!$insert_stmt) {
throw new Exception("Prepare failed: " . $conn->error);
}

$insert_stmt->bind_param("sssss", $name, $email, $employee_id, $role, $hashed_password);

if ($insert_stmt->execute()) {
$user_id = $insert_stmt->insert_id;
error_log("User registered successfully: ID=$user_id, Name=$name, Email=$email, Role=$role");

echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
echo "<script>
document.addEventListener('DOMContentLoaded', function() {
Swal.fire({
title: '<span style=\"color: #059669; font-weight: 700; font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;\">Registration Successful!</span>',
html: `
<div style='padding: 1rem 0; font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;'>
<div style='margin-bottom: 2rem;'>
<div style='width: 80px; height: 80px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, #059669, #10b981); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(5, 150, 105, 0.3);'>
<span style='color: white; font-size: 2.5rem; font-weight: bold;'>✓</span>
</div>
<p style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; font-size: 1.1rem; color: #374151; margin-bottom: 1.5rem; line-height: 1.6;'>
Welcome to the <strong style='color: #2563eb;'>Academic Management System</strong>!<br>
Your account has been successfully created.
</p>
</div>

<div style='background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin: 1.5rem 0; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);'>
<h4 style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; color: #1e293b; margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; border-bottom: 2px solid #2563eb; padding-bottom: 0.5rem; display: inline-block;'>Account Details</h4>
<div style='text-align: left;'>
<div style='margin-bottom: 0.75rem; display: flex; align-items: center;'>
<span style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; color: #64748b; font-weight: 500; width: 100px; display: inline-block;'>Name:</span>
<span style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; color: #1e293b; font-weight: 600;'>{$name}</span>
</div>
<div style='margin-bottom: 0.75rem; display: flex; align-items: center;'>
<span style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; color: #64748b; font-weight: 500; width: 100px; display: inline-block;'>Role:</span>
<span style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; background: #2563eb; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600;'>" . ucfirst($role) . "</span>
</div>
<div style='display: flex; align-items: center;'>
<span style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; color: #64748b; font-weight: 500; width: 100px; display: inline-block;'>ID:</span>
<span style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; color: #1e293b; font-weight: 600; background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px;'>{$employee_id}</span>
</div>
</div>
</div>

<div style='background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 1rem; margin-top: 1.5rem;'>
<p style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; color: #92400e; font-size: 0.9rem; margin: 0; font-weight: 500;'>
<strong>Next Step:</strong> Use your Employee ID and password to log in to the system.
</p>
</div>
</div>
`,
confirmButtonText: '<span style=\"font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; font-weight: 600;\">Continue to Login</span>',
confirmButtonColor: '#2563eb',
allowOutsideClick: false,
allowEscapeKey: false,
width: '550px',
padding: '2rem',
backdrop: 'rgba(15, 23, 42, 0.75)',
customClass: {
popup: 'professional-popup',
confirmButton: 'professional-button'
}
}).then((result) => {
if (result.isConfirmed) {
// Professional loading animation
Swal.fire({
title: '<span style=\"font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; color: #2563eb;\">Redirecting...</span>',
html: '<div style=\"padding: 1rem;\"><p style=\"font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; color: #64748b; margin: 0;\">Taking you to the login page</p></div>',
allowOutsideClick: false,
allowEscapeKey: false,
showConfirmButton: false,
timer: 1500,
timerProgressBar: true,
width: '350px',
customClass: {
popup: 'loading-popup'
}
}).then(() => {
window.location.href = 'login.php';
});
}
});
});
</script>";

echo "<style>
.professional-popup {
border-radius: 16px !important;
box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15) !important;
border: 1px solid #e2e8f0 !important;
animation: professionalSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
}

.professional-button {
border-radius: 8px !important;
padding: 12px 24px !important;
font-size: 1rem !important;
box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3) !important;
transition: all 0.2s ease !important;
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
}

.professional-button:hover {
transform: translateY(-1px) !important;
box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4) !important;
}

.loading-popup {
border-radius: 12px !important;
animation: fadeInScale 0.3s ease-out !important;
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
}

@keyframes professionalSlideIn {
0% {
opacity: 0;
transform: translateY(-40px) scale(0.9);
}
100% {
opacity: 1;
transform: translateY(0) scale(1);
}
}

@keyframes fadeInScale {
0% {
opacity: 0;
transform: scale(0.9);
}
100% {
opacity: 1;
transform: scale(1);
}
}

.swal2-timer-progress-bar {
background: #2563eb !important;
}

/* Override SweetAlert default fonts */
.swal2-popup {
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
}
</style>";


$insert_stmt->close();
exit;
} else {
throw new Exception("Insert failed: " . $insert_stmt->error);
}
}
}
} catch (Exception $e) {
error_log("Registration error: " . $e->getMessage());
echo "<script>alert('Registration failed: " . addslashes($e->getMessage()) . "');</script>";
}
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Academic System - Register</title>
<style>
/* Professional Register Form CSS */

:root {
  /* Colors */
  --primary: #1e40af;
  --primary-dark: #1e3a8a;
  --primary-light: #dbeafe;
  --success: #10b981;
  --warning: #f59e0b;
  --danger: #ef4444;
  --white: #ffffff;
  
  /* Grays */
  --gray-50: #f9fafb;
  --gray-100: #f3f4f6;
  --gray-200: #e5e7eb;
  --gray-300: #d1d5db;
  --gray-400: #9ca3af;
  --gray-500: #6b7280;
  --gray-600: #4b5563;
  --gray-700: #374151;
  --gray-800: #1f2937;
  --gray-900: #111827;
  
  /* Shadows */
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  
  /* Radius */
  --radius: 0.375rem;
  --radius-md: 0.5rem;
  --radius-lg: 0.75rem;
  --radius-xl: 1rem;
  
  /* Transitions */
  --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-fast: all 0.1s cubic-bezier(0.4, 0, 0.2, 1);
  
  /* Typography */
  --font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  --line-height: 1.5;
  
  /* Layout */
  --container-width: 440px;
  --spacing: 1.5rem;
}

/* Reset */
*, *::before, *::after {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html {
  height: 100%;
  font-size: 16px;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

body {
  font-family: var(--font-family);
  font-size: 0.875rem;
  line-height: var(--line-height);
  color: var(--gray-900);
  background: linear-gradient(135deg, #1e40af 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

/* Container */
.register-container {
  background: var(--white);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-xl);
  width: 100%;
  max-width: var(--container-width);
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.1);
  animation: slideUp 0.3s ease-out;
}

.register-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--primary) 0%, #3b82f6 50%, var(--primary) 100%);
}

/* Header */
.register-header {
  text-align: center;
  padding: 1.5rem 2rem 1rem;
}

.logo {
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  width: 48px;
  height: 48px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
  color: var(--white);
  font-size: 1.25rem;
  font-weight: 600;
  box-shadow: var(--shadow-md);
}

h1 {
  color: var(--gray-900);
  margin-bottom: 0.25rem;
  font-size: 1.375rem;
  font-weight: 600;
  letter-spacing: -0.025em;
}

.subtitle {
  color: var(--gray-600);
  font-size: 0.875rem;
  font-weight: 400;
}

/* Form */
.register-form {
  padding: 0 2rem 1.5rem;
}

.form-group {
  margin-bottom: 1rem;
}

label {
  display: block;
  margin-bottom: 0.375rem;
  color: var(--gray-700);
  font-weight: 500;
  font-size: 0.875rem;
}

input, select {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid var(--gray-300);
  border-radius: var(--radius);
  font-size: 0.875rem;
  font-family: inherit;
  transition: var(--transition);
  background: var(--white);
  color: var(--gray-900);
}

input:focus, select:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
}

input:invalid {
  border-color: var(--danger);
}

/* Select dropdown */
select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L6 6L11 1' stroke='%236b7280' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  background-size: 12px;
  padding-right: 2.5rem;
  cursor: pointer;
}

select:focus {
  background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L6 6L11 1' stroke='%231e40af' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
}

/* Password field */
.password-wrapper {
  position: relative;
}

.password-toggle {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--gray-500);
  font-size: 0.75rem;
  padding: 0.25rem;
  border-radius: var(--radius);
  transition: var(--transition-fast);
  font-weight: 500;
}

.password-toggle:hover {
  color: var(--gray-700);
}

.password-toggle:focus {
  outline: none;
  color: var(--primary);
}

/* Buttons */
.btn {
  width: 100%;
  padding: 0.875rem 1.5rem;
  background: var(--primary);
  color: var(--white);
  border: none;
  border-radius: var(--radius);
  font-size: 0.875rem;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  transition: var(--transition);
  box-shadow: var(--shadow-sm);
  margin-top: 0.5rem;
}

.btn:hover {
  background: var(--primary-dark);
  box-shadow: var(--shadow);
}

.btn:active {
  transform: translateY(1px);
}

.btn:disabled {
  background: var(--gray-400);
  cursor: not-allowed;
  transform: none;
}

/* Back to login section */
.back-to-login {
  text-align: center;
  padding: 1rem 2rem 1.5rem;
  border-top: 1px solid var(--gray-200);
  background: var(--gray-50);
}

.btn-back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  text-decoration: none;
  background: var(--white);
  color: var(--primary);
  padding: 0.625rem 1rem;
  border-radius: var(--radius);
  border: 1px solid var(--primary);
  transition: var(--transition);
  font-weight: 500;
  font-size: 0.875rem;
  font-family: inherit;
}

.btn-back:hover {
  background: var(--primary);
  color: var(--white);
}

.btn-back:active {
  transform: translateY(1px);
}

/* Alerts */
.alert {
  padding: 0.875rem 1rem;
  border-radius: var(--radius);
  margin-bottom: 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-left: 3px solid;
}

.alert-error {
  background: rgba(239, 68, 68, 0.1);
  color: var(--danger);
  border-color: var(--danger);
}

.alert-success {
  background: rgba(16, 185, 129, 0.1);
  color: var(--success);
  border-color: var(--success);
}

.alert-warning {
  background: rgba(245, 158, 11, 0.1);
  color: #92400e;
  border-color: var(--warning);
}

/* Form validation */
.form-group.error input,
.form-group.error select {
  border-color: var(--danger);
  background-color: rgba(239, 68, 68, 0.05);
}

.form-group.success input,
.form-group.success select {
  border-color: var(--success);
}

.error-message {
  color: var(--danger);
  font-size: 0.75rem;
  margin-top: 0.25rem;
  font-weight: 500;
}

/* Animations */
@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive */
@media (max-width: 640px) {
  body {
    padding: 0.5rem;
  }
  
  .register-header {
    padding: 1.25rem 1.5rem 0.875rem;
  }
  
  .register-form {
    padding: 0 1.5rem 1.25rem;
  }
  
  .back-to-login {
    padding: 0.875rem 1.5rem 1.25rem;
  }
  
  h1 {
    font-size: 1.25rem;
  }
  
  .logo {
    width: 44px;
    height: 44px;
    font-size: 1.125rem;
  }
}

@media (max-width: 480px) {
  .register-header {
    padding: 1rem 1.25rem 0.75rem;
  }
  
  .register-form {
    padding: 0 1.25rem 1rem;
  }
  
  .back-to-login {
    padding: 0.75rem 1.25rem 1rem;
  }
  
  .form-group {
    margin-bottom: 0.875rem;
  }
  
  h1 {
    font-size: 1.125rem;
  }
  
  .logo {
    width: 40px;
    height: 40px;
    font-size: 1rem;
  }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

</style>
</head>
<body>
<div class="register-container">
<div class="register-header">
<div class="logo">🎓</div>
<h1>Academic System</h1>
<p class="subtitle">Create your account</p>
</div>

<form method="POST" class="register-form">
<?= getCSRFTokenInput() ?>

<div class="form-group">
<label for="role">Select Role</label>
<select name="role" id="role" required>
<option value="">-- Select Role --</option>
<option value="Faculty">Faculty</option>
<option value="Admin">Admin</option>
</select>
</div>

<div class="form-group">
<label for="name">Full Name</label>
<input type="text" name="name" id="name" required>
</div>

<div class="form-group">
<label for="email">Email</label>
<input type="email" name="email" id="email" required>
</div>

<div class="form-group">
<label for="employee_id">Employee ID</label>
<input type="text" name="employee_id" id="employee_id" required>
</div>

<div class="form-group">
<label for="password">Password</label>
<div class="password-wrapper">
<input type="password" name="password" id="password" required>
<button type="button" class="password-toggle" onclick="togglePassword('password')">
<span id="password-toggle-text">Show</span>
</button>
</div>
<small style="color: var(--gray-600); font-size: 0.75rem;">
Must be 8+ characters with uppercase, lowercase, and number
</small>
</div>

<div class="form-group">
<label for="confirm_password">Confirm Password</label>
<div class="password-wrapper">
<input type="password" name="confirm_password" id="confirm_password" required>
<button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
<span id="confirm_password-toggle-text">Show</span>
</button>
</div>
</div>

<div class="form-group">
<button type="submit" class="btn">Create Account</button>
</div>
</form>

<div class="back-to-login">
<a href="login.php" class="btn-back">← Back to Login</a>
</div>
</div>
<script>
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
</script>
</body>
</html>