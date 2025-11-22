<?php
require_once 'config/db.php';

$result = $conn->query('SELECT g.student_id, g.column_id, g.raw_score, gcc.column_name, gcc.max_score, gc.component_name
                       FROM student_flexible_grades g
                       INNER JOIN grading_component_columns gcc ON g.column_id = gcc.id
                       INNER JOIN grading_components gc ON gcc.component_id = gc.id
                       WHERE gcc.max_score = 10 AND g.raw_score > 10 AND g.raw_score <= 100
                       ORDER BY g.raw_score DESC
                       LIMIT 20');

if ($result) {
    echo 'Grades with max_score=10 but raw_score > 10 (potential percentages):\n';
    while ($row = $result->fetch_assoc()) {
        echo 'Student ' . $row['student_id'] . ' - ' . $row['component_name'] . '->' . $row['column_name'] . ': ' . $row['raw_score'] . ' / ' . $row['max_score'] . '\n';
    }
}

$conn->close();
?>