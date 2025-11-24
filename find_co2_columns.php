<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

echo "=== All Columns with CO2 Mapping ===\n";
$result = $conn->query("SELECT gcc.id, gcc.column_name, gcc.co_mappings, gc.component_name, gc.class_code
FROM grading_component_columns gcc 
LEFT JOIN grading_components gc ON gc.id = gcc.component_id 
WHERE gcc.co_mappings LIKE '%2%'
ORDER BY gc.component_name, gcc.column_name
LIMIT 30");

while($row = $result->fetch_assoc()) {
  echo "ID {$row['id']}: {$row['component_name']} - {$row['column_name']} ({$row['class_code']}): {$row['co_mappings']}\n";
}

echo "\n=== Specifically for 25_T2_CTAPROJ1_INF223 ===\n";
$result = $conn->query("SELECT gcc.id, gcc.column_name, gcc.co_mappings, gc.component_name
FROM grading_component_columns gcc 
LEFT JOIN grading_components gc ON gc.id = gcc.component_id 
WHERE gc.class_code = '25_T2_CTAPROJ1_INF223'
  AND gcc.co_mappings LIKE '%2%'");

if ($result->num_rows == 0) {
  echo "NO columns with CO2 mapping found for this class!\n";
} else {
  while($row = $result->fetch_assoc()) {
    echo "ID {$row['id']}: {$row['component_name']} - {$row['column_name']}: {$row['co_mappings']}\n";
  }
}

$conn->close();
?>
