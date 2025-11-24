<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'automation_system';

$conn = new mysqli($host, $user, $pass, $db);

echo "Searching for placeholder rows...\n";
echo str_repeat("-", 80) . "\n";

$result = $conn->query("SELECT * FROM class WHERE 
    course_code IN ('N/A', 'Course Code', '') 
    OR section IN ('Section', 'SECTION') 
    OR academic_year IN ('Academic Year')");

echo "Found " . $result->num_rows . " placeholder rows\n\n";

while ($r = $result->fetch_assoc()) {
    echo "ID: " . $r['class_id'] . "\n";
    echo "Code: " . $r['class_code'] . "\n";
    echo "Section: " . $r['section'] . "\n";
    echo "Course: " . $r['course_code'] . "\n";
    echo "Year: " . $r['academic_year'] . "\n";
    echo "Term: " . $r['term'] . "\n";
    echo "Room: " . $r['room'] . "\n";
    echo "Faculty ID: " . ($r['faculty_id'] ?? 'NULL') . "\n";
    echo "\n";
}

// Also check what's currently being displayed
echo "\n" . str_repeat("-", 80) . "\n";
echo "All classes in database:\n";
echo str_repeat("-", 80) . "\n";

$all = $conn->query("SELECT class_id, class_code, section, course_code, academic_year, term, room, faculty_id FROM class ORDER BY class_id");

while ($r = $all->fetch_assoc()) {
    $fac = $r['faculty_id'] ? "ID: " . $r['faculty_id'] : "NULL";
    echo "ID: " . $r['class_id'] . " | Code: " . $r['class_code'] . " | Section: " . $r['section'] . " | Course: " . $r['course_code'] . " | Faculty: " . $fac . "\n";
}
?>
