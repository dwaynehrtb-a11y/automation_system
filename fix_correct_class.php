<?php
include 'config/db.php';

$studentId = '2022-126653';
$classCode = '25_T2_CCPRGG1L_INF223';

echo "=== UPDATING GRADE RECORD ===\n\n";

// Current state
$result = $conn->query("SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='$studentId' AND class_code='$classCode'");
$row = $result->fetch_assoc();
echo "BEFORE UPDATE:\n";
echo "  Midterm %: " . $row['midterm_percentage'] . "%\n";
echo "  Finals %: " . $row['finals_percentage'] . "%\n";
echo "  Term %: " . $row['term_percentage'] . "%\n";
echo "  Term Grade: " . $row['term_grade'] . "\n\n";

// Update with correct values
// Correct calculation: (62.03 * 0.40) + (100 * 0.60) = 24.812 + 60 = 84.812 = 84.81%
// 84.81% = 3.0 grade
$sql = "UPDATE grade_term 
        SET midterm_percentage=62.03, 
            finals_percentage=100.00, 
            term_percentage=84.81, 
            term_grade='3.0'
        WHERE student_id='$studentId' AND class_code='$classCode'";

if ($conn->query($sql)) {
    echo "UPDATE SUCCESSFUL!\n\n";
    
    // Verify
    $result = $conn->query("SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='$studentId' AND class_code='$classCode'");
    $row = $result->fetch_assoc();
    echo "AFTER UPDATE:\n";
    echo "  Midterm %: " . $row['midterm_percentage'] . "%\n";
    echo "  Finals %: " . $row['finals_percentage'] . "%\n";
    echo "  Term %: " . $row['term_percentage'] . "%\n";
    echo "  Term Grade: " . $row['term_grade'] . "\n";
} else {
    echo "UPDATE FAILED: " . $conn->error . "\n";
}
?>
