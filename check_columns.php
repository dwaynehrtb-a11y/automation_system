<?php
require 'config/db.php';

$result = $conn->query('SELECT id, column_name, max_score, component_id FROM grading_component_columns ORDER BY id DESC LIMIT 10');

echo "Last 10 columns in database:\n\n";
while($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . "\n";
    echo "Column Name: " . $row['column_name'] . "\n";
    echo "Max Score: " . $row['max_score'] . "\n";
    echo "Component ID: " . $row['component_id'] . "\n";
    echo "---\n";
}
?>
