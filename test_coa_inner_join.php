<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

echo "=== Testing FIXED COA Query ===\n";
$classCode = '25_T2_CTAPROJ1_INF223';
$courseCode = 'CTAPROJ1';

$query = "SELECT 
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
           INNER JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR))
           LEFT JOIN grading_components gc ON gc.id = gcc.component_id
           INNER JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
           INNER JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id
           WHERE co.course_code = ?
                AND ce.status = 'enrolled'
           GROUP BY co.co_number, co.co_description, gc.id, gcc.id, gcc.performance_target, gcc.max_score
           ORDER BY co.co_number, gc.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $classCode, $courseCode);
$stmt->execute();
$result = $stmt->get_result();

$lastCO = null;
while($row = $result->fetch_assoc()) {
    if ($row['co_number'] != $lastCO) {
        echo "\n=== CO{$row['co_number']}: {$row['co_description']} ===\n";
        $lastCO = $row['co_number'];
    }
    echo "  {$row['assessment_name']}: {$row['students_met_target']}/{$row['total_students']} ({$row['success_rate']}%)\n";
}

$stmt->close();
$conn->close();
?>
