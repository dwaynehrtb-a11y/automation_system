<?php
require_once 'config/db.php';

// Check structure
$result = $conn->query("DESCRIBE grading_components");
echo "grading_components columns:\n";
while ($row = $result->fetch_assoc()) {
    echo "  " . $row['Field'] . "\n";
}

$conn->close();
?>