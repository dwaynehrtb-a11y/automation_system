<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

$classCode = '25_T2_CCPRGG1L_INF223';

echo "=== Grading components for this class ===\n";
$query = "SELECT id, class_code, component_name FROM grading_components WHERE class_code='$classCode'";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Component columns (linked to components) ===\n";
$query = "SELECT gcc.id, gcc.component_id, gcc.column_name, gcc.max_score
FROM grading_component_columns gcc
LEFT JOIN grading_components gc ON gc.id=gcc.component_id
WHERE gc.class_code='$classCode'
LIMIT 5";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Sample student grades for a specific column ===\n";
$query = "SELECT student_id, column_id, raw_score 
FROM student_flexible_grades 
WHERE column_id IN (
    SELECT gcc.id FROM grading_component_columns gcc
    LEFT JOIN grading_components gc ON gc.id=gcc.component_id
    WHERE gc.class_code='$classCode'
)
LIMIT 10";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Found: " . $result->num_rows . " records\n";
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

$conn->close();
?>
