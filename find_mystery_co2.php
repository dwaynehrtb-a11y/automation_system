<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');

echo "=== DIRECT: Classwork columns with CO2 mapping ===\n";
$result = $conn->query("
SELECT gcc.id, gcc.column_name, gcc.co_mappings, gcc.component_id, gc.component_name, gc.class_code 
FROM grading_component_columns gcc 
LEFT JOIN grading_components gc ON gc.id = gcc.component_id 
WHERE gcc.co_mappings LIKE '%2%'
  AND gc.component_name = 'Classwork' 
  AND gc.class_code = '25_T2_CTAPROJ1_INF223'
");

if (!$result) {
  echo "Query error: " . $conn->error . "\n";
  exit;
}

echo "Rows found: " . $result->num_rows . "\n";
while($row = $result->fetch_assoc()) {
  echo "ID {$row['id']}: {$row['column_name']}, mappings: {$row['co_mappings']}\n";
}

echo "\n=== What student grades exist for CO2 Classwork ===\n";
$result = $conn->query("
SELECT 
  gcc.id as col_id,
  gcc.column_name,
  gc.component_name,
  sfg.student_id,
  sfg.raw_score,
  gcc.max_score,
  gcc.performance_target
FROM grading_component_columns gcc 
LEFT JOIN grading_components gc ON gc.id = gcc.component_id 
INNER JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
WHERE gcc.co_mappings LIKE '%2%'
  AND gc.component_name = 'Classwork' 
  AND gc.class_code = '25_T2_CTAPROJ1_INF223'
ORDER BY gcc.id, sfg.student_id
");

if (!$result) {
  echo "Query error: " . $conn->error . "\n";
  exit;
}

echo "Grade rows found: " . $result->num_rows . "\n";
if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $target = ($row['performance_target'] / 100) * $row['max_score'];
    $meets = $row['raw_score'] >= $target ? 'YES' : 'NO';
    echo "Col {$row['col_id']} ({$row['column_name']}): Student {$row['student_id']}, Score {$row['raw_score']}/{$row['max_score']} (target {$target}): $meets\n";
  }
} else {
  echo "No records found\n";
}

$conn->close();
?>
