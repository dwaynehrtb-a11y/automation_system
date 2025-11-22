<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'automation_system');

echo "=== Checking grade_term table ===\n\n";

$result = $conn->query("SELECT * FROM grade_term WHERE class_code = '24_T2_CCPRGG1L_INF222'");
while($row = $result->fetch_assoc()) {
  echo json_encode($row, JSON_PRETTY_PRINT) . "\n\n";
}
?>
