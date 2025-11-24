<?php
// Clear existing log
@unlink('../../logs/coa_debug.log');

session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';
$_GET['class_code'] = '24_T2_CCPRGG1L_INF222';

ob_start();
include 'generate_coa_html.php';
$output = ob_get_clean();

// Read log file
echo "=== DEBUG LOG ===\n";
$logFile = '../../logs/coa_debug.log';
if (file_exists($logFile)) {
    $log = file_get_contents($logFile);
    echo $log;
} else {
    echo "Log file not found\n";
}

echo "\n\n=== HTML OUTPUT ANALYSIS ===\n";
$data = json_decode($output, true);
if ($data['success']) {
    $html = $data['html'];
    
    // Find all CO rows
    if (preg_match_all('/<strong>(CO\d+)<\/strong>/', $html, $matches)) {
        echo "COs found in output: " . implode(', ', $matches[1]) . "\n";
    } else {
        echo "No COs found\n";
    }
} else {
    echo "Error: " . $data['message'];
}
?>
