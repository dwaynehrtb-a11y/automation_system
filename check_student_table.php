<?php
require 'config/db.php';

echo "Student table columns:\n";
$r = $conn->query('DESCRIBE student');
while($c = $r->fetch_assoc()) {
    echo $c['Field'] . "\n";
}

echo "\nSample student:\n";
$s = $conn->query('SELECT * FROM student LIMIT 1');
if ($s && $s->num_rows > 0) {
    $row = $s->fetch_assoc();
    foreach ($row as $key => $val) {
        if (strlen($val) > 50) {
            echo "$key: " . substr($val, 0, 50) . "...\n";
        } else {
            echo "$key: $val\n";
        }
    }
}
?>
