<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

echo "=== Get a sample class ===\n";
$result = $conn->query('SELECT DISTINCT class_code FROM class_enrollments LIMIT 1');
if (!$result) die("Query error: " . $conn->error);
$class = $result->fetch_assoc();
$classCode = $class['class_code'];
echo "Using class: $classCode\n";

echo "\n=== Test the grade distribution query ===\n";
$query = "SELECT ce.student_id,
    ROUND(
        (COALESCE(AVG(CASE WHEN sfg.raw_score IS NOT NULL THEN (COALESCE(sfg.raw_score,0)/gcc.max_score*100) END), 0)) / 25
    , 2) as calculated_grade
FROM class_enrollments ce
LEFT JOIN grading_components gc ON gc.class_code=ce.class_code
LEFT JOIN grading_component_columns gcc ON gc.id=gcc.component_id
LEFT JOIN student_flexible_grades sfg ON gcc.id=sfg.column_id AND ce.student_id=sfg.student_id
WHERE ce.class_code='$classCode' AND ce.status='enrolled'
GROUP BY ce.student_id";

$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Rows returned: " . $result->num_rows . "\n";
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

$conn->close();
?>
