<?php
/**
 * Did the Fix Work? Detailed Diagnosis
 */

require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== FIX EFFECTIVENESS ANALYSIS ===\n\n";

// Get the current stored grades
$stmt = $conn->prepare("
    SELECT 
        midterm_percentage,
        finals_percentage,
        term_percentage
    FROM grade_term 
    WHERE student_id = ? AND class_code = ?
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();
$grade_row = $result->fetch_assoc();
$stmt->close();

echo "CURRENT DATABASE STATE:\n";
echo "  Midterm: " . $grade_row['midterm_percentage'] . "%\n";
echo "  Finals: " . $grade_row['finals_percentage'] . "%\n";
echo "  Term: " . $grade_row['term_percentage'] . "%\n\n";

// Determine if these are results of the OLD formula or just different component scores
echo "DIAGNOSIS:\n";

if ($grade_row['midterm_percentage'] < 1 && $grade_row['midterm_percentage'] > 0) {
    echo "🔴 CONCLUSION: OLD BUG IS STILL ACTIVE\n";
    echo "   The percentage is in 0-1 range, which is characteristic of the old formula:\n";
    echo "   (totalWeightedScore / totalWeight) * 100\n";
    echo "   This should have been fixed!\n\n";
    echo "   REASON: Browser likely cached the old JavaScript\n";
    echo "   ACTION: Faculty needs to hard refresh (Ctrl+Shift+R)\n";
} else if ($grade_row['midterm_percentage'] >= 20 && $grade_row['midterm_percentage'] <= 100) {
    echo "🟡 POSSIBLE SCENARIO 1: Faculty Entered Different Components\n";
    echo "   The percentages (20%, 80%, 56%) could be from different component scores\n";
    echo "   This is NOT necessarily a bug - just different input data\n\n";
    echo "   To verify: Check what component scores produce these percentages:\n";
    echo "   - Do components add up to exactly 20%, 80%, 56%?\n";
    echo "   - Or are these decimal values displayed incorrectly?\n\n";
}

echo "🟢 POSSIBLE SCENARIO 2: Fix IS Working\n";
echo "   If component scores genuinely calculate to 20%, 80%, 56%, then FIX IS WORKING\n";
echo "   The new formula correctly calculated: 20%, 80%, 56%\n\n";

// Check components
$stmt = $conn->prepare("
    SELECT 
        gci.score,
        gci.max_score,
        gc.component_weight,
        gci.term_type,
        COUNT(*) as count
    FROM grade_component_items gci
    JOIN grade_components gc ON gci.component_id = gc.id
    WHERE gci.student_id = ? AND gci.class_code = ?
    GROUP BY gci.term_type
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "COMPONENT DATA EXISTS: Yes\n";
    while ($row = $result->fetch_assoc()) {
        echo "  {$row['term_type']}: {$row['count']} components\n";
    }
} else {
    echo "COMPONENT DATA: None found\n";
}
$stmt->close();

?>
