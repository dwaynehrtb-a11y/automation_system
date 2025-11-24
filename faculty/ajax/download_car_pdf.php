<?php
/**
 * CAR PDF Download Endpoint
 * Returns CAR HTML for client-side PDF generation using html2canvas + jsPDF
 */

// Disable all output before session start
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../config/db.php';
require_once '../../config/encryption.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['class_code'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing class code']);
    exit;
}

$class_code = $_POST['class_code'];

// Log for debugging
error_log("CAR PDF Request for class: $class_code");

try {
    // Clear any previous output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Generate CAR HTML
    $_GET['class_code'] = $class_code;
    $_GET['pdf'] = '1';
    
    // The included file will handle all output and exit
    include __DIR__ . '/generate_car_html.php';
    
    // If we get here, something went wrong
    error_log("CAR generation did not exit properly");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'CAR generation incomplete']);
    
} catch (Exception $e) {
    error_log("CAR PDF Exception: " . $e->getMessage());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>