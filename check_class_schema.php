<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'automation_system';

$conn = new mysqli($host, $user, $pass, $db);

echo "Class table structure:\n";
echo str_repeat("-", 80) . "\n";

$result = $conn->query("DESCRIBE class");
while ($row = $result->fetch_assoc()) {
    echo sprintf("%-20s | %-15s | %s\n", $row['Field'], $row['Type'], ($row['Null'] === 'NO' ? 'NOT NULL' : 'NULL'));
}

echo "\n\nForeign keys for class table:\n";
echo str_repeat("-", 80) . "\n";

$fk = $conn->query("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
                     FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                     WHERE TABLE_NAME = 'class' AND REFERENCED_TABLE_NAME IS NOT NULL");

while ($row = $fk->fetch_assoc()) {
    echo sprintf("FK: %s | Column: %s -> %s.%s\n", 
        $row['CONSTRAINT_NAME'],
        $row['COLUMN_NAME'],
        $row['REFERENCED_TABLE_NAME'],
        $row['REFERENCED_COLUMN_NAME']
    );
}
?>
