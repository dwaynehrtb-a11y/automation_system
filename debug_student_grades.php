<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/db.php';

echo "<h2>Debugging Grade Term Query</h2>";
echo "<pre>";

// First, let's see all grades for student 2022-126653
echo "=== All grade_term records for student 2022-126653 ===\n";
$query = "SELECT class_code, midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' ORDER BY class_code LIMIT 20";
$result = $conn->query($query);

if (!$result) {
    echo "Query error: " . $conn->error . "\n";
} elseif ($result->num_rows == 0) {
    echo "No records found.\n";
} else {
    echo "Found " . $result->num_rows . " records:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  Class: " . $row['class_code'] . " | Mid: " . $row['midterm_percentage'] . "% | Finals: " . $row['finals_percentage'] . "% | Term: " . $row['term_percentage'] . "% | Grade: " . $row['term_grade'] . "\n";
        
        // Verify calculation
        if ($row['midterm_percentage'] !== null && $row['finals_percentage'] !== null) {
            $calculated = ($row['midterm_percentage'] * 0.40) + ($row['finals_percentage'] * 0.60);
            echo "    Calculated term%: " . number_format($calculated, 2) . "% (should match stored " . $row['term_percentage'] . "%)\n";
        }
    }
}

echo "\n\n=== What classes is student 2022-126653 enrolled in? ===\n";
$query2 = "SELECT DISTINCT c.class_code, s.course_title FROM class_enrollments ce JOIN class c ON ce.class_code = c.class_code LEFT JOIN subjects s ON c.course_code = s.course_code WHERE ce.student_id='2022-126653' AND ce.status='enrolled'";
$result2 = $conn->query($query2);

if ($result2->num_rows == 0) {
    echo "No enrollments found.\n";
} else {
    echo "Found " . $result2->num_rows . " enrollments:\n";
    while ($row = $result2->fetch_assoc()) {
        echo "  " . $row['class_code'] . " (" . $row['course_title'] . ")\n";
    }
}

echo "\n\n=== Checking for class with course containing 'CCPRGG' ===\n";
$query3 = "SELECT DISTINCT c.class_code, c.course_code, s.course_title FROM class_enrollments ce JOIN class c ON ce.class_code = c.class_code LEFT JOIN subjects s ON c.course_code = s.course_code WHERE ce.student_id='2022-126653' AND c.course_code LIKE '%CCPRGG%'";
$result3 = $conn->query($query3);

if ($result3->num_rows == 0) {
    echo "No CCPRGG classes found.\n";
} else {
    echo "Found " . $result3->num_rows . " CCPRGG classes:\n";
    while ($row = $result3->fetch_assoc()) {
        echo "  Code: " . $row['class_code'] . " | Course: " . $row['course_code'] . " | Title: " . $row['course_title'] . "\n";
        
        // Now check if grades exist for this class
        $grade_check = "SELECT term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' AND class_code='" . $conn->real_escape_string($row['class_code']) . "' LIMIT 1";
        $grade_result = $conn->query($grade_check);
        if ($grade_result && $grade_result->num_rows > 0) {
            $grade_row = $grade_result->fetch_assoc();
            echo "    → Grade exists: " . $grade_row['term_percentage'] . "% → Grade: " . $grade_row['term_grade'] . "\n";
        } else {
            echo "    → NO GRADE FOUND IN grade_term!\n";
        }
    }
}

echo "</pre>";
?>
