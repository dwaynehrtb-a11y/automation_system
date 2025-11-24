<?php
// Simulate a fresh browser request to the endpoint
session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';

$_GET['class_code'] = '24_T2_CCPRGG1L_INF222';

// Now include the endpoint
ob_start();
include __DIR__ . '/faculty/ajax/generate_coa_html.php';
$output = ob_get_clean();

// Just show the status
$data = json_decode($output, true);
if ($data['success']) {
    echo "SUCCESS\n";
    echo "HTML Length: " . strlen($data['html']) . " bytes\n";
    echo "Contains NU LIPA: " . (strpos($data['html'], 'NU LIPA') ? 'YES' : 'NO') . "\n";
    echo "Contains assessment table: " . (strpos($data['html'], 'ASSESSMENT PERFORMANCE') ? 'YES' : 'NO') . "\n";
} else {
    echo "FAILED: " . $data['message'] . "\n";
}
?>
