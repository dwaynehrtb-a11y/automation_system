<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

echo "=== grading_component_columns columns ===\n";
$result = $conn->query('DESC grading_component_columns');
if (!$result) die("Query error: " . $conn->error);
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Sample student grades ===\n";
$result = $conn->query('SELECT student_id, column_id, raw_score FROM student_flexible_grades LIMIT 5');
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Get a sample class and check its data ===\n";
$result = $conn->query('SELECT DISTINCT class_code FROM class_enrollments LIMIT 1');
$class = $result->fetch_assoc();
$classCode = $class['class_code'];
echo "Using class: $classCode\n";

echo "\n=== Component columns for this class ===\n";
$result = $conn->query("SELECT id, column_name, max_score FROM grading_component_columns WHERE class_code='$classCode' LIMIT 5");
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Enrollments for this class ===\n";
$result = $conn->query("SELECT student_id, status FROM class_enrollments WHERE class_code='$classCode' LIMIT 3");
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Check if any student_flexible_grades exist for this class ===\n";
$result = $conn->query("SELECT COUNT(*) as cnt FROM student_flexible_grades sfg
JOIN grading_component_columns gcc ON gcc.id = sfg.column_id
WHERE gcc.class_code='$classCode'");
$row = $result->fetch_assoc();
echo "Records found: " . $row['cnt'] . "\n";

$conn->close();
?>
