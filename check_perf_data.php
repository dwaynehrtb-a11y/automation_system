<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');

// Check the actual performance data being retrieved
$query = "
SELECT 
    co.co_number,
    co.co_description,
    gc.component_name AS assessment_name,
    gcc.performance_target,
    gcc.max_score,
    COUNT(DISTINCT CASE 
        WHEN sfg.raw_score >= (gcc.performance_target / 100 * gcc.max_score) 
        THEN sfg.student_id 
    END) AS students_met_target,
    COUNT(DISTINCT sfg.student_id) AS total_students
FROM course_outcomes co
LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
LEFT JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id
WHERE co.course_code = ?
      AND ce.status = 'enrolled'
GROUP BY co.co_number, co.co_description, gc.id, gcc.id, gcc.performance_target, gcc.max_score
ORDER BY co.co_number, gc.id
";

$stmt = $conn->prepare($query);
$class_code = '24_T2_CCPRGG1L_INF222';
$course_code = 'CCPRGG1L';
$stmt->bind_param("ss", $class_code, $course_code);
$stmt->execute();
$result = $stmt->get_result();

echo "Raw query results:\n";
while($row = $result->fetch_assoc()) {
    echo "CO{$row['co_number']}: {$row['assessment_name']} - Met: {$row['students_met_target']}, Total: {$row['total_students']}\n";
}
?>
