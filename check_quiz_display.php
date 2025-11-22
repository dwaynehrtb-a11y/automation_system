<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'automation_system');
$result = $conn->query("SELECT sfg.student_id, sfg.column_id, sfg.raw_score, gcc.column_name, gcc.max_score FROM student_flexible_grades sfg JOIN grading_component_columns gcc ON sfg.column_id = gcc.id WHERE sfg.class_code = '24_T2_CCPRGG1L_INF222' AND gcc.component_id = 48 ORDER BY sfg.student_id, sfg.column_id");
while($row = $result->fetch_assoc()) {
  echo json_encode($row) . "\n";
}
?>
