<?php
require_once 'config/db.php';

$query = 'SELECT g.student_id, g.column_id, g.raw_score, gcc.column_name, gcc.max_score, gc.component_name
          FROM student_flexible_grades g
          INNER JOIN grading_component_columns gcc ON g.column_id = gcc.id
          INNER JOIN grading_components gc ON gcc.component_id = gc.id
          WHERE g.raw_score > gcc.max_score AND g.raw_score <= 100
          ORDER BY g.raw_score DESC
          LIMIT 20';

$result = $conn->query($query);
if ($result) {
    echo "Grades where raw_score > max_score (potential percentages):\n";
    while ($row = $result->fetch_assoc()) {
        echo "Student {$row['student_id']} - {$row['component_name']}->{$row['column_name']}: {$row['raw_score']} / {$row['max_score']}\n";
    }
} else {
    echo 'Query failed: ' . $conn->error;
}

$query2 = 'SELECT g.student_id, g.column_id, g.raw_score, gcc.column_name, gcc.max_score, gc.component_name
          FROM student_flexible_grades g
          INNER JOIN grading_component_columns gcc ON g.column_id = gcc.id
          INNER JOIN grading_components gc ON gcc.component_id = gc.id
          WHERE g.raw_score LIKE "%.%"
          ORDER BY g.raw_score DESC
          LIMIT 10';

$result2 = $conn->query($query2);
if ($result2) {
    echo "\nGrades with decimal points (potential percentages):\n";
    while ($row = $result2->fetch_assoc()) {
        echo "Student {$row['student_id']} - {$row['component_name']}->{$row['column_name']}: {$row['raw_score']} / {$row['max_score']}\n";
    }
} else {
    echo 'Query failed: ' . $conn->error;
}

$conn->close();
?>