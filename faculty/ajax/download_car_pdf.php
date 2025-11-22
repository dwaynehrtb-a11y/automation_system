<?php
/**
 * CAR PDF Download Endpoint
 * Returns CAR HTML for client-side PDF generation using html2canvas + jsPDF
 * 
 * Client-side benefits:
 * - No server-side PDF library needed
 * - Custom layouts easy to control
 * - Encrypted grades displayed correctly
 * - Better performance
 */

session_start();
require_once '../../config/db.php';
require_once '../../config/encryption.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['class_code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing class code']);
    exit;
}

$class_code = $_POST['class_code'];

try {
    // Generate CAR HTML
    $_GET['class_code'] = $class_code;
    $_GET['pdf'] = '1';
    
    ob_start();
    include __DIR__ . '/generate_car_html.php';
    $response = ob_get_clean();
    
    // Response is already JSON, just return it
    echo $response;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>