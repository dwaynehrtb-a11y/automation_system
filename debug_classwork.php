<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

$classCode = '25_T2_CTAPROJ1_INF223';

echo "=== All grading_component_columns with full details ===\n";
$result = $conn->query("SELECT gcc.id, gcc.component_id, gcc.column_name, gcc.co_mappings, gc.component_name 
FROM grading_component_columns gcc
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
WHERE gc.class_code='$classCode'
ORDER BY gcc.component_id, gcc.id");

while($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Component: {$row['component_name']}, Column: {$row['column_name']}, CO_Mappings: {$row['co_mappings']}\n";
}

echo "\n=== Check for duplicate Classwork items ===\n";
$result = $conn->query("SELECT COUNT(*) as cnt FROM grading_component_columns gcc
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
WHERE gc.class_code='$classCode' AND gc.component_name='Classwork'");
$row = $result->fetch_assoc();
echo "Total Classwork columns: " . $row['cnt'] . "\n";

echo "\n=== Detailed Classwork columns ===\n";
$result = $conn->query("SELECT gcc.id, gcc.column_name, gcc.co_mappings FROM grading_component_columns gcc
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
WHERE gc.class_code='$classCode' AND gc.component_name='Classwork'");
while($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Column: {$row['column_name']}, CO_Mappings: {$row['co_mappings']}\n";
}

$conn->close();
?>
