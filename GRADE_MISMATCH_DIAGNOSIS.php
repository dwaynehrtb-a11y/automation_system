<?php
/**
 * DIAGNOSIS: Grade Mismatch Between Faculty View and Student View
 */
require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== GRADE MISMATCH DIAGNOSIS ===\n\n";

// Get actual database values
$stmt = $conn->prepare("
    SELECT 
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
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo "No grade record found\n";
    exit;
}

echo "WHAT FACULTY DISPLAYS:\n";
echo "  Midterm: 93.33% → 3.5\n";
echo "  Finals: 90.00% → 3.5\n";
echo "  Term: 91.33% → 3.5\n";
echo "  Status: Passed\n\n";

echo "WHAT DATABASE ACTUALLY HAS:\n";
echo "  term_grade: " . $row['term_grade'] . "\n";
echo "  midterm_percentage: " . $row['midterm_percentage'] . "\n";
echo "  finals_percentage: " . $row['finals_percentage'] . "\n";
echo "  term_percentage: " . $row['term_percentage'] . "\n";
echo "  is_encrypted: " . $row['is_encrypted'] . "\n";
echo "  grade_status: " . $row['grade_status'] . "\n\n";

echo "WHAT STUDENT SEES (from API):\n";
echo "  Midterm: 23.33% → 0.0 (converted)\n";
echo "  Finals: 90.00% → 3.5 (converted)\n";
echo "  Term: 63.33% → 1.0 (converted)\n";
echo "  Status: Passed\n\n";

// Calculate what the grades SHOULD be
$midterm_pct = floatval($row['midterm_percentage']);
$finals_pct = floatval($row['finals_percentage']);
$term_pct = floatval($row['term_percentage']);

function pctToGrade($p) {
    $p = floatval($p);
    if ($p >= 96.0) return 4.0;
    if ($p >= 90.0) return 3.5;
    if ($p >= 84.0) return 3.0;
    if ($p >= 78.0) return 2.5;
    if ($p >= 72.0) return 2.0;
    if ($p >= 66.0) return 1.5;
    if ($p >= 60.0) return 1.0;
    return 0.0;
}

echo "ANALYSIS:\n";
echo "  Midterm in DB: $midterm_pct% → converts to " . pctToGrade($midterm_pct) . " grade\n";
echo "  Finals in DB: $finals_pct% → converts to " . pctToGrade($finals_pct) . " grade\n";
echo "  Term in DB: $term_pct% → converts to " . pctToGrade($term_pct) . " grade\n";
echo "  Stored term_grade: " . $row['term_grade'] . "\n\n";

echo "PROBLEM:\n";
echo "The percentage values in the database do NOT match what faculty entered!\n";
echo "  Faculty entered: 93.33% (midterm), 90.00% (finals), 91.33% (term)\n";
echo "  Database has: " . $midterm_pct . "% (midterm), " . $finals_pct . "% (finals), " . $term_pct . "% (term)\n\n";

echo "POSSIBLE CAUSES:\n";
echo "1. ❌ Wrong calculation during save\n";
echo "2. ❌ Component aggregation error\n";
echo "3. ❌ Term weight application error\n";
echo "4. ❌ Data truncation or format issue\n\n";

echo "SOLUTION:\n";
echo "The stored grades need to be corrected to match what faculty intended.\n";
echo "Faculty should re-enter/save grades with correct values.\n";

?>
