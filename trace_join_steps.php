<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');

echo "=== Step 1: course_outcomes rows ===\n";
$result = $conn->query("SELECT co_id, co_number FROM course_outcomes WHERE course_code='CTAPROJ1'");
while($row = $result->fetch_assoc()) {
  echo "CO$row[co_number]\n";
}

echo "\n=== Step 2: INNER JOIN result (CO2 only) ===\n";
$result = $conn->query("
SELECT co.co_number, gcc.id, gcc.column_name, gcc.co_mappings
FROM course_outcomes co
INNER JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR))
WHERE co.course_code='CTAPROJ1' AND co.co_number=2
");

echo "Rows: " . $result->num_rows . "\n";
if ($result->num_rows == 0) {
  echo "NO rows found - correct!\n";
} else {
  while($row = $result->fetch_assoc()) {
    echo "CO$row[co_number]: Column $row[id] ($row[column_name]), mappings: $row[co_mappings]\n";
  }
}

echo "\n=== Step 3: Add grading_components LEFT JOIN (CO2 only) ===\n";
$result = $conn->query("
SELECT co.co_number, gcc.id, gcc.column_name, gcc.co_mappings, gc.component_name, gc.class_code
FROM course_outcomes co
INNER JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR))
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
WHERE co.course_code='CTAPROJ1' AND co.co_number=2 AND gc.component_name='Classwork' AND gc.class_code='25_T2_CTAPROJ1_INF223'
");

echo "Rows: " . $result->num_rows . "\n";
if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    echo "Found: {$row['component_name']} at class {$row['class_code']}, mappings: {$row['co_mappings']}\n";
  }
}

echo "\n=== Step 4: Full query trace for class 25_T2_CTAPROJ1_INF223 ===\n";
$result = $conn->query("
SELECT DISTINCT
    co.co_number,
    gc.component_name,
    COUNT(*) as cnt
FROM course_outcomes co
INNER JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR))
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
LEFT JOIN class_enrollments ce ON ce.class_code = '25_T2_CTAPROJ1_INF223' AND ce.student_id = sfg.student_id
WHERE co.course_code='CTAPROJ1'
  AND gc.class_code = '25_T2_CTAPROJ1_INF223'
GROUP BY co.co_number, gc.component_name
ORDER BY co.co_number
");

echo "Results:\n";
while($row = $result->fetch_assoc()) {
  echo "CO{$row['co_number']}: {$row['component_name']} (cnt: {$row['cnt']})\n";
}

$conn->close();
?>
