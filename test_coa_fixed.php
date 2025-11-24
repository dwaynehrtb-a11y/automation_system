<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

$classCode = '25_T2_CTAPROJ1_INF223';
$courseCode = 'CTAPROJ1';

echo "=== Fixed COA query (with gcc.id IS NOT NULL) ===\n";
$query = "SELECT 
    co.co_number,
    co.co_description,
    gc.component_name,
    gcc.performance_target,
    COUNT(DISTINCT CASE 
        WHEN sfg.raw_score >= (gcc.performance_target / 100 * gcc.max_score) 
        THEN sfg.student_id 
    END) AS students_met_target,
    COUNT(DISTINCT sfg.student_id) AS total_students
FROM course_outcomes co
LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR))
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
INNER JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
INNER JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id
WHERE co.course_code = ?
    AND ce.status = 'enrolled'
    AND gcc.id IS NOT NULL
GROUP BY co.co_number, co.co_description, gc.id, gcc.id, gcc.performance_target, gcc.max_score
ORDER BY co.co_number, gc.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $classCode, $courseCode);
$stmt->execute();
$result = $stmt->get_result();

echo "Rows returned:\n";
while($row = $result->fetch_assoc()) {
    $success_rate = $row['total_students'] > 0 ? ($row['students_met_target'] / $row['total_students'] * 100) : 0;
    echo "CO{$row['co_number']}: {$row['component_name']} - Met: {$row['students_met_target']}/{$row['total_students']} ({$success_rate}%)\n";
}
$stmt->close();

$conn->close();
?>
