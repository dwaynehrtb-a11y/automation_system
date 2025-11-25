<?php
/**
 * Force Clear Grade Records
 * Ensures all grades for this student/class are removed
 */

require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== FORCE CLEARING ALL GRADES ===\n\n";

// First, get the record IDs before deletion
$stmt = $conn->prepare("SELECT id, midterm_percentage, finals_percentage, term_percentage FROM grade_term WHERE student_id = ? AND class_code = ?");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

$deleted_count = 0;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Will delete record ID {$row['id']}: {$row['midterm_percentage']}%, {$row['finals_percentage']}%, {$row['term_percentage']}%\n";
    }
}
$stmt->close();

// Delete all records for this student/class combination
$stmt = $conn->prepare("DELETE FROM grade_term WHERE student_id = ? AND class_code = ?");
$stmt->bind_param('ss', $student_id, $class_code);

if ($stmt->execute()) {
    $deleted_count = $stmt->affected_rows;
    echo "\n✓ Deleted $deleted_count record(s) from grade_term\n";
} else {
    echo "✗ Error deleting from grade_term: " . $stmt->error . "\n";
}
$stmt->close();

// Also clear any component data
$stmt = $conn->prepare("DELETE FROM grade_component_items WHERE student_id = ? AND class_code = ?");
$stmt->bind_param('ss', $student_id, $class_code);

if ($stmt->execute()) {
    $component_count = $stmt->affected_rows;
    echo "✓ Deleted $component_count component record(s) from grade_component_items\n";
} else {
    echo "✗ Error deleting from grade_component_items: " . $stmt->error . "\n";
}
$stmt->close();

// Verify deletion
echo "\n=== VERIFICATION ===\n";
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM grade_term WHERE student_id = ? AND class_code = ?");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row['count'] == 0) {
    echo "✓ All grade records cleared successfully!\n";
    echo "✓ Student $student_id in class $class_code has no grades\n";
    echo "✓ Ready for faculty to re-enter grades with corrected calculation\n";
} else {
    echo "✗ ERROR: Still " . $row['count'] . " record(s) remaining!\n";
}

?>
