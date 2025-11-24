<?php
require_once '../../config/db.php';

echo "=== Deleting System Prototype Grades ===\n\n";

// Delete the 2 stray System Prototype grades
$result = $conn->query("DELETE FROM student_flexible_grades WHERE id IN (404, 398)");

if ($result) {
    echo "✓ Deleted 2 System Prototype grades:\n";
    echo "  - Grade ID 404 (Student 2022-118764, score 0.00)\n";
    echo "  - Grade ID 398 (Student 2022-171253, score 0.00)\n\n";
    echo "System Prototype will no longer appear in your COA report.\n";
} else {
    echo "Error: " . $conn->error;
}
?>
