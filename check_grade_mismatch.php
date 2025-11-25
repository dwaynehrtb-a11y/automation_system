<?php
require_once 'config/db.php';
require_once 'config/encryption.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== GRADE VALUE DEBUG ===\n\n";

// Check what's actually stored in database
$stmt = $conn->prepare("
    SELECT 
        id,
        term_grade,
        midterm_percentage,
        finals_percentage,
        term_percentage,
        is_encrypted,
        grade_status
    FROM grade_term 
    WHERE student_id = ? AND class_code = ?
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "DATABASE VALUES:\n";
    echo "  term_grade: " . $row['term_grade'] . "\n";
    echo "  midterm_percentage: " . $row['midterm_percentage'] . "\n";
    echo "  finals_percentage: " . $row['finals_percentage'] . "\n";
    echo "  term_percentage: " . $row['term_percentage'] . "\n";
    echo "  is_encrypted: " . $row['is_encrypted'] . "\n";
    echo "  grade_status: " . $row['grade_status'] . "\n";
    
    echo "\nFACULTY SHOWS:\n";
    echo "  93.33% → 3.5\n";
    echo "  90.00% → 3.5\n";
    echo "  91.33% → 3.5\n";
    echo "  Status: Passed\n";
    
    echo "\nSTUDENT API RETURNS (console log):\n";
    echo "  midterm_grade: 0\n";
    echo "  midterm_percentage: 23.33\n";
    echo "  finals_grade: 3.5\n";
    echo "  finals_percentage: 90\n";
    echo "  term_grade: 1\n";
    echo "  term_percentage: 63.33\n";
    
    echo "\n=== ISSUE ===\n";
    echo "Database has different percentages than what faculty displayed!\n";
    echo "Midterm: Faculty=93.33% but DB=" . $row['midterm_percentage'] . "%\n";
    echo "Term Grade: Faculty=3.5 but API returns=1\n";
    
} else {
    echo "NO GRADE RECORD FOUND\n";
}
$stmt->close();

?>
