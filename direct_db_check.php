<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Direct Database Check</h2>";
echo "<pre>";

// Query EXACTLY what the student AJAX would query
$query = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade, grade_status, is_encrypted FROM grade_term WHERE student_id = '2022-126653' AND class_code LIKE '%CCPRGG1L%'";
$result = $conn->query($query);

echo "Query: " . $query . "\n\n";

if ($result->num_rows === 0) {
    echo "❌ No records found!\n";
} else {
    echo "Found " . $result->num_rows . " record(s):\n\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "Record Details:\n";
        echo "  Midterm: " . $row['midterm_percentage'] . "%\n";
        echo "  Finals: " . $row['finals_percentage'] . "%\n";
        echo "  Term %: " . $row['term_percentage'] . "%\n";
        echo "  Term Grade: " . $row['term_grade'] . "\n";
        echo "  Status: " . $row['grade_status'] . "\n";
        echo "  Encrypted: " . $row['is_encrypted'] . "\n";
        
        // Check what it should be
        if ($row['midterm_percentage'] == 62.03 && $row['term_percentage'] == 84.81 && $row['term_grade'] == 3.0) {
            echo "\n✅ CORRECT VALUES!\n";
        } elseif ($row['midterm_percentage'] == 55.94 && $row['term_percentage'] == 82.38 && $row['term_grade'] == 2.5) {
            echo "\n❌ OLD VALUES - Database not updated!\n";
        } else {
            echo "\n⚠️ Unexpected values\n";
        }
    }
}

echo "\n\nAlternative Query (exact class code):\n";
echo "=" . str_repeat("=", 70) . "\n\n";

// Try with exact class code match
$query2 = "SELECT class_code, midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' ORDER BY class_code";
$result2 = $conn->query($query2);

echo "All classes for student 2022-126653:\n";
while ($row = $result2->fetch_assoc()) {
    echo "  " . $row['class_code'] . ": Mid " . $row['midterm_percentage'] . "%, Finals " . $row['finals_percentage'] . "%, Term " . $row['term_percentage'] . "% (Grade " . $row['term_grade'] . ")\n";
}

$conn->close();
?>
