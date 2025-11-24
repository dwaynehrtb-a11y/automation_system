<?php
require_once '../../config/db.php';

echo "=== Your Class Students vs System Prototype Grades ===\n\n";

$class_code = '24_T2_CCPRGG1L_INF222';

// Get your enrolled students
$stmt = $conn->prepare("
    SELECT ce.student_id, s.name
    FROM class_enrollments ce
    LEFT JOIN students s ON s.id = ce.student_id
    WHERE ce.class_code = ? AND ce.status = 'enrolled'
    ORDER BY s.name
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "Your enrolled students:\n";
$your_students = [];
while ($row = $result->fetch_assoc()) {
    $your_students[] = $row['student_id'];
    echo "- {$row['student_id']}: " . ($row['name'] ?? 'NO NAME') . "\n";
}

echo "\n=== System Prototype Grades ===\n";
$result = $conn->query("
    SELECT DISTINCT sfg.student_id, s.name
    FROM student_flexible_grades sfg
    LEFT JOIN students s ON s.id = sfg.student_id
    WHERE sfg.column_id IN (
        SELECT gcc.id FROM grading_component_columns gcc
        JOIN grading_components gc ON gc.id = gcc.component_id
        WHERE gc.component_name = 'System Prototype'
    )
    ORDER BY sfg.student_id
");

echo "Students with System Prototype grades:\n";
while ($row = $result->fetch_assoc()) {
    $in_class = in_array($row['student_id'], $your_students) ? "✓ IN CLASS" : "✗ NOT IN CLASS";
    echo "- {$row['student_id']}: " . ($row['name'] ?? 'NO NAME') . " [$in_class]\n";
}

echo "\n=== Solution ===\n";
echo "The issue: System Prototype grades exist for students NOT in your class.\n";
echo "The query joins to class_enrollments and should filter them out.\n";
echo "But the students with System Prototype grades are not even in the class_enrollments table!\n\n";

echo "The 2 students showing System Prototype grades (2022-118764, 2022-171253) ARE in your class,\n";
echo "so they do have enrollments. That means System Prototype grades were entered for them,\n";
echo "but you don't want to count them in your COA.\n\n";

echo "Option 1: Delete the System Prototype grades for these 2 students\n";
echo "Option 2: Unmap System Prototype from CO3\n";
echo "Option 3: Mark System Prototype as 'inactive' or 'unused'\n";
?>
