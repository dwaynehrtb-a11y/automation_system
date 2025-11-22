<?php
require_once 'config/db.php';

echo "=== FIXING NULL GRADE_STATUS ===\n\n";

// Find all students with NULL grade_status
$result = $conn->query("
    SELECT tg.student_id, tg.class_code, tg.term_grade
    FROM grade_term tg
    WHERE tg.grade_status IS NULL
");

if($result->num_rows == 0) {
    echo "No students with NULL grade_status found.\n";
    exit;
}

echo "Found " . $result->num_rows . " students with NULL grade_status.\n\n";

// For each student, determine the appropriate status based on term_grade
$updates = 0;
while($row = $result->fetch_assoc()) {
    $student_id = $row['student_id'];
    $class_code = $row['class_code'];
    $term_grade = $row['term_grade'];
    
    // Determine status based on term_grade
    if($term_grade === null) {
        $status = 'incomplete';
        $reason = "No term grade";
    } elseif($term_grade >= 2.0) {
        $status = 'passed';
        $reason = "Grade >= 2.0";
    } elseif($term_grade >= 1.0 && $term_grade < 2.0) {
        $status = 'passed';
        $reason = "Grade >= 1.0 and < 2.0";
    } else {
        $status = 'failed';
        $reason = "Grade < 1.0";
    }
    
    echo "Student: $student_id | Class: $class_code\n";
    echo "  Term Grade: " . ($term_grade ?: 'NULL') . "\n";
    echo "  New Status: $status ($reason)\n";
    
    // Update the grade_status
    $stmt = $conn->prepare("UPDATE grade_term SET grade_status = ? WHERE student_id = ? AND class_code = ? AND grade_status IS NULL");
    if($stmt) {
        $stmt->bind_param("sss", $status, $student_id, $class_code);
        if($stmt->execute()) {
            echo "  ✓ Updated\n";
            $updates++;
        } else {
            echo "  ✗ Error: " . $stmt->error . "\n";
        }
        $stmt->close();
    }
    echo "\n";
}

echo "Total updates: $updates\n";
?>
