<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'automation_system';

$conn = new mysqli($host, $user, $pass, $db);

echo "Users table structure:\n";
echo str_repeat("-", 80) . "\n";

$result = $conn->query("DESCRIBE users");
while ($row = $result->fetch_assoc()) {
    echo sprintf("%-20s | %-15s | %s\n", $row['Field'], $row['Type'], ($row['Null'] === 'NO' ? 'NOT NULL' : 'NULL'));
}
?>
