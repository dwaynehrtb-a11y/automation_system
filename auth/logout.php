<?php


require_once '../config/session.php';

// Check if user is logged in before logging out
if (isAuthenticated()) {
// Log the logout action for audit trail
$current_user = getCurrentUser();


}

// Use the secure session destroy function
destroySession();

// Clear any additional cookies if they exist
if (isset($_COOKIE['remember_token'])) {
setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Prevent caching of this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page with success message
header("Location: http://localhost/automation_system/auth/login.php?success=");
exit();
?>