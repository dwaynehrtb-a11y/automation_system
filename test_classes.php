<?php
require_once 'config/db.php';

$result = $conn->query('SELECT COUNT(*) as cnt FROM class WHERE faculty_id IS NULL');
$row = $result->fetch_assoc();
echo "Classes with NULL faculty: " . $row['cnt'] . "\n";

$result2 = $conn->query('SELECT * FROM class LIMIT 5');
echo "\nSample classes:\n";
while($r = $result2->fetch_assoc()) {
    $fac = $r['faculty_id'] ?? 'NULL';
    echo "Class: " . $r['course_code'] . " - Faculty: " . $fac . "\n";
}
?>
