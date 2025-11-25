<?php
/**
 * FINAL DIAGNOSIS: Is the fix working or not?
 */

require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== FINAL DIAGNOSIS ===\n\n";

// Get the stored percentages
$stmt = $conn->prepare("SELECT midterm_percentage, finals_percentage, term_percentage FROM grade_term WHERE student_id = ? AND class_code = ?");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();
$grade = $result->fetch_assoc();
$stmt->close();

$stored_midterm = floatval($grade['midterm_percentage']);
$stored_finals = floatval($grade['finals_percentage']);
$stored_term = floatval($grade['term_percentage']);

echo "STORED IN DATABASE:\n";
echo "  Midterm: $stored_midterm%\n";
echo "  Finals: $stored_finals%\n";
echo "  Term: $stored_term%\n\n";

echo "ORIGINAL FACULTY INTENDED:\n";
echo "  Midterm: 93.33%\n";
echo "  Finals: 90.00%\n";
echo "  Term: 91.33%\n\n";

echo "COMPARISON:\n";
echo "  Midterm: Faculty 93.33% vs DB $stored_midterm% - MATCH: " . (abs(93.33 - $stored_midterm) < 1 ? "✓ YES" : "✗ NO") . "\n";
echo "  Finals: Faculty 90.00% vs DB $stored_finals% - MATCH: " . (abs(90 - $stored_finals) < 1 ? "✓ YES" : "✗ NO") . "\n";
echo "  Term: Faculty 91.33% vs DB $stored_term% - MATCH: " . (abs(91.33 - $stored_term) < 1 ? "✓ YES" : "✗ NO") . "\n\n";

echo "ROOT CAUSE ANALYSIS:\n";

// Test 1: Is the data from the old bug?
if ($stored_midterm < 1 && $stored_midterm > 0) {
    echo "🔴 VERDICT: OLD BUG SIGNATURE (0-1 range)\n";
    echo "The percentages are in the 0-1 range (decimal),\n";
    echo "which is the EXACT SYMPTOM of the old formula:\n";
    echo "(totalWeightedScore / totalWeight) * 100\n";
    echo "Example: 93.33 / 100 * 100 would give 0.9333\n";
    exit;
}

// Test 2: Are the values just different intentional component scores?
if ($stored_midterm >= 20 && $stored_midterm <= 100) {
    echo "🟡 SCENARIO: Different Component Scores\n";
    echo "The DB has percentages in 0-100 range (not the old bug).\n";
    echo "This could mean:\n";
    echo "  A) Faculty re-entered with DIFFERENT component values\n";
    echo "  B) Faculty intended 93.33% but components add up to 20%\n";
    echo "  C) Test data with different values\n\n";
    
    // Check if it COULD be the old formula misapplied somehow
    $ratios_to_original = [$stored_midterm / 93.33, $stored_finals / 90, $stored_term / 91.33];
    echo "Ratio Analysis:\n";
    echo "  Midterm: $stored_midterm / 93.33 = " . ($stored_midterm / 93.33) . "\n";
    echo "  Finals: $stored_finals / 90 = " . ($stored_finals / 90) . "\n";
    echo "  Term: $stored_term / 91.33 = " . ($stored_term / 91.33) . "\n\n";
    
    if (abs(($stored_midterm / 93.33) - 0.214) < 0.01) {
        echo "🔴 CORRELATION FOUND!\n";
        echo "The ratio ~0.214 suggests:\n";
        echo "$stored_midterm ≈ 93.33 × 0.214\n";
        echo "This does NOT match the bug formula.\n";
    }
}

echo "\nRECOMMENDATION:\n";
if ($stored_midterm != 93.33 || $stored_finals != 90 || $stored_term != 91.33) {
    echo "The stored values DO NOT match what faculty intended.\n";
    echo "This suggests:\n";
    echo "  1. Faculty may have entered test/different data\n";
    echo "  2. OR the JavaScript was still using old cached code\n";
    echo "  3. OR there's a different calculation path being used\n\n";
    echo "ACTION: Faculty should check:\n";
    echo "  - Did they intentionally enter different component scores?\n";
    echo "  - Or did they try to enter 93.33%, 90%, 91.33%?\n";
}

?>
