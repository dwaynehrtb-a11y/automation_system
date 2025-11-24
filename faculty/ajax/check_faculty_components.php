<?php
require_once '../../config/db.php';

$faculty_id = 114;
$class_code = '24_T2_CCPRGG1L_INF222';

echo "=== Components YOU (Faculty 114) created for your classes ===\n\n";

// Check which grading_components were created by this faculty
$stmt = $conn->prepare("
    SELECT gc.id, gc.component_name, gc.created_by
    FROM grading_components gc
    WHERE gc.created_by = ? OR gc.created_by IS NULL
    ORDER BY gc.component_name
");
$stmt->bind_param('i', $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

echo "All components possibly belonging to faculty 114:\n";
while ($row = $result->fetch_assoc()) {
    echo "- ID {$row['id']}: {$row['component_name']} (created_by: {$row['created_by']})\n";
}

echo "\n=== Checking which components are used in your class ===\n\n";

// Try a different approach - check which components have columns with grades for THIS class's students
$stmt = $conn->prepare("
    SELECT DISTINCT gc.id, gc.component_name
    FROM grading_component_columns gcc
    JOIN grading_components gc ON gc.id = gcc.component_id
    JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
    JOIN class_enrollments ce ON ce.student_id = sfg.student_id AND ce.class_code = ?
    WHERE ce.status = 'enrolled'
    ORDER BY gc.component_name
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "Components with grades for enrolled students in $class_code:\n";
while ($row = $result->fetch_assoc()) {
    echo "- {$row['component_name']}\n";
}

echo "\n=== Checking the grading_component_columns structure ===\n\n";

// Maybe there's a faculty_id or class assignment field
$stmt = $conn->prepare("DESC grading_component_columns");
$stmt->execute();
$result = $stmt->get_result();

echo "Table columns:\n";
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
