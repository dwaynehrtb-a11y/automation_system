<?php
require_once 'config/db.php';

$result = $conn->query("SELECT id, fullname, email FROM users WHERE role = 'faculty' ORDER BY id");

echo "Valid Faculty IDs in System:\n";
echo "================================\n";

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo $row['id'] . " - " . $row['fullname'] . " (" . $row['email'] . ")\n";
    }
} else {
    echo "No faculty found in system\n";
}

echo "\n\nTotal Faculty Count: " . $result->num_rows . "\n";
?>
