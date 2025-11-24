<?php
require_once '../../config/db.php';

echo "=== System Prototype Grades Check ===\n\n";

// Get the System Prototype column ID
$stmt = $conn->prepare("
    SELECT gcc.id
    FROM grading_component_columns gcc
    JOIN grading_components gc ON gc.id = gcc.component_id
    WHERE gc.component_name = 'System Prototype'
    LIMIT 1
");
$stmt->execute();
$col_result = $stmt->get_result()->fetch_assoc();
$sys_proto_col_id = $col_result['id'];

echo "System Prototype Column ID: $sys_proto_col_id\n\n";

// Get all grades for this column
$stmt = $conn->prepare("
    SELECT sfg.id, sfg.student_id, sfg.column_id, sfg.raw_score, 
           s.name, ce.class_code
    FROM student_flexible_grades sfg
    JOIN students s ON s.id = sfg.student_id
    LEFT JOIN class_enrollments ce ON ce.student_id = sfg.student_id 
    WHERE sfg.column_id = ?
    ORDER BY ce.class_code
");
$stmt->bind_param('i', $sys_proto_col_id);
$stmt->execute();
$result = $stmt->get_result();

echo "All grades for System Prototype column:\n";
while ($row = $result->fetch_assoc()) {
    echo "Student: {$row['name']} | Class: " . ($row['class_code'] ?? 'NOT ENROLLED') . " | Score: {$row['raw_score']}\n";
}

echo "\n=== Checking if user 114 is assigned to class 24_T2_CCPRGG1L_INF222 ===\n\n";

$class_code = '24_T2_CCPRGG1L_INF222';
$user_id = 114;

$stmt = $conn->prepare("
    SELECT fc.id, fc.faculty_id, fc.class_code, c.course_title, ce.class_code
    FROM faculty_classes fc
    JOIN classes c ON c.code = fc.class_code
    LEFT JOIN class_enrollments ce ON ce.class_code = fc.class_code
    WHERE fc.faculty_id = ? AND fc.class_code = ?
");
$stmt->bind_param('ii', $user_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "YES - Faculty 114 is assigned to {$row['class_code']}\n";
        echo "Course: {$row['course_title']}\n";
    }
} else {
    echo "NOT FOUND\n";
}
?>
