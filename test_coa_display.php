<?php
session_start();

// Simulate faculty session
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';

// Required includes
require_once __DIR__ . '/config/db.php';

// Get test data
$class_code = '24_T2_CCPRGG1L_INF222';

// Make request to endpoint
$url = 'http://localhost/automation_system/faculty/ajax/generate_coa_html.php?class_code=' . urlencode($class_code);

$opts = [
    'http' => [
        'method' => 'GET',
        'header' => 'Cookie: PHPSESSID=' . session_id() . "\r\n"
    ]
];

$context = stream_context_create($opts);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo "Error fetching COA endpoint\n";
    exit;
}

echo "=== COA ENDPOINT RESPONSE ===\n\n";

$data = json_decode($response, true);

if (!$data) {
    echo "Invalid JSON response:\n";
    echo $response . "\n";
    exit;
}

echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
echo "Class Code: " . ($data['class_code'] ?? 'N/A') . "\n";
echo "HTML Length: " . strlen($data['html'] ?? '') . " bytes\n";

if ($data['success']) {
    // Check HTML structure
    $html = $data['html'];
    
    echo "\n=== HTML STRUCTURE CHECK ===\n";
    echo "Contains doctype: " . (strpos($html, '<!DOCTYPE') !== false ? 'YES' : 'NO') . "\n";
    echo "Contains NU LIPA: " . (strpos($html, 'NU LIPA') !== false ? 'YES' : 'NO') . "\n";
    echo "Contains COURSE INFORMATION: " . (strpos($html, 'COURSE INFORMATION') !== false ? 'YES' : 'NO') . "\n";
    echo "Contains table elements: " . (substr_count($html, '<table') > 0 ? 'YES (' . substr_count($html, '<table') . ' tables)' : 'NO') . "\n";
    echo "Contains assessment rows: " . (substr_count($html, '<tr>') > 5 ? 'YES (' . substr_count($html, '<tr>') . ' rows)' : 'NO') . "\n";
    
    // Save HTML for debugging
    file_put_contents(__DIR__ . '/test_coa_output.html', $html);
    echo "\n✓ HTML saved to test_coa_output.html\n";
    
    // Print first 1000 chars
    echo "\n=== FIRST 1000 CHARACTERS ===\n";
    echo substr($html, 0, 1000) . "\n...\n";
} else {
    echo "\nError: " . ($data['message'] ?? 'Unknown error') . "\n";
}
?>
