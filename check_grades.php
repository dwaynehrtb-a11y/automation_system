<?php
require_once 'config/db.php';

// Check all columns for this class
$query = "
    SELECT gcc.id, gcc.name, gc.id as component_id, gc.class_code, COUNT(sfg.id) as grade_count
    FROM grading_component_columns gcc
    JOIN grading_components gc ON gcc.component_id = gc.id
    LEFT JOIN student_flexible_grades sfg ON gcc.id = sfg.column_id
    WHERE gc.class_code = '24_T2_CCPRGG1L_INF222'
    GROUP BY gcc.id
    ORDER BY gc.id, gcc.order_index
";

$result = $conn->query($query);
echo "Columns for 24_T2_CCPRGG1L_INF222:\n";
while ($row = $result->fetch_assoc()) {
    echo "  ID: {$row['id']}, Name: '{$row['name']}', Grades: {$row['grade_count']}\n";
}

// Check sample grade values
echo "\nSample grades:\n";
$sampleQuery = "
    SELECT sfg.id, sfg.student_id, sfg.column_id, sfg.raw_score, gcc.name
    FROM student_flexible_grades sfg
    JOIN grading_component_columns gcc ON sfg.column_id = gcc.id
    WHERE gcc.component_id IN (SELECT id FROM grading_components WHERE class_code = '24_T2_CCPRGG1L_INF222')
    LIMIT 20
";

$sampleResult = $conn->query($sampleQuery);
while ($row = $sampleResult->fetch_assoc()) {
    echo "  Column '{$row['name']}' (ID: {$row['column_id']}), Student: {$row['student_id']}, Score: {$row['raw_score']}\n";
}

$conn->close();
?>