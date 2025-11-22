<?php
require_once 'config/db.php';

$result = $conn->query('SELECT gcc.id, gcc.column_name, gcc.max_score, gc.component_name 
                       FROM grading_component_columns gcc 
                       INNER JOIN grading_components gc ON gcc.component_id = gc.id 
                       WHERE gc.component_name LIKE "%Quiz%" 
                       LIMIT 10');

if ($result) {
    echo 'Quiz columns:\n';
    while ($row = $result->fetch_assoc()) {
        echo 'Column ' . $row['id'] . ': ' . $row['column_name'] . ' (max: ' . $row['max_score'] . ')\n';
    }
}

$result2 = $conn->query('SELECT gcc.id, gcc.column_name, gcc.max_score, gc.component_name 
                        FROM grading_component_columns gcc 
                        INNER JOIN grading_components gc ON gcc.component_id = gc.id 
                        WHERE gcc.max_score <= 50
                        LIMIT 10');

if ($result2) {
    echo '\nColumns with max_score <= 50:\n';
    while ($row = $result2->fetch_assoc()) {
        echo 'Column ' . $row['id'] . ': ' . $row['component_name'] . ' -> ' . $row['column_name'] . ' (max: ' . $row['max_score'] . ')\n';
    }
}

$conn->close();
?>