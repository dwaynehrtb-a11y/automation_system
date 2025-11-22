<?php
// Start session first
session_start();

// Set JSON header FIRST before any includes
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// Prevent HTML output
ob_start();

// Test the response
$response = [
    'success' => true,
    'test' => 'API is working',
    'session_user' => $_SESSION['user_id'] ?? null,
    'session_role' => $_SESSION['role'] ?? null,
    'post_data' => $_POST
];

echo json_encode($response);
ob_end_flush();
?>
