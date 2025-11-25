<?php
require_once 'config/db.php';

echo "=== FIXING ENCRYPTION FLAG FOR STUDENT 2025-276819 ===\n\n";

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

// First, check the visibility status for this class
$visibility_result = $conn->query("SELECT class_code, grade_visibility FROM grade_visibility_status WHERE class_code = '$class_code' LIMIT 1");

if ($visibility_result && $visibility_result->num_rows > 0) {
    $vis_row = $visibility_result->fetch_assoc();
    echo "Current visibility for $class_code: " . ($vis_row['grade_visibility'] ?? 'NOT SET') . "\n";
} else {
    echo "No visibility record found for $class_code\n";
}

// Check current state of the grade_term record
$grade_result = $conn->query("SELECT id, student_id, is_encrypted, grade_status FROM grade_term WHERE student_id = '$student_id' AND class_code = '$class_code'");
if ($grade_result && $grade_result->num_rows > 0) {
    $grade_row = $grade_result->fetch_assoc();
    echo "\nCurrent grade_term record:\n";
    echo "  is_encrypted: " . $grade_row['is_encrypted'] . "\n";
    echo "  grade_status: " . $grade_row['grade_status'] . "\n";
} else {
    echo "\nNo grade_term record found!\n";
    exit;
}

// Update is_encrypted to 0 (make grades visible)
echo "\n📝 Setting is_encrypted = 0 for this student...\n";

$update_stmt = $conn->prepare("UPDATE grade_term SET is_encrypted = 0 WHERE student_id = ? AND class_code = ?");
if (!$update_stmt) {
    echo "❌ Error preparing statement: " . $conn->error . "\n";
    exit;
}

$update_stmt->bind_param("ss", $student_id, $class_code);
if ($update_stmt->execute()) {
    echo "✅ Successfully updated!\n";
    echo "   Rows affected: " . $update_stmt->affected_rows . "\n";
} else {
    echo "❌ Error executing update: " . $update_stmt->error . "\n";
}
$update_stmt->close();

// Verify the change
echo "\n🔍 Verifying the change...\n";
$verify_result = $conn->query("SELECT id, student_id, is_encrypted, grade_status FROM grade_term WHERE student_id = '$student_id' AND class_code = '$class_code'");
if ($verify_result && $verify_result->num_rows > 0) {
    $verify_row = $verify_result->fetch_assoc();
    echo "  is_encrypted: " . $verify_row['is_encrypted'] . " (should be 0)\n";
    echo "  grade_status: " . $verify_row['grade_status'] . " (should be 'passed')\n";
}

echo "\n✅ Done! The student should now see their grades correctly.\n";
?>
