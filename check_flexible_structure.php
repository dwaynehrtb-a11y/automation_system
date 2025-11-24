<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

echo "=== grading_components table structure ===\n";
$result = $conn->query("DESC grading_components");
while($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

echo "\n=== grading_component_columns table structure ===\n";
$result = $conn->query("DESC grading_component_columns");
while($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

echo "\n=== Sample grading_components data ===\n";
$result = $conn->query("SELECT * FROM grading_components WHERE class_code='25_T2_CTAPROJ1_INF223' LIMIT 5");
while($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

echo "\n=== Sample student_flexible_grades ===\n";
$result = $conn->query("SELECT sfg.*, gcc.component_id FROM student_flexible_grades sfg
LEFT JOIN grading_component_columns gcc ON gcc.id=sfg.column_id
LIMIT 10");
while($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

$conn->close();
?>
