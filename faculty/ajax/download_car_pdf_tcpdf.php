<?php
/**
 * CAR PDF Generation using TCPDF
 * Alternative high-performance option
 * 
 * TCPDF Benefits:
 * - Fastest performance
 * - Smaller file sizes
 * - Best for complex layouts
 * - More direct control
 */

session_start();
require_once '../../config/db.php';
require_once '../../config/encryption.php';
require_once '../../vendor/autoload.php';

use TCPDF;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    die('Unauthorized');
}

if (!isset($_POST['class_code'])) {
    http_response_code(400);
    die('Missing class code');
}

$class_code = $_POST['class_code'];

try {
    // Generate HTML
    $_GET['class_code'] = $class_code;
    $_GET['pdf'] = '1';
    
    ob_start();
    include __DIR__ . '/generate_car_html.php';
    $response = ob_get_clean();
    
    $data = json_decode($response, true);
    $html = $data['html'];
    
    // Remove images for TCPDF compatibility
    $html = preg_replace('/<img[^>]+>/i', '', $html);
    
    // Create TCPDF instance
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set page orientation to landscape
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(10, 10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->AddPage('L', 'A4');
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Write HTML
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Download PDF
    $pdf->Output('CAR_' . $class_code . '.pdf', 'D');
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error generating PDF: ' . $e->getMessage());
}
?>
