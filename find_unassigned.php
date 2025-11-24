<?php
require_once 'config/db.php';

echo "Looking for the Unassigned class with N/A course_code:\n";
echo str_repeat("-", 100) . "\n";

$result = $conn->query("SELECT * FROM class WHERE course_code = 'N/A' OR course_code = ''");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Found:\n";
        print_r($row);
        echo "\n";
    }
} else {
    echo "No class with N/A or empty course_code found\n";
}

echo "\n\nAll classes with NULL faculty_id:\n";
$null_result = $conn->query("SELECT * FROM class WHERE faculty_id IS NULL");
if ($null_result->num_rows > 0) {
    while ($row = $null_result->fetch_assoc()) {
        print_r($row);
        echo "\n";
    }
} else {
    echo "No classes with NULL faculty_id\n";
}
?>
