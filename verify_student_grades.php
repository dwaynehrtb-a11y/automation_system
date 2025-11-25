<?php
/**
 * Verification Script - Confirm Student Can See Grades
 */
require_once 'config/db.php';
require_once 'config/encryption.php';

$student_id = '2025-276819';
$class_code = 'CCPRGG1L';

echo "=== VERIFICATION: Student Grade Visibility ===\n\n";

// Simulate what the student API will check
echo "Simulating API check for:\n";
echo "  Student: $student_id\n";
echo "  Class: $class_code\n\n";

// Check is_encrypted flag (primary check)
$stmt = $conn->prepare("
    SELECT midterm_percentage, finals_percentage, term_percentage, term_grade, grade_status, is_encrypted 
    FROM grade_term 
    WHERE student_id = ? AND class_code = ?
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo "❌ No grade record found\n";
    exit;
}

echo "Database Check:\n";
echo "  is_encrypted: " . ($row['is_encrypted'] == 1 ? "1 (ENCRYPTED)" : "0 (DECRYPTED)") . "\n";
echo "  grade_status: " . $row['grade_status'] . "\n";
echo "  term_percentage: " . $row['term_percentage'] . "\n";
echo "  term_grade: " . substr($row['term_grade'], 0, 20) . "...\n\n";

// Determine what API will return
$is_encrypted = intval($row['is_encrypted']) === 1;

if ($is_encrypted) {
    echo "❌ PROBLEM: is_encrypted = 1 (grades are still locked)\n";
    echo "   API will return: term_grade_hidden = true\n";
    echo "   Student will see: 🔐 Lock icons\n";
} else {
    echo "✅ SUCCESS: is_encrypted = 0 (grades are unlocked)\n";
    echo "   API will return: term_grade_hidden = false\n";
    echo "   Student will see: Actual grade values\n";
    
    // Decrypt and show what student will see
    Encryption::init();
    try {
        $midterm_pct = floatval($row['midterm_percentage'] ?? 0);
        $finals_pct = floatval($row['finals_percentage'] ?? 0);
        $term_pct = floatval($row['term_percentage'] ?? 0);
        $term_grade = floatval($row['term_grade']);
        
        echo "\n  What student will see:\n";
        echo "    Midterm: " . number_format($midterm_pct, 2) . "%\n";
        echo "    Finals: " . number_format($finals_pct, 2) . "%\n";
        echo "    Term Grade: " . number_format($term_grade, 2) . "\n";
        echo "    Status: " . $row['grade_status'] . "\n";
    } catch (Exception $e) {
        echo "  Error decrypting: " . $e->getMessage() . "\n";
    }
}

echo "\n=== VERIFICATION COMPLETE ===\n";

?>
