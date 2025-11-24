<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

$classCode = '25_T2_CTAPROJ1_INF223';
$courseCode = 'CTAPROJ1';

echo "=== Test JSON_CONTAINS with integer (no JSON_QUOTE) ===\n";
$result = $conn->query("SELECT gcc.id, gcc.column_name, gcc.co_mappings, co.co_number 
FROM grading_component_columns gcc 
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
LEFT JOIN course_outcomes co ON JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR))
WHERE gcc.component_id IN (SELECT id FROM grading_components WHERE class_code='$classCode')
AND co.course_code='$courseCode'
GROUP BY gcc.id, co.co_number");

echo "Results: " . $result->num_rows . " rows\n";
while($row = $result->fetch_assoc()) {
    echo "Column: {$row['column_name']}, CO: {$row['co_number']}, Mappings: {$row['co_mappings']}\n";
}

echo "\n=== Full COA query test ===\n";
$query = "SELECT 
    co.co_number,
    co.co_description,
    gc.component_name,
    COUNT(DISTINCT CASE 
        WHEN sfg.raw_score >= 60
        THEN sfg.student_id 
    END) AS students_met_target,
    COUNT(DISTINCT sfg.student_id) AS total_students
FROM course_outcomes co
LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR))
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
INNER JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
INNER JOIN class_enrollments ce ON ce.class_code = '$classCode' AND ce.student_id = sfg.student_id
WHERE co.course_code = '$courseCode'
    AND ce.status = 'enrolled'
GROUP BY co.co_number, gc.id
ORDER BY co.co_number, gc.id";

$result = $conn->query($query);
if (!$result) {
    echo "Query error: " . $conn->error . "\n";
} else {
    echo "Results: " . $result->num_rows . " rows\n";
    while($row = $result->fetch_assoc()) {
        echo "CO{$row['co_number']}: {$row['component_name']} - Met: {$row['students_met_target']}/{$row['total_students']}\n";
    }
}

$conn->close();
?>
