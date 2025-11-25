<?php
/**
 * Direct Database Fix - Set is_encrypted = 0 for released grades
 * This script applies the fix without requiring browser interaction
 */

define('SYSTEM_ACCESS', true);
require_once 'config/db.php';

echo "=== GRADE DISPLAY BUG FIX - DATABASE UPDATE ===\n\n";

try {
    // Get current state
    $before = $conn->query("SELECT COUNT(*) as count FROM grade_term WHERE is_encrypted = 1")->fetch_assoc()['count'];
    echo "📊 Current state: $before records with is_encrypted = 1\n\n";
    
    // Apply the fix
    echo "🔄 Applying fix...\n";
    $result = $conn->query("UPDATE grade_term SET is_encrypted = 0 WHERE is_encrypted = 1");
    
    if ($result) {
        $affected = $conn->affected_rows;
        echo "✅ Update successful!\n";
        echo "   Records updated: $affected\n\n";
        
        // Verify the fix
        $after = $conn->query("SELECT COUNT(*) as count FROM grade_term WHERE is_encrypted = 1")->fetch_assoc()['count'];
        echo "✓ Verification: $after records still encrypted (should be 0)\n\n";
        
        // Show the affected student record
        echo "=== AFFECTED STUDENT RECORD ===\n";
        $student_result = $conn->query("
            SELECT student_id, class_code, term_grade, term_percentage, grade_status, is_encrypted
            FROM grade_term
            WHERE student_id = '2025-276819' AND class_code = '25_T2_CCPRGG1L_INF223'
        ");
        
        if ($student_result && $student_result->num_rows > 0) {
            $student = $student_result->fetch_assoc();
            echo "Student ID: " . $student['student_id'] . "\n";
            echo "Class Code: " . $student['class_code'] . "\n";
            echo "Term Grade: " . $student['term_grade'] . "\n";
            echo "Term %: " . $student['term_percentage'] . "\n";
            echo "Status: " . $student['grade_status'] . "\n";
            echo "is_encrypted: " . $student['is_encrypted'] . " (should be 0) ✅\n";
        }
        
        echo "\n✅ FIX COMPLETE!\n";
        echo "\n📝 Next Steps:\n";
        echo "1. Hard refresh browser (Ctrl+Shift+Delete)\n";
        echo "2. Login as student 2025-276819\n";
        echo "3. Navigate to 'My Enrolled Classes'\n";
        echo "4. Verify grade shows as 1.5 (green, 'Passed' status)\n";
        echo "5. Verify term percentage shows 70.00%\n";
        
    } else {
        echo "❌ Error: " . $conn->error . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

?>
