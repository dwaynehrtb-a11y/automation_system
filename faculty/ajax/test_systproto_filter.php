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
    
    // Extract all CO3 assessments from the HTML
    preg_match_all('/<strong>CO3<\/strong>.*?rowspan="(\d+)"/', $html, $rowspan);
    
    if (!empty($rowspan[1])) {
        $co3_rows = intval($rowspan[1][0]);
        echo "CO3 Assessment Rows (rowspan value): $co3_rows\n\n";
    }
    
    // Extract assessment names for CO3
    preg_match('/<strong>CO3<\/strong>.*?<\/tr>(.*?)<\/tr>.*?<\/tr>.*?<\/tr>.*?<\/table>/s', $html, $co3_section);
    
    if (strpos($html, 'System prototype') !== false) {
        echo "❌ System Prototype is STILL showing\n";
    } else {
        echo "✅ System Prototype is HIDDEN (GOOD!)\n";
    }
    
    echo "\nCO3 Components visible:\n";
    $assessments = ['Laboratory exam', 'Mock defense', 'Lab works', 'System prototype'];
    foreach ($assessments as $assess) {
        if (strpos($html, strtolower($assess)) !== false) {
            echo "  ✓ $assess\n";
        } else {
            echo "  ✗ $assess\n";
        }
    }
} else {
    echo "Error: " . $data['message'];
}
?>
