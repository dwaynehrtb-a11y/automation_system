<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

echo "=== Classes with student_grades data ===\n";
$query = "SELECT DISTINCT sg.class_code, COUNT(*) as grade_count
FROM student_grades sg
GROUP BY sg.class_code
LIMIT 10";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Found: " . $result->num_rows . " classes with grade data\n";
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Classes with grade_term data ===\n";
$query = "SELECT DISTINCT class_code, COUNT(*) as grade_count
FROM grade_term
GROUP BY class_code
LIMIT 10";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Found: " . $result->num_rows . " classes with grade_term data\n";
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Classes with student_flexible_grades data ===\n";
$query = "SELECT gcc.id, gc.class_code, COUNT(sfg.id) as count
FROM grading_component_columns gcc
LEFT JOIN grading_components gc ON gc.id=gcc.component_id
LEFT JOIN student_flexible_grades sfg ON sfg.column_id=gcc.id
WHERE sfg.id IS NOT NULL
GROUP BY gc.class_code
LIMIT 10";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Found: " . $result->num_rows . " classes with flexible grade data\n";
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

$conn->close();
?>
