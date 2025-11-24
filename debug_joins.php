<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

echo "=== Debug: Check student_flexible_grades for Classwork ===\n";
$query = "SELECT gcc.id, gcc.column_name, gcc.co_mappings, COUNT(sfg.id) as grade_count
FROM grading_component_columns gcc
LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
WHERE gcc.component_id IN (SELECT id FROM grading_components WHERE component_name='Classwork' AND class_code='25_T2_CTAPROJ1_INF223')
GROUP BY gcc.id";

$result = $conn->query($query);
while($row = $result->fetch_assoc()) {
    echo "Column ID {$row['id']}: {$row['column_name']}, Mapped to: {$row['co_mappings']}, Grades: {$row['grade_count']}\n";
}

echo "\n=== Debug: Trace through full join ===\n";
$classCode = '25_T2_CTAPROJ1_INF223';
$courseCode = 'CTAPROJ1';

$query = "SELECT 
    co.co_number,
    gcc.id as gcc_id,
    gcc.column_name,
    gcc.co_mappings,
    gc.component_name,
    COUNT(DISTINCT sfg.student_id) as students
FROM course_outcomes co
LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR))
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
WHERE co.course_code = ? 
    AND gc.class_code = ?
GROUP BY co.co_number, gcc.id
ORDER BY co.co_number, gc.component_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $courseCode, $classCode);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    echo "CO{$row['co_number']}: Column {$row['column_name']} ({$row['component_name']}), Mappings: {$row['co_mappings']}, Students: {$row['students']}\n";
}
$stmt->close();

$conn->close();
?>
