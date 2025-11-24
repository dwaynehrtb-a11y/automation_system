<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'automation_system';

$conn = new mysqli($host, $user, $pass, $db);

echo "Cleaning up placeholder rows...\n";
echo str_repeat("-", 80) . "\n";

$result = $conn->query("DELETE FROM class WHERE 
    (course_code = 'Course Code') 
    OR (section = 'Section')
    OR (academic_year = 'Academic Year')
    OR (class_code LIKE '%Academic Year%Term%Course Code%')");

echo "Deleted " . $conn->affected_rows . " placeholder row(s)\n";

echo "\n\nRemaining classes:\n";
echo str_repeat("-", 80) . "\n";

$all = $conn->query("SELECT class_id, class_code, section, course_code, academic_year FROM class ORDER BY class_id");

while ($r = $all->fetch_assoc()) {
    echo "ID: " . $r['class_id'] . " | Code: " . $r['class_code'] . " | Section: " . $r['section'] . " | Course: " . $r['course_code'] . " | Year: " . $r['academic_year'] . "\n";
}

echo "\nAll placeholder rows removed! You can now reload the page.\n";
?>
