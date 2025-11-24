<?php
require 'config/db.php';

echo "=== student_flexible_grades columns ===\n";
$result = $conn->query('DESCRIBE student_flexible_grades');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\n=== grading_component_columns columns ===\n";
$result = $conn->query('DESCRIBE grading_component_columns');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
