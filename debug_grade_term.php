<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');

echo "=== Checking grade_term for student 2022-126653 in CCPRGG1L ===\n";
$result = $conn->query("SELECT * FROM grade_term WHERE student_id='2022-126653' AND class_code LIKE '%CCPRGG1L%'");

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "Stored values in database:\n";
        echo "  midterm_percentage: " . $row['midterm_percentage'] . "%\n";
        echo "  finals_percentage: " . $row['finals_percentage'] . "%\n";
        echo "  term_percentage: " . $row['term_percentage'] . "%\n";
        echo "  term_grade: " . $row['term_grade'] . "\n";
        echo "  grade_status: " . $row['grade_status'] . "\n";
        
        // Calculate what it should be
        $calc = (floatval($row['midterm_percentage']) * 0.4) + (floatval($row['finals_percentage']) * 0.6);
        echo "\nCalculated term_percentage should be: " . number_format($calc, 2) . "%\n";
    }
} else {
    echo "No records found\n";
}

$conn->close();
?>
