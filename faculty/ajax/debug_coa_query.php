<?php
session_start();
require_once '../../config/db.php';

$class_code = '24_T2_CCPRGG1L_INF222';
$course_code = 'CCPRGG1L';

echo "=== COA DEBUG OUTPUT ===\n\n";

// Run the exact query from generate_coa_html.php
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
                COUNT(DISTINCT sfg.student_id) AS total_students,
                COALESCE(ROUND(
                    COUNT(DISTINCT CASE 
                        WHEN sfg.raw_score >= (gcc.performance_target / 100 * gcc.max_score) 
                        THEN sfg.student_id 
                    END) * 100.0 / NULLIF(COUNT(DISTINCT sfg.student_id), 0),
                    2
                ), 0) AS success_rate
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

$rows = [];
while($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

echo "Total rows returned: " . count($rows) . "\n\n";

// Display rows grouped by CO
$coGroups = [];
foreach ($rows as $row) {
    $co_num = $row['co_number'];
    if (!isset($coGroups[$co_num])) {
        $coGroups[$co_num] = [];
    }
    $coGroups[$co_num][] = $row;
}

foreach ($coGroups as $co_num => $rows) {
    echo "CO{$co_num}: " . $rows[0]['co_description'] . "\n";
    echo "  Rows for this CO: " . count($rows) . "\n";
    foreach ($rows as $row) {
        echo "    - Assessment: {$row['assessment_name']}, Met: {$row['students_met_target']}/{$row['total_students']}, Success: {$row['success_rate']}%\n";
    }
    echo "\n";
}

echo "\n=== UNIQUE COs IN DATABASE ===\n";
$coQuery = "SELECT co_number, co_description FROM course_outcomes WHERE course_code = ? ORDER BY co_number";
$stmt = $conn->prepare($coQuery);
$stmt->bind_param("s", $course_code);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    echo "CO" . $row['co_number'] . ": " . $row['co_description'] . "\n";
}
$stmt->close();

echo "\n=== GRADING COLUMNS WITH CO MAPPINGS ===\n";
$colQuery = "SELECT gcc.id, gcc.column_name, gc.component_name, gcc.co_mappings FROM grading_component_columns gcc
             JOIN grading_components gc ON gc.id = gcc.component_id
             WHERE gc.class_code = ?
             ORDER BY gcc.co_mappings";
$stmt = $conn->prepare($colQuery);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    echo "Col ID {$row['id']}: {$row['component_name']} - {$row['column_name']} -> COs: {$row['co_mappings']}\n";
}
$stmt->close();
?>
