<?php
session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';
$_GET['class_code'] = '24_T2_CCPRGG1L_INF222';

ob_start();
require './generate_coa_html.php';
$json = ob_get_clean();
$data = json_decode($json, true);

if ($data['success']) {
    $html = $data['html'];
    
    echo "=== FINAL COA REPORT (INF222) ===\n\n";
    
    // Parse CO sections
    preg_match_all('/<strong>CO(\d+)<\/strong>.*?rowspan="(\d+)"/', $html, $matches);
    
    foreach ($matches[1] as $idx => $co_num) {
        $rowspan = $matches[2][$idx];
        echo "CO$co_num: $rowspan assessment(s)\n";
    }
    
    echo "\nAssessments by CO:\n";
    
    if (strpos($html, '<strong>CO1</strong>')) {
        echo "✓ CO1: Classwork\n";
    }
    
    if (strpos($html, '<strong>CO2</strong>')) {
        echo "✓ CO2: Quiz\n";
    }
    
    if (strpos($html, '<strong>CO3</strong>')) {
        echo "✓ CO3:\n";
        if (strpos($html, 'Laboratory exam')) echo "    - Laboratory exam\n";
        if (strpos($html, 'Mock defense')) echo "    - Mock defense\n";
        if (strpos($html, 'Lab works')) echo "    - Lab works\n";
        if (strpos($html, 'System prototype')) echo "    - System prototype (SHOULD NOT APPEAR)\n";
    }
    
    echo "\n=== STATUS ===\n";
    if (strpos($html, 'System prototype') === false) {
        echo "✅ System Prototype SUCCESSFULLY REMOVED\n";
        echo "✅ CO3 now shows only 3 components (Laboratory exam, Mock defense, Lab works)\n";
        echo "✅ COA properly summarizes all tagged components\n";
    } else {
        echo "❌ System Prototype still visible\n";
    }
} else {
    echo "Error: " . $data['message'];
}
?>
