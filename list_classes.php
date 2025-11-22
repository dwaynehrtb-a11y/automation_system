<?php
require_once 'config/db.php';

echo "Available classes with grading components:\n";
$result = $conn->query("
    SELECT DISTINCT gc.class_code, c.subject_code, COUNT(gcc.id) as column_count
    FROM grading_components gc
    JOIN class c ON gc.class_code = c.class_code
    LEFT JOIN grading_component_columns gcc ON gc.id = gcc.component_id
    GROUP BY gc.class_code
    ORDER BY gc.class_code DESC
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    echo "  {$row['class_code']} ({$row['subject_code']}): {$row['column_count']} columns\n";
}

$conn->close();
?>