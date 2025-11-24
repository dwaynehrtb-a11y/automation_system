<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Recalculating Term Grades for Student 2022-126653</h2>";
echo "<pre>";

// Find all classes for student 2022-126653
$query = "SELECT DISTINCT class_code FROM grade_term WHERE student_id='2022-126653'";
$result = $conn->query($query);

if ($result->num_rows == 0) {
    echo "No classes found for this student.\n";
    exit;
}

$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row['class_code'];
}

echo "Found " . count($classes) . " classes for student 2022-126653:\n";
print_r($classes);

echo "\n\nRecalculating term percentages...\n";
echo "=====================================\n\n";

foreach ($classes as $class_code) {
    // Get current data
    $current_query = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' AND class_code=?";
    $stmt = $conn->prepare($current_query);
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $current_result = $stmt->get_result();
    $current = $current_result->fetch_assoc();
    $stmt->close();
    
    $mid = floatval($current['midterm_percentage'] ?? 0);
    $fin = floatval($current['finals_percentage'] ?? 0);
    $stored_term = floatval($current['term_percentage'] ?? 0);
    
    // Get weights
    $weights_query = "SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code=? LIMIT 1";
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
    
    // Calculate correct term percentage
    $calculated_term = ($mid * ($midterm_weight / 100.0)) + ($fin * ($finals_weight / 100.0));
    $calculated_term = round($calculated_term, 2);
    
    echo "Class: $class_code\n";
    echo "  Weights: Midterm $midterm_weight%, Finals $finals_weight%\n";
    echo "  Midterm: $mid%\n";
    echo "  Finals: $fin%\n";
    echo "  Stored Term: $stored_term%\n";
    echo "  Calculated Term: $calculated_term%\n";
    
    if (abs($calculated_term - $stored_term) > 0.01) {
        echo "  ⚠️  MISMATCH - updating database...\n";
        
        // Calculate new grade based on calculated term percentage
        $new_grade = null;
        if ($calculated_term >= 96.0) $new_grade = '4.0';
        elseif ($calculated_term >= 90.0) $new_grade = '3.5';
        elseif ($calculated_term >= 84.0) $new_grade = '3.0';
        elseif ($calculated_term >= 78.0) $new_grade = '2.5';
        elseif ($calculated_term >= 72.0) $new_grade = '2.0';
        elseif ($calculated_term >= 66.0) $new_grade = '1.5';
        elseif ($calculated_term >= 60.0) $new_grade = '1.0';
        
        echo "  New Grade: $new_grade\n";
        
        // Update the database
        $update_query = "UPDATE grade_term SET term_percentage=?, term_grade=? WHERE student_id='2022-126653' AND class_code=?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("dss", $calculated_term, $new_grade, $class_code);
        $update_stmt->execute();
        $update_stmt->close();
        
        echo "  ✅ Updated!\n\n";
    } else {
        echo "  ✅ Correct - no update needed\n\n";
    }
}

echo "Done!";
echo "</pre>";
?>
