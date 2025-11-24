<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>CCPRGG1L Grade Investigation for Student 2022-126653</h2>";
echo "<pre>";

// Find the CCPRGG1L class code(s)
echo "Step 1: Find all CCPRGG1L classes student is enrolled in\n";
echo "==================================================\n";
$query = "
    SELECT DISTINCT c.class_code, c.academic_year, c.term
    FROM class_enrollments ce
    JOIN class c ON ce.class_code = c.class_code
    WHERE ce.student_id='2022-126653' AND c.course_code LIKE '%CCPRGG1L%'
";
$result = $conn->query($query);
$ccprgg_codes = [];

if ($result->num_rows == 0) {
    echo "ERROR: No CCPRGG1L enrollment found!\n";
} else {
    while ($row = $result->fetch_assoc()) {
        $ccprgg_codes[] = $row['class_code'];
        echo "Found enrollment: " . $row['class_code'] . " (Year: " . $row['academic_year'] . ", Term: " . $row['term'] . ")\n";
    }
}

echo "\n\nStep 2: Check what grade_term records exist for these classes\n";
echo "==============================================================\n";

if (empty($ccprgg_codes)) {
    echo "Cannot proceed - no CCPRGG1L classes found.\n";
} else {
    foreach ($ccprgg_codes as $code) {
        echo "\nClass Code: $code\n";
        echo "---\n";
        
        $query = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade, grade_status FROM grade_term WHERE student_id='2022-126653' AND class_code=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $grade_result = $stmt->get_result();
        
        if ($grade_result->num_rows == 0) {
            echo "  ❌ NO RECORD FOUND IN grade_term!\n";
        } else {
            $row = $grade_result->fetch_assoc();
            echo "  Midterm %: " . $row['midterm_percentage'] . "\n";
            echo "  Finals %: " . $row['finals_percentage'] . "\n";
            echo "  Term %: " . $row['term_percentage'] . "\n";
            echo "  Term Grade: " . $row['term_grade'] . "\n";
            echo "  Grade Status: " . $row['grade_status'] . "\n";
            
            // Verify calculation
            $mid = floatval($row['midterm_percentage'] ?? 0);
            $fin = floatval($row['finals_percentage'] ?? 0);
            $calc = ($mid * 0.40) + ($fin * 0.60);
            $stored = floatval($row['term_percentage'] ?? 0);
            
            echo "\n  Calculation Check:\n";
            echo "  ($mid × 0.40) + ($fin × 0.60) = " . number_format($calc, 2) . "%\n";
            echo "  Stored in DB: " . number_format($stored, 2) . "%\n";
            
            if (abs($calc - $stored) < 0.01) {
                echo "  ✅ Calculation matches stored value\n";
            } else {
                echo "  ❌ MISMATCH! Calculation doesn't match stored value\n";
            }
        }
        
        $stmt->close();
    }
}

echo "\n\nStep 3: Compare with what faculty AJAX would compute\n";
echo "=====================================================\n";

if (!empty($ccprgg_codes)) {
    $code = $ccprgg_codes[0]; // Use first CCPRGG code
    
    // Get weights
    $weights_query = "SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code=?";
    $weights_stmt = $conn->prepare($weights_query);
    $weights_stmt->bind_param("s", $code);
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
    
    echo "For class: $code\n";
    echo "Weights: Midterm $midterm_weight%, Finals $finals_weight%\n";
}

echo "</pre>";
?>
