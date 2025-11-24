<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Recalculate All Term Grades from Components</h2>";
echo "<pre>";

// For now, focus on classes that have student 2022-126653

// Get all classes
$classes_query = "SELECT DISTINCT gt.class_code FROM grade_term gt WHERE gt.student_id='2022-126653'";
$classes_result = $conn->query($classes_query);

if ($classes_result->num_rows == 0) {
    echo "No classes found for student 2022-126653\n";
    exit;
}

echo "Found " . $classes_result->num_rows . " classes for student 2022-126653\n\n";

while ($class_row = $classes_result->fetch_assoc()) {
    $class_code = $class_row['class_code'];
    
    echo "=" . str_repeat("=", 80) . "\n";
    echo "Class: $class_code\n";
    echo "=" . str_repeat("=", 80) . "\n";
    
    // Get current grade_term values
    $current_query = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' AND class_code=?";
    $current_stmt = $conn->prepare($current_query);
    $current_stmt->bind_param("s", $class_code);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    $current_row = $current_result->fetch_assoc();
    $current_stmt->close();
    
    echo "Current DB values:\n";
    echo "  Midterm: " . $current_row['midterm_percentage'] . "%\n";
    echo "  Finals: " . $current_row['finals_percentage'] . "%\n";
    echo "  Term %: " . $current_row['term_percentage'] . "%\n";
    echo "  Term Grade: " . $current_row['term_grade'] . "\n\n";
    
    // Recalculate based on components
    echo "Recalculating from components...\n";
    
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
    
    // Get midterm components and scores
    $midterm_query = "
        SELECT gc.id, gc.component_name, gc.percentage, 
               COALESCE(SUM(sfg.raw_score), 0) as total_score,
               COUNT(gcc.id) as column_count,
               SUM(gcc.max_score) as total_max_score
        FROM grading_components gc
        LEFT JOIN grading_component_columns gcc ON gc.id = gcc.component_id
        LEFT JOIN student_flexible_grades sfg ON gcc.id = sfg.column_id 
            AND sfg.class_code = gc.class_code 
            AND sfg.student_id = '2022-126653'
        WHERE gc.class_code = ? AND gc.term_type = 'midterm'
        GROUP BY gc.id, gc.component_name, gc.percentage
        ORDER BY gc.order_index
    ";
    $midterm_stmt = $conn->prepare($midterm_query);
    $midterm_stmt->bind_param("s", $class_code);
    $midterm_stmt->execute();
    $midterm_result = $midterm_stmt->get_result();
    
    $midterm_pct = 0;
    $midterm_total_weight = 0;
    $midterm_total_score = 0;
    
    echo "  Midterm components:\n";
    while ($comp = $midterm_result->fetch_assoc()) {
        $comp_max = floatval($comp['total_max_score'] ?? 0);
        $comp_score = floatval($comp['total_score'] ?? 0);
        $comp_weight = floatval($comp['percentage'] ?? 0);
        
        if ($comp_max > 0) {
            $comp_pct = ($comp_score / $comp_max) * 100;
            $weighted = $comp_pct * ($comp_weight / 100);
            $midterm_pct += $weighted;
            $midterm_total_weight += $comp_weight;
            $midterm_total_score += $comp_score;
            
            echo "    " . $comp['component_name'] . ": " . number_format($comp_score, 2) . "/" . number_format($comp_max, 2) . " (" . number_format($comp_pct, 2) . "%) × " . $comp_weight . "% = " . number_format($weighted, 2) . "\n";
        }
    }
    $midterm_stmt->close();
    
    if ($midterm_total_weight > 0) {
        $midterm_pct = ($midterm_pct / $midterm_total_weight) * 100;
    } else {
        $midterm_pct = 0;
    }
    
    echo "  Midterm Total: " . number_format($midterm_pct, 2) . "%\n\n";
    
    // Get finals components and scores  
    $finals_query = "
        SELECT gc.id, gc.component_name, gc.percentage,
               COALESCE(SUM(sfg.raw_score), 0) as total_score,
               COUNT(gcc.id) as column_count,
               SUM(gcc.max_score) as total_max_score
        FROM grading_components gc
        LEFT JOIN grading_component_columns gcc ON gc.id = gcc.component_id
        LEFT JOIN student_flexible_grades sfg ON gcc.id = sfg.column_id 
            AND sfg.class_code = gc.class_code 
            AND sfg.student_id = '2022-126653'
        WHERE gc.class_code = ? AND gc.term_type = 'finals'
        GROUP BY gc.id, gc.component_name, gc.percentage
        ORDER BY gc.order_index
    ";
    $finals_stmt = $conn->prepare($finals_query);
    $finals_stmt->bind_param("s", $class_code);
    $finals_stmt->execute();
    $finals_result = $finals_stmt->get_result();
    
    $finals_pct = 0;
    $finals_total_weight = 0;
    
    echo "  Finals components:\n";
    while ($comp = $finals_result->fetch_assoc()) {
        $comp_max = floatval($comp['total_max_score'] ?? 0);
        $comp_score = floatval($comp['total_score'] ?? 0);
        $comp_weight = floatval($comp['percentage'] ?? 0);
        
        if ($comp_max > 0) {
            $comp_pct = ($comp_score / $comp_max) * 100;
            $weighted = $comp_pct * ($comp_weight / 100);
            $finals_pct += $weighted;
            $finals_total_weight += $comp_weight;
            
            echo "    " . $comp['component_name'] . ": " . number_format($comp_score, 2) . "/" . number_format($comp_max, 2) . " (" . number_format($comp_pct, 2) . "%) × " . $comp_weight . "% = " . number_format($weighted, 2) . "\n";
        }
    }
    $finals_stmt->close();
    
    if ($finals_total_weight > 0) {
        $finals_pct = ($finals_pct / $finals_total_weight) * 100;
    } else {
        $finals_pct = 0;
    }
    
    echo "  Finals Total: " . number_format($finals_pct, 2) . "%\n\n";
    
    // Calculate term percentage
    $term_pct = ($midterm_pct * ($midterm_weight / 100)) + ($finals_pct * ($finals_weight / 100));
    
    // Calculate term grade
    $term_grade = null;
    if ($term_pct >= 96) $term_grade = '4.0';
    elseif ($term_pct >= 90) $term_grade = '3.5';
    elseif ($term_pct >= 84) $term_grade = '3.0';
    elseif ($term_pct >= 78) $term_grade = '2.5';
    elseif ($term_pct >= 72) $term_grade = '2.0';
    elseif ($term_pct >= 66) $term_grade = '1.5';
    elseif ($term_pct >= 60) $term_grade = '1.0';
    
    echo "Calculated values:\n";
    echo "  Midterm: " . number_format($midterm_pct, 2) . "%\n";
    echo "  Finals: " . number_format($finals_pct, 2) . "%\n";
    echo "  Term %: " . number_format($term_pct, 2) . "%\n";
    echo "  Term Grade: " . $term_grade . "\n";
    
    // Compare
    $current_term = floatval($current_row['term_percentage'] ?? 0);
    $current_grade = $current_row['term_grade'];
    
    echo "\nComparison:\n";
    if (abs($term_pct - $current_term) > 0.01 || $term_grade !== $current_grade) {
        echo "  ⚠️  VALUES DIFFER - UPDATING\n";
        
        // Update database
        $update_query = "UPDATE grade_term SET midterm_percentage=?, finals_percentage=?, term_percentage=?, term_grade=? WHERE student_id='2022-126653' AND class_code=?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("dddss", $midterm_pct, $finals_pct, $term_pct, $term_grade, $class_code);
        if ($update_stmt->execute()) {
            echo "  ✅ Updated successfully\n";
        } else {
            echo "  ❌ Update failed: " . $update_stmt->error . "\n";
        }
        $update_stmt->close();
    } else {
        echo "  ✅ Values match - no update needed\n";
    }
    
    echo "\n";
}

echo "\nDone!\n";
echo "</pre>";
?>
