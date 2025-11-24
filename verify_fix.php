<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Verification: Student 2022-126653 Grades After Fix</h2>";
echo "<pre>";

$query = "SELECT class_code, midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' ORDER BY class_code";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    echo "No records found!\n";
} else {
    echo "Current database state:\n\n";
    
    $all_correct = true;
    while ($row = $result->fetch_assoc()) {
        $mid = floatval($row['midterm_percentage']);
        $fin = floatval($row['finals_percentage']);
        $calc = ($mid * 0.40) + ($fin * 0.60);
        
        // Calculate correct grade
        if ($calc >= 96) $correct_grade = '4.0';
        elseif ($calc >= 90) $correct_grade = '3.5';
        elseif ($calc >= 84) $correct_grade = '3.0';
        elseif ($calc >= 78) $correct_grade = '2.5';
        elseif ($calc >= 72) $correct_grade = '2.0';
        elseif ($calc >= 66) $correct_grade = '1.5';
        elseif ($calc >= 60) $correct_grade = '1.0';
        else $correct_grade = '0.0';
        
        $stored_term = floatval($row['term_percentage']);
        $stored_grade = $row['term_grade'];
        
        $term_ok = abs($calc - $stored_term) < 0.01;
        $grade_ok = $correct_grade === $stored_grade;
        
        echo "Class: " . $row['class_code'] . "\n";
        echo "  Midterm: " . $mid . "% | Finals: " . $fin . "%\n";
        echo "  Stored: " . $stored_term . "% → Grade " . $stored_grade . "\n";
        echo "  Calculated: " . number_format($calc, 2) . "% → Grade " . $correct_grade . "\n";
        
        if ($term_ok && $grade_ok) {
            echo "  ✅ CORRECT\n\n";
        } else {
            echo "  ❌ INCORRECT\n\n";
            $all_correct = false;
        }
    }
    
    if ($all_correct) {
        echo str_repeat("=", 70) . "\n";
        echo "✅ ALL GRADES ARE NOW CORRECT!\n";
        echo str_repeat("=", 70) . "\n";
        echo "\nThe student should now see correct grades in the dashboard.\n";
        echo "Refresh the student dashboard page to see the updated grades.\n";
    } else {
        echo str_repeat("=", 70) . "\n";
        echo "⚠️ SOME GRADES STILL NEED CORRECTION\n";
        echo str_repeat("=", 70) . "\n";
    }
}

$conn->close();
?>
