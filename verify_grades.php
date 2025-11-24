<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Verify Grade Data for Student 2022-126653</h2>";
echo "<pre>";

// Get all class codes for this student
$query = "SELECT DISTINCT class_code, midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' ORDER BY class_code";
$result = $conn->query($query);

if ($result->num_rows == 0) {
    echo "No records found.\n";
} else {
    echo "Student 2022-126653 has " . $result->num_rows . " classes:\n\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "Class: " . $row['class_code'] . "\n";
        echo "  Midterm: " . $row['midterm_percentage'] . "%\n";
        echo "  Finals: " . $row['finals_percentage'] . "%\n";
        echo "  Stored Term %: " . $row['term_percentage'] . "%\n";
        echo "  Stored Term Grade: " . $row['term_grade'] . "\n";
        
        // Calculate what it should be
        $mid = floatval($row['midterm_percentage']);
        $fin = floatval($row['finals_percentage']);
        $calc = ($mid * 0.40) + ($fin * 0.60);
        
        echo "  Calculated Term %: " . number_format($calc, 2) . "%\n";
        
        // Calculate what grade should be with correct scale
        if ($calc >= 96) $grade = '4.0';
        elseif ($calc >= 90) $grade = '3.5';
        elseif ($calc >= 84) $grade = '3.0';
        elseif ($calc >= 78) $grade = '2.5';
        elseif ($calc >= 72) $grade = '2.0';
        elseif ($calc >= 66) $grade = '1.5';
        elseif ($calc >= 60) $grade = '1.0';
        else $grade = '0.0';
        
        echo "  Calculated Grade (correct scale): " . $grade . "\n";
        
        // Check for match
        if (abs($calc - floatval($row['term_percentage'])) < 0.01) {
            echo "  ✅ Term % matches\n";
        } else {
            echo "  ⚠️  Term % mismatch! Stored: " . $row['term_percentage'] . "%, Calculated: " . number_format($calc, 2) . "%\n";
        }
        
        if ($grade === $row['term_grade']) {
            echo "  ✅ Grade matches\n";
        } else {
            echo "  ⚠️  Grade mismatch! Stored: " . $row['term_grade'] . ", Calculated: " . $grade . "\n";
        }
        
        echo "\n";
    }
}

echo "</pre>";
?>
