<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>✅ Verification: Student Grade After Fix</h2>";
echo "<pre>";

// Get the CCPRGG1L class
$class_query = "SELECT DISTINCT c.class_code FROM class_enrollments ce JOIN class c ON ce.class_code = c.class_code WHERE ce.student_id='2022-126653' AND c.course_code LIKE '%CCPRGG1L%'";
$class_result = $conn->query($class_query);
$class_row = $class_result->fetch_assoc();
$class_code = $class_row['class_code'];

// Get updated values
$query = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' AND class_code=?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "CCPRGG1L - Fundamentals of Programming\n";
echo "Student: Jabba Santis (2022-126653)\n\n";

echo "UPDATED DATABASE VALUES:\n";
echo "=" . str_repeat("=", 60) . "\n";
echo "Midterm: " . $row['midterm_percentage'] . "%\n";
echo "Finals: " . $row['finals_percentage'] . "%\n";
echo "Term %: " . $row['term_percentage'] . "%\n";
echo "Term Grade: " . $row['term_grade'] . "\n\n";

echo "COMPARISON WITH FACULTY DISPLAY:\n";
echo "=" . str_repeat("=", 60) . "\n";
echo "Faculty shows:   62.03% midterm → 84.81% term → Grade 3.0\n";
echo "Database now:    " . $row['midterm_percentage'] . "% midterm → " . $row['term_percentage'] . "% term → Grade " . $row['term_grade'] . "\n\n";

// Verify calculation
$mid = floatval($row['midterm_percentage']);
$fin = floatval($row['finals_percentage']);
$calc = ($mid * 0.40) + ($fin * 0.60);

echo "VERIFICATION:\n";
echo "=" . str_repeat("=", 60) . "\n";
echo "Calculation: (" . $mid . " × 0.40) + (" . $fin . " × 0.60)\n";
echo "           = " . number_format($calc, 2) . "%\n";
echo "Stored:      " . $row['term_percentage'] . "%\n";

if (abs($calc - floatval($row['term_percentage'])) < 0.01) {
    echo "✅ MATCH!\n";
} else {
    echo "❌ MISMATCH\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
if (abs($mid - 62.03) < 0.1 && abs(floatval($row['term_percentage']) - 84.81) < 0.1) {
    echo "✅ FIXED! Student now sees same grades as faculty!\n";
} else {
    echo "Status: Awaiting confirmation\n";
}
echo str_repeat("=", 60) . "\n";

$conn->close();
?>
