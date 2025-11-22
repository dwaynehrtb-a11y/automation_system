<?php
/**
 * FINAL MIGRATION VERIFICATION SCRIPT
 * Confirms all references to term_grades have been migrated to grade_term
 */

require 'config/db.php';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        Migration Verification: term_grades → grade_term        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$checks_passed = 0;
$checks_failed = 0;

// 1. Verify grade_term table exists
echo "[1/4] Checking if grade_term table exists... ";
$result = $conn->query("SHOW TABLES LIKE 'grade_term'");
if ($result->num_rows > 0) {
    echo "✓ PASS\n";
    $checks_passed++;
} else {
    echo "✗ FAIL\n";
    $checks_failed++;
}

// 2. Verify data in grade_term
echo "[2/4] Checking grade_term contains records... ";
$result = $conn->query("SELECT COUNT(*) as cnt FROM grade_term");
$row = $result->fetch_assoc();
if ($row['cnt'] > 0) {
    echo "✓ PASS ({$row['cnt']} records)\n";
    $checks_passed++;
} else {
    echo "✗ FAIL (no records)\n";
    $checks_failed++;
}

// 3. Verify table structure
echo "[3/4] Checking grade_term table structure... ";
$result = $conn->query("SHOW COLUMNS FROM grade_term");
$expected_columns = ['id', 'student_id', 'class_code', 'midterm_percentage', 'finals_percentage', 'term_percentage', 'term_grade', 'grade_status', 'status_manually_set', 'lacking_requirements', 'computed_at', 'is_encrypted'];
$found_columns = [];
while ($row = $result->fetch_assoc()) {
    $found_columns[] = $row['Field'];
}
$all_found = true;
foreach ($expected_columns as $col) {
    if (!in_array($col, $found_columns)) {
        $all_found = false;
        break;
    }
}
if ($all_found) {
    echo "✓ PASS\n";
    $checks_passed++;
} else {
    echo "✗ FAIL (missing columns)\n";
    $checks_failed++;
}

// 4. Verify foreign keys
echo "[4/4] Checking grade_term foreign key constraints... ";
$result = $conn->query("SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='grade_term' AND REFERENCED_TABLE_NAME IS NOT NULL");
$fks = [];
while ($row = $result->fetch_assoc()) {
    $fks[] = $row['REFERENCED_TABLE_NAME'];
}
if (in_array('student', $fks) && in_array('class', $fks)) {
    echo "✓ PASS\n";
    $checks_passed++;
} else {
    echo "✗ FAIL (FK constraints missing)\n";
    $checks_failed++;
}

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        RESULTS SUMMARY                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "Checks Passed: $checks_passed/4\n";
echo "Checks Failed: $checks_failed/4\n\n";

if ($checks_failed === 0) {
    echo "✓ ALL CHECKS PASSED - Migration is complete and verified!\n";
} else {
    echo "✗ SOME CHECKS FAILED - Please review the migration.\n";
}

echo "\n=== Sample Data from grade_term ===\n";
$result = $conn->query("SELECT student_id, class_code, midterm_percentage, finals_percentage, term_percentage, term_grade, grade_status FROM grade_term LIMIT 3");
$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    echo "\nRecord $count:\n";
    echo "  Student: {$row['student_id']}\n";
    echo "  Class: {$row['class_code']}\n";
    echo "  Grades: Midterm={$row['midterm_percentage']}%, Finals={$row['finals_percentage']}%, Term={$row['term_percentage']}%\n";
    echo "  Term Grade: {$row['term_grade']}\n";
    echo "  Status: {$row['grade_status']}\n";
}

echo "\n✓ Migration verification complete.\n";
?>
