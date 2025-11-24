<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Investigation: Midterm Percentage Discrepancy</h2>";
echo "<pre>";

// Find the CCPRGG1L class code
$class_query = "SELECT DISTINCT c.class_code FROM class_enrollments ce JOIN class c ON ce.class_code = c.class_code WHERE ce.student_id='2022-126653' AND c.course_code LIKE '%CCPRGG1L%'";
$class_result = $conn->query($class_query);

if ($class_result->num_rows === 0) {
    echo "No CCPRGG1L class found!\n";
    exit;
}

$class_row = $class_result->fetch_assoc();
$class_code = $class_row['class_code'];

echo "Class: $class_code\n\n";

// Get current grade_term values
echo "=== Current grade_term Record ===\n";
$grade_query = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' AND class_code=?";
$grade_stmt = $conn->prepare($grade_query);
$grade_stmt->bind_param("s", $class_code);
$grade_stmt->execute();
$grade_result = $grade_stmt->get_result();
$grade_row = $grade_result->fetch_assoc();
$grade_stmt->close();

echo "Stored Midterm: " . $grade_row['midterm_percentage'] . "%\n";
echo "Stored Finals: " . $grade_row['finals_percentage'] . "%\n";
echo "Stored Term %: " . $grade_row['term_percentage'] . "%\n";
echo "Stored Term Grade: " . $grade_row['term_grade'] . "\n\n";

// Now calculate what midterm SHOULD be based on components
echo "=== What Midterm SHOULD Be (from components) ===\n";

$comp_query = "
SELECT gc.id, gc.component_name, gc.percentage,
       SUM(CASE WHEN sfg.raw_score IS NOT NULL THEN sfg.raw_score ELSE 0 END) as total_score,
       SUM(CASE WHEN sfg.raw_score IS NOT NULL THEN gcc.max_score ELSE 0 END) as total_max
FROM grading_components gc
LEFT JOIN grading_component_columns gcc ON gc.id = gcc.component_id
LEFT JOIN student_flexible_grades sfg ON gcc.id = sfg.column_id 
    AND sfg.class_code = ? AND sfg.student_id = '2022-126653'
WHERE gc.class_code = ? AND gc.term_type = 'midterm'
GROUP BY gc.id, gc.component_name, gc.percentage
ORDER BY gc.order_index
";

$comp_stmt = $conn->prepare($comp_query);
$comp_stmt->bind_param("ss", $class_code, $class_code);
$comp_stmt->execute();
$comp_result = $comp_stmt->get_result();

$midterm_total_weight = 0;
$midterm_weighted_pct = 0;

while ($comp = $comp_result->fetch_assoc()) {
    $comp_weight = floatval($comp['percentage']);
    $comp_score = floatval($comp['total_score']);
    $comp_max = floatval($comp['total_max']);
    
    if ($comp_max > 0) {
        $comp_pct = ($comp_score / $comp_max) * 100;
        $weighted = ($comp_pct * $comp_weight) / 100;
        
        echo $comp['component_name'] . ": " . number_format($comp_score, 2) . "/" . number_format($comp_max, 2) . " = " . number_format($comp_pct, 2) . "% × " . $comp_weight . "% = " . number_format($weighted, 2) . "\n";
        
        $midterm_weighted_pct += $weighted;
        $midterm_total_weight += $comp_weight;
    }
}

if ($midterm_total_weight > 0) {
    $calculated_midterm = $midterm_weighted_pct;
    echo "\nCalculated Midterm: " . number_format($calculated_midterm, 2) . "%\n";
} else {
    echo "\nNo component data found!\n";
    $calculated_midterm = null;
}

$comp_stmt->close();

echo "\n" . str_repeat("=", 70) . "\n";
echo "COMPARISON\n";
echo str_repeat("=", 70) . "\n";
echo "Stored in DB: " . $grade_row['midterm_percentage'] . "%\n";
if ($calculated_midterm !== null) {
    echo "Should be (from components): " . number_format($calculated_midterm, 2) . "%\n";
    
    if (abs($calculated_midterm - floatval($grade_row['midterm_percentage'])) > 0.5) {
        echo "\n⚠️ MAJOR DISCREPANCY FOUND!\n";
        echo "Difference: " . number_format(abs($calculated_midterm - floatval($grade_row['midterm_percentage'])), 2) . "%\n";
        echo "\nFaculty is displaying: 62.03%\n";
        echo "Database stores: " . $grade_row['midterm_percentage'] . "%\n";
        echo "\nThis explains why student sees wrong term grade!\n";
    }
}

$conn->close();
?>
