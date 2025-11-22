<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

$student_id = '2022-118764';

// Get the actual grade_term record
echo "<h2>Grade Term Raw Data for CCPRGG1L</h2>";
$query = "
    SELECT gt.*, c.course_code, c.term, s.course_title
    FROM grade_term gt
    INNER JOIN class c ON gt.class_code = c.class_code
    LEFT JOIN subjects s ON c.course_code = s.course_code
    WHERE gt.student_id = ? AND gt.class_code = 'CCPRGG1L'
    LIMIT 1
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "<pre>";
    echo "Student ID: " . $row['student_id'] . "\n";
    echo "Class Code: " . $row['class_code'] . "\n";
    echo "Course Code: " . $row['course_code'] . "\n";
    echo "Course Title: " . $row['course_title'] . "\n";
    echo "Term: " . $row['term'] . "\n";
    echo "---\n";
    echo "midterm_percentage: " . $row['midterm_percentage'] . " (type: " . gettype($row['midterm_percentage']) . ")\n";
    echo "finals_percentage: " . $row['finals_percentage'] . " (type: " . gettype($row['finals_percentage']) . ")\n";
    echo "term_percentage: " . $row['term_percentage'] . " (type: " . gettype($row['term_percentage']) . ")\n";
    echo "term_grade: " . $row['term_grade'] . " (type: " . gettype($row['term_grade']) . ")\n";
    echo "grade_status: " . $row['grade_status'] . "\n";
    echo "</pre>";
    
    // Try to decode if JSON
    if ($row['midterm_percentage']) {
        $decoded = json_decode($row['midterm_percentage'], true);
        if ($decoded) {
            echo "<h3>Midterm Percentage (decoded from JSON):</h3>";
            echo "<pre>" . json_encode($decoded, JSON_PRETTY_PRINT) . "</pre>";
        }
    }
    
    if ($row['finals_percentage']) {
        $decoded = json_decode($row['finals_percentage'], true);
        if ($decoded) {
            echo "<h3>Finals Percentage (decoded from JSON):</h3>";
            echo "<pre>" . json_encode($decoded, JSON_PRETTY_PRINT) . "</pre>";
        }
    }
} else {
    echo "No record found";
}

$stmt->close();
?>
