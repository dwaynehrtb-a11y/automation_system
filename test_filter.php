<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';

echo "Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "<br>";
echo "Connection: " . (isset($conn) ? 'EXISTS' : 'NOT SET') . "<br>";

if (!isset($conn)) {
    die("No connection");
}

// First, let's check what columns exist in the class table
echo "<h3>Class table structure:</h3>";
$columns = $conn->query("DESCRIBE class");
while ($col = $columns->fetch_assoc()) {
    echo $col['Field'] . " (" . $col['Type'] . ")<br>";
}

echo "<hr>";

$academic_year = '24';
$term = 'T2';

// Simplified query first
$simple_query = "SELECT * FROM class WHERE academic_year = ? AND term = ? LIMIT 5";
$stmt = $conn->prepare($simple_query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt = $conn->prepare($simple_query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
echo "Statement prepared<br>";

$stmt->bind_param("ss", $academic_year, $term);
echo "Parameters bound<br>";

$stmt->execute();
echo "Query executed<br>";

$result = $stmt->get_result();
echo "Rows found: " . $result->num_rows . "<br><br>";

echo "<h3>Sample data:</h3>";
while ($row = $result->fetch_assoc()) {
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}
