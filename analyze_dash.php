<?php
require_once 'config/db.php';

// Get the exact bytes of the teaching strategies field
$stmt = $conn->prepare("SELECT teaching_strategies FROM car_data WHERE car_id = 3");
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if($data) {
    $strategies = $data['teaching_strategies'];
    
    // Get first line
    $lines = explode("\n", $strategies);
    $firstLine = $lines[0];
    
    echo "First line: " . $firstLine . "\n\n";
    
    // Find dash positions and show character codes
    echo "Character analysis:\n";
    for($i = 0; $i < strlen($firstLine); $i++) {
        $char = $firstLine[$i];
        $ord = ord($char);
        if($ord < 32 || $ord > 126) {
            // Show special characters
            echo "Position $i: " . bin2hex($char) . " (ord: $ord)\n";
        }
    }
    
    // Try to find the dash
    echo "\n\nSearching for dashes:\n";
    if(strpos($firstLine, '–') !== false) {
        echo "Found EN-DASH (–): U+2013\n";
    }
    if(strpos($firstLine, '—') !== false) {
        echo "Found EM-DASH (—): U+2014\n";
    }
    if(strpos($firstLine, '-') !== false) {
        echo "Found HYPHEN (-): U+002D\n";
    }
    if(strpos($firstLine, '–') === false && strpos($firstLine, '—') === false && strpos($firstLine, '-') === false) {
        echo "NO DASH FOUND!\n";
        echo "Raw hex dump of full first line:\n";
        echo bin2hex($firstLine) . "\n";
    }
}
?>
