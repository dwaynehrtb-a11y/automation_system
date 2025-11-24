<?php
include 'config/db.php';

$studentId = '2022-126653';

// Check for CCPRGG1L specifically
$result = $conn->query("SELECT student_id, class_code, midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='$studentId' AND class_code='CCPRGG1L'");

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "=== DATABASE STATE ===\n";
    echo "Student: " . $row['student_id'] . "\n";
    echo "Class: " . $row['class_code'] . "\n";
    echo "Midterm %: " . $row['midterm_percentage'] . "%\n";
    echo "Finals %: " . $row['finals_percentage'] . "%\n";
    echo "Term %: " . $row['term_percentage'] . "%\n";
    echo "Term Grade: " . $row['term_grade'] . "\n";
} else {
    echo "No record found for student $studentId in CCPRGG1L\n";
}
?>
