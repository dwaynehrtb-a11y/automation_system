<?php
session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';
$_GET['class_code'] = '24_T2_CCPRGG1L_INF222';

ob_start();
include 'generate_coa_html.php';
$output = ob_get_clean();

$data = json_decode($output, true);
if ($data['success']) {
    $html = $data['html'];
    
    // Extract just the table rows
    if (preg_match('/<table class="assessment-table"[^>]*>(.*?)<\/table>/s', $html, $matches)) {
        $table = $matches[1];
        
        // Find all data rows (not headers)
        $rows = [];
        if (preg_match_all('/<tr>.*?<\/tr>/s', $table, $row_matches)) {
            $rows = $row_matches[0];
        }
        
        echo "=== Assessment Table Analysis ===\n";
        echo "Total <tr> elements: " . count($rows) . "\n\n";
        
        $dataRowNum = 0;
        foreach ($rows as $idx => $row) {
            // Extract CO number if present
            preg_match('/CO(\d+)/i', $row, $co_match);
            preg_match('/>([^<]*?Assessment[^<]*?)<\/td>/i', $row, $assess_match);
            
            if ($co_match || $assess_match) {
                $dataRowNum++;
                $co = $co_match[1] ?? 'N/A';
                $assessment = $assess_match[1] ?? 'N/A';
                
                echo "Data Row $dataRowNum: CO=$co, Assessment=$assessment\n";
                
                // Show preview of the row
                $preview = substr(strip_tags($row), 0, 100);
                echo "  Preview: $preview...\n\n";
            }
        }
    }
} else {
    echo "Error: " . $data['message'];
}
?>
