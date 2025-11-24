<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

echo "=== Classes with course_code=CTAPROJ1 ===\n";
$result = $conn->query("SELECT class_code, course_code FROM class WHERE course_code='CTAPROJ1' ORDER BY class_code");
while($row = $result->fetch_assoc()) {
  echo "Class: {$row['class_code']}\n";
}

echo "\n=== Checking columns for each class ===\n";
$result = $conn->query("SELECT gc.class_code, gcc.id, gcc.column_name, gcc.co_mappings, gc.component_name
FROM grading_component_columns gcc 
LEFT JOIN grading_components gc ON gc.id = gcc.component_id 
WHERE gc.class_code IN (SELECT class_code FROM class WHERE course_code='CTAPROJ1')
ORDER BY gc.class_code, gc.component_name, gcc.column_name");

if (!$result) {
  echo "Query error: " . $conn->error . "\n";
} else {
  while($row = $result->fetch_assoc()) {
    echo "{$row['class_code']}: {$row['component_name']} - {$row['column_name']}: {$row['co_mappings']}\n";
  }
}

$conn->close();
?>
