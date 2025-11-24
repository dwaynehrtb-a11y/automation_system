<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

$classCode = '25_T2_CTAPROJ1_INF223';
$courseCode = 'CTAPROJ1';

echo "=== Check course_outcomes ===\n";
$result = $conn->query("SELECT * FROM course_outcomes WHERE course_code='$courseCode'");
while($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

echo "\n=== Check grading_components for this class ===\n";
$result = $conn->query("SELECT id, component_name FROM grading_components WHERE class_code='$classCode'");
while($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

echo "\n=== Check grading_component_columns with co_mappings ===\n";
$result = $conn->query("SELECT id, component_id, column_name, co_mappings FROM grading_component_columns WHERE component_id IN (SELECT id FROM grading_components WHERE class_code='$classCode')");
while($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Component: {$row['component_id']}, Column: {$row['column_name']}, CO_Mappings: " . $row['co_mappings'] . "\n";
}

echo "\n=== Test JSON_CONTAINS for CO 1 ===\n";
$result = $conn->query("SELECT gcc.id, gcc.column_name, gcc.co_mappings FROM grading_component_columns gcc WHERE gcc.component_id IN (SELECT id FROM grading_components WHERE class_code='$classCode') AND JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE('1'))");
echo "Results: " . $result->num_rows . " rows\n";
while($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

$conn->close();
?>
