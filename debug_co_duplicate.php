<?php
require_once __DIR__ . '/config/db.php';

// Query the actual performance data like the COA does
$class_code = '24_T2_CCPRGG1L_INF222';
$sql = "SELECT course_code FROM class_enrollments WHERE class_code = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();
$class = $result->fetch_assoc();
$stmt->close();

if (!$class) {
    die("Class not found\n");
}

$course_code = $class['course_code'];

// Run the exact same query as COA
$coPerfQuery = "SELECT 
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
               ORDER BY co.co_number, gc.id";

$stmt = $conn->prepare($coPerfQuery);
$stmt->bind_param("ss", $class_code, $course_code);
$stmt->execute();
$result = $stmt->get_result();

echo "Raw Query Results:\n";
echo str_repeat("-", 80) . "\n";

$current_co = null;
while ($row = $result->fetch_assoc()) {
    $co = $row['co_number'];
    if ($co !== $current_co) {
        echo "\nCO{$co}: {$row['co_description']}\n";
        $current_co = $co;
    }
    echo "  {$row['assessment_name']}: Met={$row['students_met_target']}, Total={$row['total_students']}, Target={$row['performance_target']}\n";
}

$stmt->close();
?>
