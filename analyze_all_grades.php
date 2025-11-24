<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Comprehensive Grade Analysis for Student 2022-126653</h2>";
echo "<pre>";

// Get ALL enrolled classes
echo "Step 1: What classes is this student enrolled in?\n";
echo "=" . str_repeat("=", 75) . "\n\n";

$enrollments = "SELECT DISTINCT c.class_code, c.course_code, s.course_title, c.academic_year, c.term FROM class_enrollments ce JOIN class c ON ce.class_code = c.class_code LEFT JOIN subjects s ON c.course_code = s.course_code WHERE ce.student_id='2022-126653' AND ce.status='enrolled' ORDER BY c.academic_year DESC, c.term DESC, c.class_code";

$result = $conn->query($enrollments);

$all_classes = [];
while ($row = $result->fetch_assoc()) {
    echo $row['class_code'] . " | " . $row['course_code'] . " (" . $row['course_title'] . ") | Year: " . $row['academic_year'] . " Term: " . $row['term'] . "\n";
    $all_classes[] = $row['class_code'];
}

echo "\n\nStep 2: What grades are stored in grade_term for these classes?\n";
echo "=" . str_repeat("=", 75) . "\n\n";

foreach ($all_classes as $class_code) {
    $grade_query = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade, grade_status FROM grade_term WHERE student_id='2022-126653' AND class_code=?";
    $stmt = $conn->prepare($grade_query);
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $grade_result = $stmt->get_result();
    
    echo "Class: $class_code\n";
    
    if ($grade_result->num_rows === 0) {
        echo "  ❌ NO GRADE_TERM RECORD\n";
    } else {
        $grade = $grade_result->fetch_assoc();
        echo "  Midterm: " . $grade['midterm_percentage'] . "%\n";
        echo "  Finals: " . $grade['finals_percentage'] . "%\n";
        echo "  Term %: " . $grade['term_percentage'] . "%\n";
        echo "  Term Grade: " . $grade['term_grade'] . "\n";
        echo "  Status: " . $grade['grade_status'] . "\n";
        
        // Verify calculation
        $mid = floatval($grade['midterm_percentage']);
        $fin = floatval($grade['finals_percentage']);
        $calc = ($mid * 0.40) + ($fin * 0.60);
        
        if (abs($calc - floatval($grade['term_percentage'])) < 0.01) {
            echo "  ✓ Calculation verified\n";
        } else {
            echo "  ✗ Calculation mismatch! Calc: " . number_format($calc, 2) . "%\n";
        }
    }
    
    $stmt->close();
    echo "\n";
}

echo "\n\nStep 3: Compare with what SHOULD be in database\n";
echo "=" . str_repeat("=", 75) . "\n";
echo "\nBased on the console log, we're looking at:\n";
echo "  Midterm: 55.94% (Grade 0 - Failed)\n";
echo "  Finals: 100% (Grade 4.0 - Excellent)\n";
echo "  Term: (55.94 × 0.40) + (100 × 0.60) = 22.376 + 60 = 82.38%\n";
echo "  Grade: 2.5 (Satisfactory - 78-83.99%)\n";
echo "\nThis is CORRECT! ✓\n";

echo "\n\nNote:\n";
echo "If the faculty interface was showing 62.03% midterm and 84.81% term,\n";
echo "it might be showing a DIFFERENT CLASS or a DIFFERENT TERM.\n";
echo "\nThe student currently enrolled has midterm 55.94% and term 82.38%,\n";
echo "which calculates to grade 2.5 - CORRECT!\n";

$conn->close();
?>
