<?php
include 'config/db.php';

$studentId = '2022-126653';

// Find all classes for this student
$result = $conn->query("SELECT DISTINCT class_code, midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='$studentId' ORDER BY class_code");

echo "=== ALL CLASSES FOR STUDENT $studentId ===\n\n";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Class: " . $row['class_code'] . "\n";
        echo "  Midterm %: " . $row['midterm_percentage'] . "%\n";
        echo "  Finals %: " . $row['finals_percentage'] . "%\n";
        echo "  Term %: " . $row['term_percentage'] . "%\n";
        echo "  Term Grade: " . $row['term_grade'] . "\n\n";
    }
} else {
    echo "No grade records found for student $studentId\n";
}

// Also check enrollments
echo "\n=== ENROLLMENTS ===\n";
$enroll = $conn->query("SELECT class_code FROM enrollments WHERE student_id='$studentId' LIMIT 10");
if ($enroll && $enroll->num_rows > 0) {
    while ($row = $enroll->fetch_assoc()) {
        echo "Enrolled in: " . $row['class_code'] . "\n";
    }
} else {
    echo "No enrollments found\n";
}
?>
