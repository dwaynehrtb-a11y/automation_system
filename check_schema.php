<?php
require 'config/db.php';

$result = $conn->query('SHOW COLUMNS FROM grade_term');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . PHP_EOL;
}
?>
