<?php
require_once 'config/db.php';

echo "=== Grade Term Table Structure ===\n";
$result = $conn->query('DESCRIBE grade_term');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\n=== Class Table Structure ===\n";
$result = $conn->query('DESCRIBE class');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
