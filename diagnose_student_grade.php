<?php
require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== DIAGNOSING STUDENT GRADE ISSUE ===\n\n";

// Check grade_term table
$result = $conn->query("
    SELECT id, student_id, class_code, term_percentage, term_grade, grade_status 
    FROM grade_term 
    WHERE student_id='$student_id' AND class_code='$class_code'
");

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Database grade_term record:\n";
    echo "  grade_status: " . ($row['grade_status'] ?? 'NULL') . "\n";
    echo "  term_grade: " . ($row['term_grade'] ?? 'NULL') . "\n";
    echo "  term_percentage: " . ($row['term_percentage'] ?? 'NULL') . "\n";
} else {
    echo "No grade_term record found!\n";
}

// Check what get_grades.php would return
echo "\n=== SIMULATING get_grades.php RESPONSE ===\n";

// Check GradesModel
require_once 'includes/GradesModel.php';
$gm = new GradesModel($conn);

try {
    $response = $gm->getStudentGradeSummary($student_id, $class_code);
    echo "GradesModel Response:\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    echo "GradesModel Error: " . $e->getMessage() . "\n";
}

?>
