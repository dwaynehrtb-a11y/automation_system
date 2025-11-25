<?php
/**
 * Simple direct test - what's in the database vs what should be there
 */

require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== SIMPLE DATABASE CHECK ===\n\n";

// Get stored grades
$stmt = $conn->prepare("SELECT midterm_percentage, finals_percentage, term_percentage FROM grade_term WHERE student_id = ? AND class_code = ?");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "✓ Database is CLEAN - No grades found\n";
    echo "Ready for faculty to re-enter with fixed code\n";
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

$midterm = floatval($row['midterm_percentage']);
$finals = floatval($row['finals_percentage']);
$term = floatval($row['term_percentage']);

echo "DATABASE VALUES:\n";
echo "  Midterm: $midterm%\n";
echo "  Finals: $finals%\n";
echo "  Term: $term%\n\n";

// Check if these are in correct range
echo "ANALYSIS:\n";

if ($midterm < 1 || $finals < 1 || $term < 1) {
    echo "❌ VALUES ARE IN DECIMAL RANGE (0-1)\n";
    echo "This is the OLD BUG signature - percentages stored as decimals\n";
    echo "Example: 93.33 was stored as 0.9333 or similar\n\n";
    echo "ROOT CAUSE: JavaScript is still using OLD cached code\n";
    echo "The fix hasn't been applied yet in the running code\n\n";
    echo "SOLUTION:\n";
    echo "1. Faculty needs to hard-refresh browser: Ctrl+Shift+R\n";
    echo "2. Clear browser cache completely\n";
    echo "3. Re-enter grades\n";
    echo "4. The NEW code (with fix) will calculate correctly\n";
} else if ($midterm >= 20 && $finals >= 60 && $term >= 40) {
    echo "✓ VALUES ARE IN CORRECT RANGE (0-100)\n";
    echo "This suggests the fix might be working\n";
    echo "Or: Faculty entered different component scores\n\n";
    echo "NEXT STEP:\n";
    echo "Check if these are the expected values faculty intended\n";
} else {
    echo "⚠️ VALUES ARE UNCLEAR\n";
    echo "Cannot determine if fix is working without knowing\n";
    echo "what values faculty intended to enter\n";
}

?>
