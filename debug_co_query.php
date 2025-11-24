<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');

echo "=== Course Outcomes for CTAPROJ1 ===\n";
$result = $conn->query("SELECT co_id, co_number, co_description, course_code FROM course_outcomes WHERE course_code = 'CTAPROJ1' ORDER BY co_number");

while($row = $result->fetch_assoc()) {
  echo "CO_ID {$row['co_id']}: CO{$row['co_number']}: {$row['co_description']}\n";
}

echo "\n=== Trace: What grading_component_columns match CO2 ===\n";
$result = $conn->query("
SELECT gcc.id, gcc.column_name, gcc.co_mappings, gc.component_name, gc.class_code
FROM grading_component_columns gcc
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
WHERE JSON_CONTAINS(gcc.co_mappings, '2')
  AND gc.class_code = '25_T2_CTAPROJ1_INF223'
ORDER BY gcc.id
");

if ($result->num_rows == 0) {
  echo "NO grading_component_columns match CO2 for this class\n";
} else {
  while($row = $result->fetch_assoc()) {
    echo "ID {$row['id']}: {$row['column_name']} ({$row['component_name']}), mappings: {$row['co_mappings']}\n";
  }
}

$conn->close();
?>
