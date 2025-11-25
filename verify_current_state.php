<?php
/**
 * Verify Current State - Check if fix was applied
 */

define('SYSTEM_ACCESS', true);
require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "<pre>";
echo "=== VERIFICATION: Current Database State ===\n\n";

// Check the specific student record
$result = $conn->query("
    SELECT 
        id,
        student_id,
        class_code,
        midterm_percentage,
        finals_percentage,
        term_percentage,
        term_grade,
        grade_status,
        is_encrypted,
        status_manually_set,
        lacking_requirements,
        computed_at
    FROM grade_term
    WHERE student_id = '$student_id' AND class_code = '$class_code'
");

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "✅ RECORD FOUND FOR STUDENT $student_id\n\n";
    
    echo "Database Values:\n";
    echo "├─ midterm_percentage: " . ($row['midterm_percentage'] ?? 'NULL') . "\n";
    echo "├─ finals_percentage: " . ($row['finals_percentage'] ?? 'NULL') . "\n";
    echo "├─ term_percentage: " . ($row['term_percentage'] ?? 'NULL') . "\n";
    echo "├─ term_grade: " . ($row['term_grade'] ?? 'NULL') . "\n";
    echo "├─ grade_status: " . ($row['grade_status'] ?? 'NULL') . "\n";
    echo "├─ is_encrypted: " . ($row['is_encrypted'] ?? 'NULL') . " " . (intval($row['is_encrypted']) === 0 ? "✅ (VISIBLE)" : "❌ (HIDDEN)") . "\n";
    echo "├─ status_manually_set: " . ($row['status_manually_set'] ?? 'NULL') . "\n";
    echo "└─ lacking_requirements: " . ($row['lacking_requirements'] ?? 'NULL') . "\n";
    
    echo "\n📊 ANALYSIS:\n";
    $term_pct = floatval($row['term_percentage'] ?? 0);
    if ($term_pct >= 60) {
        echo "✅ Term percentage ($term_pct%) >= 60% → Should be PASSED\n";
    } else {
        echo "❌ Term percentage ($term_pct%) < 60% → Should be FAILED\n";
    }
    
    if ($row['grade_status'] === 'passed') {
        echo "✅ grade_status = 'passed' (CORRECT)\n";
    } else if ($row['grade_status'] === 'failed') {
        echo "❌ grade_status = 'failed' (SHOULD BE 'passed')\n";
    }
    
    if (intval($row['is_encrypted']) === 0) {
        echo "✅ is_encrypted = 0 → Grades are VISIBLE to student\n";
    } else {
        echo "❌ is_encrypted = 1 → Grades are HIDDEN from student\n";
    }
    
} else {
    echo "❌ NO RECORD FOUND for $student_id in $class_code\n";
}

echo "\n";

// Check overall encryption status
$encrypted_count = $conn->query("SELECT COUNT(*) as count FROM grade_term WHERE is_encrypted = 1")->fetch_assoc()['count'];
$total_count = $conn->query("SELECT COUNT(*) as count FROM grade_term")->fetch_assoc()['count'];

echo "📈 OVERALL STATUS:\n";
echo "├─ Total records: $total_count\n";
echo "├─ Encrypted records: $encrypted_count\n";
echo "└─ Visible records: " . ($total_count - $encrypted_count) . "\n";

echo "\n✅ FIX STATUS: ";
if (intval($encrypted_count) === 0) {
    echo "ALL RECORDS VISIBLE ✅\n";
} else {
    echo "$encrypted_count RECORDS STILL ENCRYPTED\n";
}

echo "</pre>";
?>
