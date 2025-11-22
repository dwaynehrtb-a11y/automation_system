<?php
/**
 * Fix corrupted grades - convert percentages back to raw scores
 * CW1, CW2, CW3 columns had percentage values stored as raw_score
 * This script removes those corrupted entries so they can be re-entered correctly
 */

require_once 'config/db.php';

// Find columns that are named like "CW1", "CW2", "CW3"
$query = "
    SELECT gcc.id, gcc.name, gc.id as component_id, gc.class_code
    FROM grading_component_columns gcc
    JOIN grading_components gc ON gcc.component_id = gc.id
    WHERE gcc.name IN ('CW1', 'CW2', 'CW3')
    AND gc.class_code = '24_T2_CCPRGG1L_INF222'
";

$result = $conn->query($query);
echo "Found " . $result->num_rows . " corrupted columns\n";

$column_ids = [];
while ($row = $result->fetch_assoc()) {
    $column_ids[] = $row['id'];
    echo "Column: {$row['name']} (ID: {$row['id']}) in {$row['class_code']}\n";
}

if (count($column_ids) > 0) {
    // Delete grades for these columns to clear corrupted data
    $ids_str = implode(',', $column_ids);
    $deleteQuery = "DELETE FROM student_flexible_grades WHERE column_id IN ($ids_str)";
    
    if ($conn->query($deleteQuery)) {
        echo "Deleted " . $conn->affected_rows . " corrupted grade records\n";
        echo "✓ Cleanup complete! Grades for CW1, CW2, CW3 have been cleared.\n";
        echo "✓ Teachers can now re-enter grades correctly.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
} else {
    echo "No corrupted columns found.\n";
}

$conn->close();
?>