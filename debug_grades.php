<?php
require 'config/db.php';

$class_code = '24_T2_CCPRGG1L_INF222';

// Get a student for this class
echo "=== CLASS: $class_code ===\n";
$result = $conn->query("SELECT DISTINCT student_id FROM student_flexible_grades WHERE class_code='$class_code' LIMIT 1");
if($srow = $result->fetch_assoc()) {
    $student_id = $srow['student_id'];
    echo "Using student: $student_id\n";
    
    // Check grade_term
    echo "\n=== TERM GRADES ===\n";
    $result = $conn->query("SELECT * FROM grade_term WHERE student_id='$student_id' AND class_code='$class_code'");
    if($row = $result->fetch_assoc()) {
        echo "midterm_percentage: " . $row['midterm_percentage'] . "\n";
        echo "finals_percentage: " . $row['finals_percentage'] . "\n";
        echo "term_percentage: " . $row['term_percentage'] . "\n";
    } else {
        echo "No grade_term entry\n";
    }
    
    // Check grading components
    echo "\n=== GRADING COMPONENTS ===\n";
    $result = $conn->query("SELECT id, component_name, term_type, percentage FROM grading_components WHERE class_code='$class_code' ORDER BY term_type, component_name");
    while($row = $result->fetch_assoc()) {
        echo $row['id'] . ": " . $row['component_name'] . " (" . $row['term_type'] . ") - " . $row['percentage'] . "%\n";
    }
    
    // Check student flexible grades with calculations
    echo "\n=== STUDENT FLEXIBLE GRADES & CALCULATIONS ===\n";
    $result = $conn->query("
        SELECT gc.component_name, gc.term_type, gc.percentage,
               gcc.column_name, gcc.max_score, sfg.raw_score
        FROM grading_components gc
        LEFT JOIN grading_component_columns gcc ON gc.id = gcc.component_id
        LEFT JOIN student_flexible_grades sfg ON gcc.id = sfg.column_id AND sfg.student_id='$student_id'
        WHERE gc.class_code='$class_code'
        ORDER BY gc.term_type, gc.component_name, gcc.order_index
    ");
    
    $prev_comp = '';
    $comp_total = 0;
    $comp_max = 0;
    while($row = $result->fetch_assoc()) {
        $comp = $row['component_name'];
        if($comp != $prev_comp && $prev_comp != '') {
            if($comp_max > 0) {
                $pct = ($comp_total / $comp_max) * 100;
                echo "  => Subtotal: $comp_total / $comp_max = " . round($pct, 1) . "%\n";
            }
            $comp_total = 0;
            $comp_max = 0;
        }
        if($comp != $prev_comp) {
            echo "\n" . $comp . " (" . $row['term_type'] . " - " . $row['percentage'] . "%):\n";
            $prev_comp = $comp;
        }
        $score = $row['raw_score'] ?? 0;
        echo "  " . $row['column_name'] . ": $score / " . $row['max_score'] . "\n";
        $comp_total += $score;
        $comp_max += $row['max_score'];
    }
    if($comp_max > 0) {
        $pct = ($comp_total / $comp_max) * 100;
        echo "  => Subtotal: $comp_total / $comp_max = " . round($pct, 1) . "%\n";
    }
}
?>
