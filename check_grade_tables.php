<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

$classCode = '25_T2_CCPRGG1L_INF223';

echo "=== All tables ===\n";
$result = $conn->query("SHOW TABLES");
while($row = $result->fetch_array()) {
    echo $row[0] . "\n";
}

echo "\n=== Check grade_term table ===\n";
$query = "SELECT COUNT(*) as cnt FROM grade_term WHERE class_code='$classCode'";
$result = $conn->query($query);
$row = $result->fetch_assoc();
echo "grade_term records: " . $row['cnt'] . "\n";

echo "\n=== Sample from grade_term ===\n";
$query = "SELECT student_id, term_grade, grade_status FROM grade_term WHERE class_code='$classCode' LIMIT 5";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Check grading_component_grades table ===\n";
$query = "SELECT COUNT(*) as cnt FROM grading_component_grades WHERE class_code='$classCode'";
$result = $conn->query($query);
$row = $result->fetch_assoc();
echo "grading_component_grades records: " . $row['cnt'] . "\n";

echo "\n=== Sample from grading_component_grades ===\n";
$query = "SELECT student_id, column_id, grade FROM grading_component_grades WHERE class_code='$classCode' LIMIT 5";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Found: " . $result->num_rows . " records\n";
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

$conn->close();
?>
