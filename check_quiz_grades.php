<?php
require_once 'config/db.php';

$query = 'SELECT g.student_id, g.column_id, g.raw_score, gcc.column_name, gcc.max_score, gc.component_name
          FROM student_flexible_grades g
          INNER JOIN grading_component_columns gcc ON g.column_id = gcc.id
          INNER JOIN grading_components gc ON gcc.component_id = gc.id
          WHERE gcc.component_id = 35
          ORDER BY g.student_id, gcc.id';

$result = $conn->query($query);
if ($result) {
    echo "Grades for Quizzes component (ID 35):\n";
    while ($row = $result->fetch_assoc()) {
        echo "Student {$row['student_id']} - {$row['column_name']}: {$row['raw_score']} / {$row['max_score']}\n";
    }
} else {
    echo 'Query failed: ' . $conn->error;
}

$conn->close();
?>