<?php
require_once 'config/db.php';

$stmt = $conn->prepare("SELECT teaching_strategies FROM car_data WHERE car_id = 3");
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if($data) {
    $strategies = $data['teaching_strategies'];
    $lines = explode("\n", $strategies);
    
    echo "Total lines: " . count($lines) . "\n\n";
    
    foreach($lines as $i => $line) {
        $line = trim($line);
        if(empty($line)) continue;
        
        echo "Line " . ($i+1) . ":\n";
        echo "Raw: " . $line . "\n";
        
        // Try to split it
        $parts = preg_split('/[–—-]/', $line, 2);
        
        echo "Parts count: " . count($parts) . "\n";
        if(count($parts) > 1) {
            echo "Part 1: " . trim($parts[0]) . "\n";
            echo "Part 2: " . substr(trim($parts[1]), 0, 100) . "...\n";
        }
        echo "\n";
    }
}
?>
