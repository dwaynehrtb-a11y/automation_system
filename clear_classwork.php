<?php
/**
 * Simple fix: Delete all Classwork grades to clear the corrupted percentage data
 */

require_once 'config/db.php';

// Find the Classwork component for INF222
$query = "
    DELETE FROM student_flexible_grades
    WHERE column_id IN (
        SELECT gcc.id
        FROM grading_component_columns gcc
        JOIN grading_components gc ON gcc.component_id = gc.id
        WHERE gc.component_name = 'Classwork'
        AND gc.class_code LIKE '%INF222%'
    )
";

if ($conn->query($query)) {
    echo "✓ Cleared " . $conn->affected_rows . " corrupted Classwork grades\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
?>