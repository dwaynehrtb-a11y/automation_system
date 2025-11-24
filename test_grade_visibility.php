<?php
/**
 * Simple test to check grade encryption status
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

// Get a sample student from CCPRGG1L class
$query = "
    SELECT 
        ce.student_id,
        gt.is_encrypted,
        gt.term_grade,
        gt.midterm_percentage,
        gt.finals_percentage
    FROM class_enrollments ce
    LEFT JOIN grade_term gt ON ce.student_id = gt.student_id AND ce.class_code = gt.class_code
    WHERE ce.class_code = 'CCPRGG1L'
    LIMIT 3
";

$result = $conn->query($query);

echo "<h2>CCPRGG1L Grade Encryption Check</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr>";
echo "<th>Student ID</th>";
echo "<th>is_encrypted</th>";
echo "<th>Term Grade (first 30 chars)</th>";
echo "<th>Midterm %</th>";
echo "<th>Finals %</th>";
echo "</tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
    echo "<td>" . ($row['is_encrypted'] ?? 'NULL') . "</td>";
    echo "<td>" . substr($row['term_grade'] ?? '', 0, 30) . "...</td>";
    echo "<td>" . substr($row['midterm_percentage'] ?? '', 0, 10) . "</td>";
    echo "<td>" . substr($row['finals_percentage'] ?? '', 0, 10) . "</td>";
    echo "</tr>";
}

echo "</table>";

// Now test API directly
echo "<h2>API Test - Calling get_grades.php</h2>";

$student_id = '2022-118764';
$class_code = 'CCPRGG1L';

$_SESSION['user_id'] = 1;  
$_SESSION['role'] = 'student';
$_SESSION['student_id'] = $student_id;
$_SESSION['csrf_token'] = 'test_token';

$_POST['action'] = 'get_student_grade_summary';
$_POST['class_code'] = $class_code;
$_POST['csrf_token'] = 'test_token';

ob_start();
include 'student/ajax/get_grades.php';
$output = ob_get_clean();

echo "<pre>";
echo htmlspecialchars($output);
echo "</pre>";
?>
