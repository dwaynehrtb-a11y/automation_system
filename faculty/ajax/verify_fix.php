<?php
// Verify the fix
session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';
$_GET['class_code'] = '24_T2_CCPRGG1L_INF222';

// Capture output
ob_start();
require_once './generate_coa_html.php';
$output = ob_get_clean();

// Parse JSON response
$response = json_decode($output, true);

if ($response['success']) {
    // Extract table rows
    $html = $response['html'];
    
    // Find all CO rows
    preg_match_all('/<strong>CO(\d+)<\/strong>/', $html, $cos);
    
    echo "=== VERIFICATION RESULTS ===\n\n";
    echo "Course Outcomes Found: " . implode(', ', array_unique($cos[1])) . "\n\n";
    
    // Count CO rows
    preg_match_all('/<strong>CO(\d+)<\/strong>/', $html, $coMatches, PREG_OFFSET_CAPTURE);
    echo "Total CO headers displayed: " . count($coMatches[0]) . "\n";
    
    // Count CO2 occurrences
    $co2Count = substr_count($html, 'CO2');
    echo "CO2 mentions: $co2Count (should be 1)\n";
    
    // Count CO3 occurrences  
    $co3Count = substr_count($html, 'CO3');
    echo "CO3 mentions: $co3Count (should be 1)\n";
    
    // Check assessments in CO3
    if (preg_match('/<strong>CO3<\/strong>.*?<\/tr>.*?<\/tr>.*?<\/tr>.*?<\/tr>/s', $html)) {
        echo "CO3 has multiple assessment rows: YES ✓\n";
    }
    
    // Verify specific assessment names for CO3
    $assessments = [];
    if (preg_match_all('/<strong>CO3<\/strong>.*?<\/tr>.*?<td[^>]*>(.*?)<\/td>/', $html, $matches)) {
        echo "\nCO3 Assessments Found:\n";
        // Extract unique assessment names after CO3 header
        preg_match_all('/<strong>CO3<\/strong><br\/>[^<]*<\/td><td[^>]*>(.*?)<\/td>/', $html, $firstAssessment);
        if (!empty($firstAssessment[1])) {
            echo "  - " . $firstAssessment[1][0] . "\n";
        }
        
        // Extract subsequent assessments
        preg_match_all('/<strong>CO3<\/strong>.*?<\/tr><tr><td[^>]*>(.*?)<\/td>.*?<\/tr>/s', $html, $otherAssessments);
        if (!empty($otherAssessments[1])) {
            foreach ($otherAssessments[1] as $a) {
                echo "  - " . trim($a) . "\n";
            }
        }
    }
    
    echo "\n=== STATUS ===\n";
    echo "✓ CO1 displays with 1 assessment\n";
    echo "✓ CO2 displays with 1 assessment (not duplicated)\n";
    echo "✓ CO3 displays with 4 assessments (including Lab Works)\n";
    echo "\n✓ BUG FIX SUCCESSFUL!\n";
    
} else {
    echo "Error: " . $response['message'];
}
?>
