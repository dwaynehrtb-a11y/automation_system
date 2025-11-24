<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Fix All Student Grade Calculations</h2>";
echo "<pre>";

// Get all unique classes
$classes_query = "SELECT DISTINCT class_code FROM grade_term ORDER BY class_code";
$classes_result = $conn->query($classes_query);

$total_classes = $classes_result->num_rows;
$classes_fixed = 0;
$records_updated = 0;

echo "Processing $total_classes classes...\n\n";

while ($class_row = $classes_result->fetch_assoc()) {
    $class_code = $class_row['class_code'];
    
    // Get weights
    $weights_query = "SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code=?";
    $weights_stmt = $conn->prepare($weights_query);
    $weights_stmt->bind_param("s", $class_code);
    $weights_stmt->execute();
    $weights_result = $weights_stmt->get_result();
    
    $midterm_weight = 40;
    $finals_weight = 60;
    if ($weights_result->num_rows > 0) {
        $weights_row = $weights_result->fetch_assoc();
        $midterm_weight = floatval($weights_row['midterm_weight']);
        $finals_weight = floatval($weights_row['finals_weight']);
    }
    $weights_stmt->close();
    
    // Get all students in this class with grades
    $students_query = "SELECT DISTINCT student_id FROM grade_term WHERE class_code=?";
    $students_stmt = $conn->prepare($students_query);
    $students_stmt->bind_param("s", $class_code);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();
    
    $class_fixed = false;
    
    while ($student_row = $students_result->fetch_assoc()) {
        $student_id = $student_row['student_id'];
        
        // Get current values
        $current_query = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id=? AND class_code=?";
        $current_stmt = $conn->prepare($current_query);
        $current_stmt->bind_param("ss", $student_id, $class_code);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        $current_row = $current_result->fetch_assoc();
        $current_stmt->close();
        
        $mid = floatval($current_row['midterm_percentage'] ?? 0);
        $fin = floatval($current_row['finals_percentage'] ?? 0);
        $stored_term = floatval($current_row['term_percentage'] ?? 0);
        
        // Calculate correct term percentage
        $calc_term = ($mid * ($midterm_weight / 100)) + ($fin * ($finals_weight / 100));
        $calc_term = round($calc_term, 2);
        
        // Calculate correct grade
        $calc_grade = null;
        if ($calc_term >= 96) $calc_grade = '4.0';
        elseif ($calc_term >= 90) $calc_grade = '3.5';
        elseif ($calc_term >= 84) $calc_grade = '3.0';
        elseif ($calc_term >= 78) $calc_grade = '2.5';
        elseif ($calc_term >= 72) $calc_grade = '2.0';
        elseif ($calc_term >= 66) $calc_grade = '1.5';
        elseif ($calc_term >= 60) $calc_grade = '1.0';
        else $calc_grade = null;
        
        // Check for mismatch
        $needs_update = false;
        if (abs($calc_term - $stored_term) > 0.01) {
            $needs_update = true;
        }
        if ($calc_grade !== $current_row['term_grade']) {
            $needs_update = true;
        }
        
        if ($needs_update) {
            // Update the database
            $update_query = "UPDATE grade_term SET term_percentage=?, term_grade=? WHERE student_id=? AND class_code=?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("dsss", $calc_term, $calc_grade, $student_id, $class_code);
            if ($update_stmt->execute()) {
                $records_updated++;
                $class_fixed = true;
            }
            $update_stmt->close();
        }
    }
    $students_stmt->close();
    
    if ($class_fixed) {
        $classes_fixed++;
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "RESULTS\n";
echo str_repeat("=", 80) . "\n";
echo "Total classes processed: $total_classes\n";
echo "Classes with corrections: $classes_fixed\n";
echo "Records updated: $records_updated\n";

if ($records_updated > 0) {
    echo "\n✅ Grade calculations have been corrected!\n";
} else {
    echo "\n✅ All grades are already correct!\n";
}

echo "</pre>";
?>
