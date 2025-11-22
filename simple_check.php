<?php
require_once 'config/db.php';

$query = "SELECT * FROM student_flexible_grades WHERE student_id = '2022-118764' AND status = 'inc' LIMIT 5";
$result = $conn->query($query);

echo "INC Records:\n";
while ($row = $result->fetch_assoc()) {
    print_r($row);
    echo "\n---\n";
}
?>
