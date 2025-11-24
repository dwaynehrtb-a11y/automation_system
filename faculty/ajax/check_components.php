<?php
require_once '../../config/db.php';

$class_code = '24_T2_CCPRGG1L_INF222';

echo "=== Grading Components for Class ===\n\n";

$stmt = $conn->prepare("
    SELECT gcc.id, gcc.component_id, gc.component_name, gcc.co_mappings 
    FROM grading_component_columns gcc 
    JOIN grading_components gc ON gc.id = gcc.component_id 
    WHERE gcc.class_code = ? 
    ORDER BY gc.component_name
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

$components = [];
while($row = $result->fetch_assoc()) {
    $components[] = $row;
    $co_mappings = json_decode($row['co_mappings'], true);
    echo "Component: " . $row['component_name'] . "\n";
    echo "  Column ID: " . $row['id'] . "\n";
    echo "  CO Mappings: " . implode(', ', $co_mappings) . "\n";
    echo "\n";
}

if (empty($components)) {
    echo "No grading components found for this class.\n";
}

echo "\n=== Components Mapped to CO3 ===\n";
foreach ($components as $comp) {
    $co_mappings = json_decode($comp['co_mappings'], true);
    if (in_array('3', $co_mappings)) {
        echo "- " . $comp['component_name'] . "\n";
    }
}
?>
