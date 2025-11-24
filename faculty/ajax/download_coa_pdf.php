<?php
/**
 * COA PDF Download Endpoint
 * Returns COA HTML for client-side PDF generation using html2canvas + jsPDF
 */

session_start();
require_once '../../config/db.php';

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
    $_GET['class_code'] = $class_code;
    
    ob_start();
    include __DIR__ . '/generate_coa_html.php';
    $response = ob_get_clean();
    
    echo $response;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
