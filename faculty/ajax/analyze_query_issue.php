<?php
require_once '../../config/db.php';

echo "=== Understanding the COA Query Issue ===\n\n";

// The query does a LEFT JOIN to class_enrollments
// This means even if there's no matching enrollment record, it still returns the row with NULL class_code

echo "System Prototype grades with their class enrollment status:\n\n";

$result = $conn->query("
    SELECT sfg.student_id, sfg.raw_score, 
           ce.class_code, ce.status,
           gcc.id, gcc.co_mappings
    FROM student_flexible_grades sfg
    LEFT JOIN class_enrollments ce ON ce.student_id = sfg.student_id AND ce.class_code = '24_T2_CCPRGG1L_INF222'
    JOIN grading_component_columns gcc ON gcc.id = sfg.column_id
    WHERE sfg.column_id = 138
");

while ($row = $result->fetch_assoc()) {
    echo "Student: {$row['student_id']} | Score: {$row['raw_score']} | Enrolled in INF222: " . 
         ($row['class_code'] ? 'YES (' . $row['status'] . ')' : 'NO (NULL)') . "\n";
}

echo "\n=== The Problem ===\n";
echo "The COA query does:\n";
echo "  LEFT JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id\n\n";
echo "With WHERE clause:\n";
echo "  WHERE co.course_code = ? AND ce.status = 'enrolled'\n\n";
echo "Since the LEFT JOIN returns NULL for students not in class_enrollments,\n";
echo "the WHERE clause 'ce.status = enrolled' should filter them out.\n\n";
echo "But maybe there's an issue with the filtering...\n\n";

// Let me run the EXACT query from generate_coa_html.php
echo "=== Running the Exact COA Query ===\n\n";

$coPerfQuery = "SELECT 
    co.co_number,
    co.co_description,
    gc.component_name AS assessment_name,
    gcc.performance_target, gcc.max_score,
    COUNT(DISTINCT CASE WHEN sfg.raw_score >= (gcc.performance_target / 100 * gcc.max_score) THEN sfg.student_id END) AS students_met_target,
    COUNT(DISTINCT sfg.student_id) AS total_students
FROM course_outcomes co
LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
LEFT JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id
WHERE co.course_code = ?
      AND ce.status = 'enrolled'
GROUP BY co.co_number, co.co_description, gc.id, gcc.id, gcc.performance_target, gcc.max_score
ORDER BY co.co_number, gc.id";

$stmt = $conn->prepare($coPerfQuery);
$class_code = '24_T2_CCPRGG1L_INF222';
$course_code = 'CCPRGG1L';
$stmt->bind_param('ss', $class_code, $course_code);
$stmt->execute();
$result = $stmt->get_result();

echo "Results:\n";
while ($row = $result->fetch_assoc()) {
    echo "CO{$row['co_number']} | {$row['assessment_name']} | Met: {$row['students_met_target']} | Total: {$row['total_students']}\n";
}
?>
