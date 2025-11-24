<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Direct Database Fix for Student 2022-126653</h2>";
echo "<pre>";

// First, let's see what's in the database NOW
echo "Current Database State:\n";
echo "=======================\n\n";

$query = "SELECT class_code, midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653'";
$result = $conn->query($query);

$classes_to_fix = [];

while ($row = $result->fetch_assoc()) {
    echo "Class: " . $row['class_code'] . "\n";
    echo "  Midterm: " . $row['midterm_percentage'] . "%\n";
    echo "  Finals: " . $row['finals_percentage'] . "%\n";
    echo "  Term %: " . $row['term_percentage'] . "%\n";
    echo "  Term Grade: " . $row['term_grade'] . "\n";
    
    // Calculate what it SHOULD be
    $mid = floatval($row['midterm_percentage']);
    $fin = floatval($row['finals_percentage']);
    $calc_term = ($mid * 0.40) + ($fin * 0.60);
    
    // Calculate correct grade
    if ($calc_term >= 96) $correct_grade = '4.0';
    elseif ($calc_term >= 90) $correct_grade = '3.5';
    elseif ($calc_term >= 84) $correct_grade = '3.0';
    elseif ($calc_term >= 78) $correct_grade = '2.5';
    elseif ($calc_term >= 72) $correct_grade = '2.0';
    elseif ($calc_term >= 66) $correct_grade = '1.5';
    elseif ($calc_term >= 60) $correct_grade = '1.0';
    else $correct_grade = '0.0';
    
    echo "  Should be: " . number_format($calc_term, 2) . "% → Grade " . $correct_grade . "\n";
    
    if ($calc_term != floatval($row['term_percentage']) || $correct_grade !== $row['term_grade']) {
        echo "  ⚠️ NEEDS FIX\n";
        $classes_to_fix[] = [
            'class_code' => $row['class_code'],
            'correct_term' => $calc_term,
            'correct_grade' => $correct_grade
        ];
    } else {
        echo "  ✓ OK\n";
    }
    
    echo "\n";
}

// Now fix any that need it
if (!empty($classes_to_fix)) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "APPLYING FIXES\n";
    echo str_repeat("=", 80) . "\n\n";
    
    foreach ($classes_to_fix as $fix) {
        echo "Fixing " . $fix['class_code'] . "...\n";
        
        $update = "UPDATE grade_term SET term_percentage=?, term_grade=? WHERE student_id='2022-126653' AND class_code=?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("dss", $fix['correct_term'], $fix['correct_grade'], $fix['class_code']);
        
        if ($stmt->execute()) {
            echo "  ✓ Updated to: " . number_format($fix['correct_term'], 2) . "% → Grade " . $fix['correct_grade'] . "\n";
        } else {
            echo "  ✗ ERROR: " . $stmt->error . "\n";
        }
        $stmt->close();
    }
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "VERIFICATION AFTER FIX\n";
    echo str_repeat("=", 80) . "\n\n";
    
    // Re-query to verify
    $verify = $conn->query("SELECT class_code, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653'");
    
    while ($row = $verify->fetch_assoc()) {
        echo $row['class_code'] . ": " . $row['term_percentage'] . "% → Grade " . $row['term_grade'] . "\n";
    }
} else {
    echo "\n✓ All grades are already correct!\n";
}

echo "</pre>";
?>
