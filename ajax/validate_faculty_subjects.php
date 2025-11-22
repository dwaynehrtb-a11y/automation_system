<?php
session_start();
if (!isset($_SESSION['faculty_id'])) {
    die("You are not logged in as a faculty.");
}

require_once '../config/session.php';
require_once '../config/db.php';

$faculty_id = $_SESSION['faculty_id'];
$faculty_name = $_SESSION['name'];

echo "<h2>🔍 Faculty Debug Validation: $faculty_name (ID: $faculty_id)</h2>";
echo "<pre>";

// 1. Show all subjects assigned to this faculty
echo "\n📘 SUBJECTS ASSIGNED TO FACULTY:\n";
$subjects = $conn->query("SELECT * FROM subjects WHERE faculty_id = $faculty_id");
if ($subjects->num_rows === 0) {
    echo "❌ No subjects assigned to this faculty.\n";
} else {
    while ($row = $subjects->fetch_assoc()) {
        echo "- [{$row['id']}] {$row['course_code']} | {$row['course_title']}\n";
    }
}

// 2. Show all sections and check if they match any subject's course_code
echo "\n📗 SECTIONS MATCHING ASSIGNED SUBJECTS:\n";
$sql = "
    SELECT s.section_id, s.course_code AS section_code, s.term, s.year, sj.course_title
    FROM section s
    JOIN subjects sj 
      ON TRIM(s.course_code) LIKE CONCAT('%', TRIM(sj.course_code), '%')
      OR TRIM(sj.course_code) LIKE CONCAT('%', TRIM(s.course_code), '%')
    WHERE sj.faculty_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "❌ No matching sections found for this faculty's subjects.\n";
} else {
    while ($row = $res->fetch_assoc()) {
        echo "- Section ID: {$row['section_id']} | Course Code: {$row['section_code']} | Term: {$row['term']} | Year: {$row['year']} | Title: {$row['course_title']}\n";
    }
}

echo "</pre>";
?>
