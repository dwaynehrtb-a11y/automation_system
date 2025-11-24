<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

$classCode = '25_T2_CCPRGG1L_INF223';

echo "=== Check student_grades table structure ===\n";
$result = $conn->query("DESC student_grades");
if (!$result) die("Query error: " . $conn->error);
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Sample from student_grades ===\n";
$query = "SELECT * FROM student_grades WHERE class_code='$classCode' LIMIT 5";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Found: " . $result->num_rows . " records\n";
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Check car_data table ===\n";
$result = $conn->query("DESC car_data");
if (!$result) die("Query error: " . $conn->error);
while($row = $result->fetch_assoc()) { 
    echo $row['Field'] . "\n"; 
}

echo "\n=== Sample from car_data ===\n";
$query = "SELECT class_code, student_id FROM car_data WHERE class_code='$classCode' LIMIT 5";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Found: " . $result->num_rows . " records\n";
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

$conn->close();
?>
