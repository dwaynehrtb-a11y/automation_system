<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

echo "=== All Classwork/Quiz CO Mappings ===\n";
$result = $conn->query("SELECT gcc.id, gcc.column_name, gcc.co_mappings, gc.component_name 
FROM grading_component_columns gcc 
LEFT JOIN grading_components gc ON gc.id = gcc.component_id 
WHERE gcc.component_id IN (SELECT id FROM grading_components WHERE component_name IN ('Classwork', 'Quiz') AND class_code='25_T2_CTAPROJ1_INF223')
ORDER BY gc.component_name, gcc.column_name");

while($row = $result->fetch_assoc()) {
  echo "{$row['component_name']} - {$row['column_name']}: {$row['co_mappings']}\n";
}

echo "\n=== Database check for CO2 actual mappings ===\n";
$result = $conn->query("SELECT gcc.id, gcc.column_name, gcc.co_mappings, gc.component_name 
FROM grading_component_columns gcc 
LEFT JOIN grading_components gc ON gc.id = gcc.component_id 
WHERE gcc.component_id IN (SELECT id FROM grading_components WHERE class_code='25_T2_CTAPROJ1_INF223')
  AND JSON_CONTAINS(gcc.co_mappings, CAST(2 AS CHAR))");

while($row = $result->fetch_assoc()) {
  echo "{$row['component_name']} - {$row['column_name']}: {$row['co_mappings']}\n";
}

$conn->close();
?>
