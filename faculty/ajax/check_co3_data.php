<?php
require_once '../../config/db.php';

$class_code = '24_T2_CCPRGG1L_INF222';
$course_code = 'CCPRGG1L';

echo "=== Checking CO3 Components for this Class ===\n\n";

// First, get all CO3 components
$stmt = $conn->prepare("
    SELECT gcc.id, gc.component_name, COUNT(DISTINCT sfg.student_id) as student_count
    FROM grading_component_columns gcc
    JOIN grading_components gc ON gc.id = gcc.component_id
    LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
    WHERE gcc.co_mappings LIKE '%\"3\"%' OR gcc.co_mappings LIKE '%3%'
    GROUP BY gcc.id, gc.component_name
    ORDER BY gc.component_name
");

$stmt->execute();
$result = $stmt->get_result();

echo "CO3 Components (globally):\n";
while ($row = $result->fetch_assoc()) {
    echo "- {$row['component_name']} (Column ID: {$row['id']}) - {$row['student_count']} students with grades\n";
}

echo "\n=== Now checking which CO3 components have grades in THIS CLASS ===\n\n";

// Check which components have actual grades for students in this class
$stmt = $conn->prepare("
    SELECT DISTINCT gcc.id, gcc.component_id, gc.component_name, 
           COUNT(DISTINCT sfg.student_id) as student_count
    FROM grading_component_columns gcc
    JOIN grading_components gc ON gc.id = gcc.component_id
    JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
    JOIN class_enrollments ce ON ce.student_id = sfg.student_id
    WHERE gcc.co_mappings LIKE '%\"3\"%' 
      AND ce.class_code = ?
      AND ce.status = 'enrolled'
    GROUP BY gcc.id, gc.component_name
    ORDER BY gc.component_name
");

$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "CO3 Components with grades in $class_code:\n";
$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    echo "- {$row['component_name']} (Column ID: {$row['id']}) - {$row['student_count']} students\n";
}

if ($count == 0) {
    echo "None found! This means CO3 components don't have grades assigned yet.\n";
}

echo "\n=== Checking System Prototype specifically ===\n\n";

$stmt = $conn->prepare("
    SELECT gcc.id, gcc.co_mappings, COUNT(DISTINCT sfg.student_id) as grade_count
    FROM grading_component_columns gcc
    LEFT JOIN grading_components gc ON gc.id = gcc.component_id
    LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
    WHERE gc.component_name = 'System prototype'
    GROUP BY gcc.id
");

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $co_mappings = json_decode($row['co_mappings']);
    echo "System prototype:\n";
    echo "  Column ID: {$row['id']}\n";
    echo "  CO Mappings: " . json_encode($co_mappings) . "\n";
    echo "  Total grades in all classes: {$row['grade_count']}\n";
    echo "  Has data: " . ($row['grade_count'] > 0 ? 'YES' : 'NO') . "\n";
}
?>
