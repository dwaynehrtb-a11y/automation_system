<?php
require_once 'config/db.php';

echo "Classes in database:\n";
echo str_repeat("-", 120) . "\n";

$result = $conn->query("SELECT id, class_code, section, course_code, academic_year, term, faculty_id, room FROM class ORDER BY id DESC");

while($r = $result->fetch_assoc()) {
    $fac = $r['faculty_id'] ?? 'NULL';
    echo sprintf("ID: %s | Code: %s | Section: %s | Course: %s | Year: %s | Term: %s | Faculty: %s | Room: %s\n", 
        $r['id'], 
        $r['class_code'],
        $r['section'],
        $r['course_code'],
        $r['academic_year'],
        $r['term'],
        $fac,
        $r['room']
    );
}
?>
