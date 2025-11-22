<?php
require_once 'config/db.php';

// Check what grades exist for this class
$query = "
    SELECT sfg.id, sfg.student_id, sfg.raw_score, gcc.name
    FROM student_flexible_grades sfg
    JOIN grading_component_columns gcc ON sfg.column_id = gcc.id
    JOIN grading_components gc ON gcc.component_id = gc.id
    WHERE gc.class_code LIKE '%INF222%'
    ORDER BY sfg.student_id, gcc.order_index
    LIMIT 30
";

$result = $conn->query($query);
if (!$result) {
    echo "Error: " . $conn->error;
} else {
    echo "Current grades in database:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  Student {$row['student_id']}, {$row['name']}: {$row['raw_score']}\n";
    }
}

$conn->close();
?>