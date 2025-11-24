<?php
require_once '../../config/db.php';

echo "=== All Grading Component Columns (any class) ===\n\n";

$stmt = $conn->prepare("
    SELECT gcc.id, gcc.class_code, gcc.component_id, gc.component_name, gcc.co_mappings 
    FROM grading_component_columns gcc 
    JOIN grading_components gc ON gc.id = gcc.component_id 
    ORDER BY gcc.class_code, gc.component_name
");
$stmt->execute();
$result = $stmt->get_result();

$count = 0;
while($row = $result->fetch_assoc()) {
    $count++;
    $co_mappings = json_decode($row['co_mappings'], true);
    echo "[$count] Class: {$row['class_code']} | Component: {$row['component_name']} | COs: " . implode(',', $co_mappings) . "\n";
}

echo "\n=== Total records: $count ===\n\n";

echo "=== Records for class 24_T2_CCPRGG1L_INF222 ===\n";
$stmt = $conn->prepare("
    SELECT gcc.id, gc.component_name, gcc.co_mappings 
    FROM grading_component_columns gcc 
    JOIN grading_components gc ON gc.id = gcc.component_id 
    WHERE gcc.class_code = '24_T2_CCPRGG1L_INF222'
");
$stmt->execute();
$result = $stmt->get_result();

$count = $result->num_rows;
echo "Found: $count records\n";

if ($count == 0) {
    echo "\nNone - This class has no grading component columns assigned!\n";
    
    echo "\n=== Checking course_code ===\n";
    $stmt = $conn->prepare("
        SELECT gcc.id, gcc.class_code, gcc.component_id, gc.component_name, gcc.co_mappings 
        FROM grading_component_columns gcc 
        JOIN grading_components gc ON gc.id = gcc.component_id 
        WHERE gcc.course_code = 'CCPRGG1L'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        echo "Found by course_code: " . $row['component_name'] . " (class_code: {$row['class_code']})\n";
    }
}
?>
