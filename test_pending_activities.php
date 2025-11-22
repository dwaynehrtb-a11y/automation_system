<?php
require_once 'config/db.php';

// Get INC records for student 2022-118764
$query = "
    SELECT DISTINCT
        sfg.student_id,
        c.class_code,
        c.course_code,
        s.course_title,
        c.term,
        u.name as faculty_name,
        gcc.column_name
    FROM student_flexible_grades sfg
    INNER JOIN class c ON sfg.class_code = c.class_code
    LEFT JOIN subjects s ON c.course_code = s.course_code
    LEFT JOIN users u ON c.faculty_id = u.id
    LEFT JOIN grading_component_columns gcc ON sfg.column_id = gcc.id
    WHERE sfg.student_id = '2022-118764' AND sfg.status = 'inc'
    ORDER BY c.course_code, gcc.column_name
";

$result = $conn->query($query);

echo "Pending Activities for Student 2022-118764:\n";
echo "===========================================\n\n";

if ($result->num_rows > 0) {
    $current_class = '';
    while ($row = $result->fetch_assoc()) {
        if ($current_class !== $row['course_code']) {
            if ($current_class !== '') echo "\n";
            echo "Course: " . $row['course_code'] . " - " . $row['course_title'] . "\n";
            echo "Class Code: " . $row['class_code'] . "\n";
            echo "Term: " . $row['term'] . "\n";
            echo "Faculty: " . $row['faculty_name'] . "\n";
            echo "INC Components:\n";
            $current_class = $row['course_code'];
        }
        echo "  - " . $row['column_name'] . "\n";
    }
} else {
    echo "No INC records found for this student.\n";
}
?>
