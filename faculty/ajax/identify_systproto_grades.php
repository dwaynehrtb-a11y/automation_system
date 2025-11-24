<?php
require_once '../../config/db.php';

echo "=== System Prototype Grade Cleanup ===\n\n";

// Find System Prototype column ID
$result = $conn->query("
    SELECT gcc.id FROM grading_component_columns gcc
    JOIN grading_components gc ON gc.id = gcc.component_id
    WHERE gc.component_name = 'System Prototype'
");

$col_ids = [];
while ($row = $result->fetch_assoc()) {
    $col_ids[] = $row['id'];
}

echo "System Prototype column IDs: " . implode(', ', $col_ids) . "\n\n";

// Check which students in class 24_T2_CCPRGG1L_INF222 have System Prototype grades
$in_list = implode(',', $col_ids);
$result = $conn->query("
    SELECT sfg.id, sfg.student_id, sfg.raw_score
    FROM student_flexible_grades sfg
    WHERE sfg.column_id IN ($in_list)
      AND sfg.student_id IN (
        SELECT ce.student_id FROM class_enrollments ce
        WHERE ce.class_code = '24_T2_CCPRGG1L_INF222' AND ce.status = 'enrolled'
      )
");

echo "System Prototype grades to delete for class INF222:\n";
$to_delete = [];
while ($row = $result->fetch_assoc()) {
    echo "- Grade ID {$row['id']}: Student {$row['student_id']} score {$row['raw_score']}\n";
    $to_delete[] = $row['id'];
}

if (!empty($to_delete)) {
    echo "\nThese grades should be deleted because:\n";
    echo "1. You don't use System Prototype in your class\n";
    echo "2. They're inflating the CO3 summary in your COA\n\n";
    
    echo "Command to delete (manually):\n";
    echo "DELETE FROM student_flexible_grades WHERE id IN (" . implode(',', $to_delete) . ");\n";
}
?>
