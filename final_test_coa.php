<?php
session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';
$_GET['class_code'] = '24_T2_CCPRGG1L_INF222';

ob_start();
include 'faculty/ajax/generate_coa_html.php';
$output = ob_get_clean();

$data = json_decode($output, true);
if ($data === null) {
    echo "JSON Parse Error: " . json_last_error_msg() . "\n";
    echo "Output:\n" . $output . "\n";
} else {
    echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    if (!$data['success']) {
        echo "Error: " . $data['message'] . "\n";
    } else {
        echo "HTML Generated: " . strlen($data['html']) . " bytes\n";
        echo "First 500 chars:\n" . substr($data['html'], 0, 500) . "\n";
    }
}
?>
