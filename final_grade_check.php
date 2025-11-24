<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Final Verification - Student 2022-126653 Grades</h2>";
echo "<pre>";

$query = "SELECT DISTINCT class_code, midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' ORDER BY class_code";
$result = $conn->query($query);

if ($result->num_rows == 0) {
    echo "No classes found.\n";
} else {
    echo "Classes for student 2022-126653:\n\n";
    
    $all_correct = true;
    
    while ($row = $result->fetch_assoc()) {
        echo "Class: " . $row['class_code'] . "\n";
        echo "  Midterm: " . $row['midterm_percentage'] . "%\n";
        echo "  Finals: " . $row['finals_percentage'] . "%\n";
        echo "  Term %: " . $row['term_percentage'] . "%\n";
        echo "  Term Grade: " . $row['term_grade'] . "\n";
        
        // Verify calculation
        $mid = floatval($row['midterm_percentage']);
        $fin = floatval($row['finals_percentage']);
        $calc = ($mid * 0.40) + ($fin * 0.60);
        
        // Get correct grade
        if ($calc >= 96) $correct_grade = '4.0';
        elseif ($calc >= 90) $correct_grade = '3.5';
        elseif ($calc >= 84) $correct_grade = '3.0';
        elseif ($calc >= 78) $correct_grade = '2.5';
        elseif ($calc >= 72) $correct_grade = '2.0';
        elseif ($calc >= 66) $correct_grade = '1.5';
        elseif ($calc >= 60) $correct_grade = '1.0';
        else $correct_grade = null;
        
        echo "  Calculated Term %: " . number_format($calc, 2) . "%\n";
        echo "  Expected Grade: " . $correct_grade . "\n";
        
        // Check match
        if (abs($calc - floatval($row['term_percentage'])) < 0.01 && $correct_grade === $row['term_grade']) {
            echo "  ✅ CORRECT\n";
        } else {
            echo "  ❌ MISMATCH\n";
            $all_correct = false;
        }
        
        echo "\n";
    }
    
    if ($all_correct) {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "✅ ALL GRADES ARE NOW CORRECT!\n";
        echo str_repeat("=", 80) . "\n";
    } else {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "⚠️  SOME GRADES STILL HAVE MISMATCHES\n";
        echo str_repeat("=", 80) . "\n";
    }
}

echo "</pre>";
?>
