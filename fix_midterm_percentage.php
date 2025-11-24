<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Fix Midterm Percentage to Match Component Data</h2>";
echo "<pre>";

// Find CCPRGG1L class
$class_query = "SELECT DISTINCT c.class_code FROM class_enrollments ce JOIN class c ON ce.class_code = c.class_code WHERE ce.student_id='2022-126653' AND c.course_code LIKE '%CCPRGG1L%'";
$class_result = $conn->query($class_query);
$class_row = $class_result->fetch_assoc();
$class_code = $class_row['class_code'];

echo "Class: $class_code\n\n";

// Get current stored values
$current_query = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' AND class_code=?";
$current_stmt = $conn->prepare($current_query);
$current_stmt->bind_param("s", $class_code);
$current_stmt->execute();
$current_row = $current_stmt->get_result()->fetch_assoc();
$current_stmt->close();

echo "Current stored values:\n";
echo "  Midterm: " . $current_row['midterm_percentage'] . "%\n";
echo "  Finals: " . $current_row['finals_percentage'] . "%\n";
echo "  Term %: " . $current_row['term_percentage'] . "%\n";
echo "  Term Grade: " . $current_row['term_grade'] . "\n\n";

// Calculate what midterm should be
echo "Calculating midterm from components...\n";

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

$midterm_weighted = 0;
$midterm_weight = 0;

while ($comp = $comp_result->fetch_assoc()) {
    $weight = floatval($comp['percentage']);
    $score = floatval($comp['total_score']);
    $max = floatval($comp['total_max']);
    
    if ($max > 0) {
        $pct = ($score / $max) * 100;
        $weighted = ($pct * $weight) / 100;
        $midterm_weighted += $weighted;
        $midterm_weight += $weight;
        
        echo "  " . $comp['component_name'] . ": " . number_format($pct, 2) . "% × " . $weight . "% = " . number_format($weighted, 2) . "\n";
    }
}

$comp_stmt->close();

if ($midterm_weight > 0) {
    $new_midterm = $midterm_weighted;
} else {
    $new_midterm = 0;
}

echo "\nCalculated Midterm: " . number_format($new_midterm, 2) . "%\n\n";

// Get finals (should remain 100%)
$new_finals = floatval($current_row['finals_percentage']);
echo "Finals: " . $new_finals . "% (unchanged)\n\n";

// Calculate new term percentage
$new_term = ($new_midterm * 0.40) + ($new_finals * 0.60);
echo "New Term %: (" . number_format($new_midterm, 2) . " × 0.40) + (" . $new_finals . " × 0.60) = " . number_format($new_term, 2) . "%\n\n";

// Calculate new grade
if ($new_term >= 96) $new_grade = '4.0';
elseif ($new_term >= 90) $new_grade = '3.5';
elseif ($new_term >= 84) $new_grade = '3.0';
elseif ($new_term >= 78) $new_grade = '2.5';
elseif ($new_term >= 72) $new_grade = '2.0';
elseif ($new_term >= 66) $new_grade = '1.5';
elseif ($new_term >= 60) $new_grade = '1.0';
else $new_grade = '0.0';

echo "New Grade: " . $new_grade . "\n\n";

echo str_repeat("=", 70) . "\n";
echo "UPDATING DATABASE\n";
echo str_repeat("=", 70) . "\n\n";

echo "Changes:\n";
echo "  Midterm: " . $current_row['midterm_percentage'] . "% → " . number_format($new_midterm, 2) . "%\n";
echo "  Finals: " . $current_row['finals_percentage'] . "% → " . $new_finals . "%\n";
echo "  Term %: " . $current_row['term_percentage'] . "% → " . number_format($new_term, 2) . "%\n";
echo "  Term Grade: " . $current_row['term_grade'] . " → " . $new_grade . "\n\n";

// Update database
$update_query = "UPDATE grade_term SET midterm_percentage=?, finals_percentage=?, term_percentage=?, term_grade=? WHERE student_id='2022-126653' AND class_code=?";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("dddss", $new_midterm, $new_finals, $new_term, $new_grade, $class_code);

if ($update_stmt->execute()) {
    echo "✅ Database updated successfully!\n\n";
    echo "NEW VALUES:\n";
    echo "  Midterm: " . number_format($new_midterm, 2) . "% → Grade " . ($new_midterm >= 84 ? '3.0' : ($new_midterm >= 78 ? '2.5' : ($new_midterm >= 72 ? '2.0' : ($new_midterm >= 66 ? '1.5' : ($new_midterm >= 60 ? '1.0' : '0.0'))))) . "\n";
    echo "  Finals: " . $new_finals . "% → Grade 4.0\n";
    echo "  Term: " . number_format($new_term, 2) . "% → Grade " . $new_grade . "\n";
} else {
    echo "❌ Update failed: " . $update_stmt->error . "\n";
}

$update_stmt->close();
$conn->close();
?>
