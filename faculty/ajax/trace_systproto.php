<?php
require_once '../../config/db.php';

echo "=== Direct Database Query ===\n\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM student_flexible_grades WHERE column_id = 138");
$row = $result->fetch_assoc();
echo "Grades for System Prototype column (138): " . $row['cnt'] . "\n";

$result = $conn->query("
    SELECT sfg.id, sfg.student_id, sfg.raw_score 
    FROM student_flexible_grades sfg 
    WHERE sfg.column_id = 138 LIMIT 5
");

echo "\nFirst 5 grade records:\n";
while ($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

// Check if students table has these student IDs
echo "\n=== Check student IDs ===\n";
$result = $conn->query("
    SELECT DISTINCT sfg.student_id
    FROM student_flexible_grades sfg
    WHERE sfg.column_id = 138
");

$student_ids = [];
while ($row = $result->fetch_assoc()) {
    $student_ids[] = $row['student_id'];
}

echo "Student IDs with System Prototype grades: " . implode(', ', $student_ids) . "\n";

// Now look up their names
foreach ($student_ids as $sid) {
    $result = $conn->query("SELECT id, name FROM students WHERE id = $sid");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "- ID $sid: {$row['name']}\n";
    } else {
        echo "- ID $sid: NOT FOUND IN STUDENTS TABLE\n";
    }
}

// Now check which classes these students are in
echo "\n=== Classes for these students ===\n";
$in_list = implode(',', $student_ids);
$result = $conn->query("
    SELECT DISTINCT ce.student_id, ce.class_code, s.name
    FROM class_enrollments ce
    JOIN students s ON s.id = ce.student_id
    WHERE ce.student_id IN ($in_list) AND ce.status = 'enrolled'
    ORDER BY ce.class_code
");

while ($row = $result->fetch_assoc()) {
    echo "- Student {$row['student_id']} ({$row['name']}): {$row['class_code']}\n";
}
?>
