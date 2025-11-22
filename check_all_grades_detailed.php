<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'automation_system');

echo "=== Checking ALL components for class 24_T2_CCPRGG1L_INF222 ===\n\n";

$result = $conn->query("SELECT id, component_name, percentage FROM grading_components WHERE class_code = '24_T2_CCPRGG1L_INF222'");
while($comp = $result->fetch_assoc()) {
  echo "Component {$comp['id']}: {$comp['component_name']} ({$comp['percentage']}%)\n";
}

echo "\n=== Checking RAW_SCORES for ALL students/columns ===\n\n";

$result = $conn->query("
SELECT 
  sfg.student_id,
  gcc.component_id,
  gcc.id as column_id,
  gcc.column_name,
  gcc.max_score,
  sfg.raw_score
FROM student_flexible_grades sfg
JOIN grading_component_columns gcc ON sfg.column_id = gcc.id
WHERE sfg.class_code = '24_T2_CCPRGG1L_INF222'
ORDER BY gcc.component_id, sfg.student_id, gcc.id
");

$prevComponent = null;
while($row = $result->fetch_assoc()) {
  if ($prevComponent != $row['component_id']) {
    $prevComponent = $row['component_id'];
    echo "\n--- Component {$row['component_id']} ---\n";
  }
  echo "  {$row['student_id']} | {$row['column_name']} | raw_score={$row['raw_score']} | max_score={$row['max_score']}\n";
}
?>
