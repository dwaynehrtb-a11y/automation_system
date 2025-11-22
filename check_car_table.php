<?php
require_once 'config/db.php';

// Get table structure
echo "=== CAR_DATA TABLE STRUCTURE ===\n";
$result = $conn->query('DESCRIBE car_data');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Null'] . "\n";
}

echo "\n=== SAMPLE CAR_DATA RECORD ===\n";
$stmt = $conn->prepare("SELECT * FROM car_data LIMIT 1");
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
if($data) {
    echo "Car ID: " . $data['car_id'] . "\n";
    echo "Teaching Strategies Length: " . strlen($data['teaching_strategies']) . " chars\n";
    echo "Teaching Strategies Preview:\n";
    echo substr($data['teaching_strategies'], 0, 200) . "...\n";
}
?>
