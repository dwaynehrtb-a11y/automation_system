<?php
// Test endpoint response directly
session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';

require_once __DIR__ . '/config/db.php';

$class_code = '24_T2_CCPRGG1L_INF222';

// Directly include and call the endpoint logic
ob_start();

// We'll simulate the endpoint call
$_GET['class_code'] = $class_code;

// Mock the endpoint without exit
try {
    require_once __DIR__ . '/faculty/ajax/generate_coa_html.php';
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$response = ob_get_clean();

// Parse the JSON response
$data = json_decode($response, true);

if ($data && $data['success']) {
    echo "=== COA GENERATION SUCCESSFUL ===\n\n";
    echo "Success: YES\n";
    echo "Class Code: " . ($data['class_code'] ?? 'N/A') . "\n";
    echo "HTML Length: " . strlen($data['html'] ?? '') . " bytes\n";
    
    $html = $data['html'];
    
    echo "\n=== HTML STRUCTURE ===\n";
    echo "Contains DOCTYPE: " . (strpos($html, '<!DOCTYPE') !== false ? 'YES' : 'NO') . "\n";
    echo "Contains NU LIPA: " . (strpos($html, 'NU LIPA') !== false ? 'YES' : 'NO') . "\n";
    echo "Contains COURSE INFORMATION: " . (strpos($html, 'COURSE INFORMATION') !== false ? 'YES' : 'NO') . "\n";
    echo "Tables count: " . substr_count($html, '<table') . "\n";
    echo "Table rows count: " . substr_count($html, '<tr>') . "\n";
    
    // Save HTML
    file_put_contents(__DIR__ . '/test_coa_output.html', $html);
    echo "\n✓ HTML saved to test_coa_output.html\n";
    
    // Show first 800 chars
    echo "\n=== FIRST 800 CHARACTERS ===\n";
    echo substr($html, 0, 800) . "\n...\n";
} else {
    echo "Error: " . ($data['message'] ?? json_encode($data)) . "\n";
}
?>
