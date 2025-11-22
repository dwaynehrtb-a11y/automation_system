<?php
require_once 'config/db.php';

$result = $conn->query('SELECT g.student_id, g.column_id, g.raw_score, gcc.column_name, gcc.max_score
                       FROM student_flexible_grades g
                       INNER JOIN grading_component_columns gcc ON g.column_id = gcc.id
                       WHERE g.raw_score LIKE "%\%"
                       LIMIT 10');

if ($result) {
    echo 'Grades with % symbol:\n';
    while ($row = $result->fetch_assoc()) {
        echo 'Student ' . $row['student_id'] . ' - ' . $row['column_name'] . ': ' . $row['raw_score'] . ' / ' . $row['max_score'] . '\n';
    }
}

$conn->close();
?>