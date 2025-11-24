<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>🔄 Cache Clear & Database Verification</h2>";
echo "<pre>";

// First verify database has correct values
echo "Step 1: Verify Database Has Updated Values\n";
echo "=" . str_repeat("=", 70) . "\n";

$class_query = "SELECT DISTINCT c.class_code FROM class_enrollments ce JOIN class c ON ce.class_code = c.class_code WHERE ce.student_id='2022-126653' AND c.course_code LIKE '%CCPRGG1L%'";
$class_result = $conn->query($class_query);
$class_row = $class_result->fetch_assoc();
$class_code = $class_row['class_code'];

$query = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' AND class_code=?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "Class: $class_code\n";
echo "Midterm: " . $row['midterm_percentage'] . "%\n";
echo "Finals: " . $row['finals_percentage'] . "%\n";
echo "Term %: " . $row['term_percentage'] . "%\n";
echo "Term Grade: " . $row['term_grade'] . "\n\n";

if ($row['midterm_percentage'] == 62.03 && $row['term_percentage'] == 84.81 && $row['term_grade'] == 3.0) {
    echo "✅ Database has CORRECT updated values!\n\n";
} else {
    echo "⚠️ Database may not have been updated properly\n";
    echo "Expected: Midterm 62.03%, Term 84.81%, Grade 3.0\n\n";
}

echo "\nStep 2: Instructions for Cache Clear\n";
echo "=" . str_repeat("=", 70) . "\n\n";

echo "The student needs to:\n\n";
echo "1. Clear browser cache (Ctrl+Shift+Delete or Cmd+Shift+Delete)\n";
echo "2. Hard refresh the page (Ctrl+F5 or Cmd+Shift+R)\n";
echo "3. Or open an incognito/private window\n\n";

echo "This will force reload of:\n";
echo "  - student_dashboard.js\n";
echo "  - AJAX responses from get_grades.php\n\n";

echo "Step 3: Expected Result After Cache Clear\n";
echo "=" . str_repeat("=", 70) . "\n\n";

echo "The browser console should show:\n";
echo "  Grade data received: {\n";
echo "    midterm_percentage: 62.03,\n";
echo "    finals_percentage: 100,\n";
echo "    term_percentage: 84.81,\n";
echo "    term_grade: 3.0,\n";
echo "    ...\n";
echo "  }\n\n";

echo "And the dashboard should display:\n";
echo "  MIDTERM: 62.03% → Grade 0\n";
echo "  FINALS: 100% → Grade 4.0\n";
echo "  TERM GRADE: 3.0 (84.81%)\n\n";

$conn->close();
?>
