<?php
require_once 'config/db.php';

echo "Deleting placeholder class row...\n";

$result = $conn->query("DELETE FROM class WHERE class_code = 'Academic Year_Term_Course Code_Section' AND course_code = 'Course Code'");

if ($result) {
    echo "✓ Deleted " . $conn->affected_rows . " placeholder row(s)\n";
} else {
    echo "✗ Error: " . $conn->error . "\n";
}

echo "\nRemaining classes in database:\n";
echo str_repeat("-", 80) . "\n";

$classes = $conn->query("SELECT class_code, section, academic_year, term, course_code, faculty_id FROM class ORDER BY class_code");

while ($row = $classes->fetch_assoc()) {
    $fac = $row['faculty_id'] ?? 'NULL';
    echo sprintf("%-40s | Sec: %-10s | Year: %s | Term: %s | Fac: %s\n",
        $row['class_code'],
        $row['section'],
        $row['academic_year'],
        $row['term'],
        $fac
    );
}
?>
