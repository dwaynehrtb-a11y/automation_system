<?php
/**
 * Clear Old Incorrect Grades
 * 
 * Removes the old incorrect grade records so faculty can re-enter them correctly
 * with the fixed calculation formula.
 */

require_once 'config/db.php';

// Check if this is a reset request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'confirm_clear') {
    // Clear all grades for testing student Ivy Ramirez
    $student_id = '2025-276819';
    $class_code = '25_T2_CCPRGG1L_INF223';
    
    echo "=== CLEARING OLD GRADES ===\n\n";
    
    // Get current data before deleting
    $stmt = $conn->prepare("SELECT * FROM grade_term WHERE student_id = ? AND class_code = ?");
    $stmt->bind_param('ss', $student_id, $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $old_data = $result->fetch_assoc();
        echo "OLD DATA (being removed):\n";
        echo "  Midterm: " . $old_data['midterm_percentage'] . "%\n";
        echo "  Finals: " . $old_data['finals_percentage'] . "%\n";
        echo "  Term: " . $old_data['term_percentage'] . "%\n";
        echo "  Term Grade: " . $old_data['term_grade'] . "\n\n";
    }
    $stmt->close();
    
    // Delete from grade_term
    $stmt = $conn->prepare("DELETE FROM grade_term WHERE student_id = ? AND class_code = ?");
    $stmt->bind_param('ss', $student_id, $class_code);
    
    if ($stmt->execute()) {
        echo "✓ Deleted old grade record\n";
        echo "✓ Grade data cleared successfully\n\n";
        echo "NEXT STEPS:\n";
        echo "1. Faculty: Go to grading dashboard for CCPRGG1L class\n";
        echo "2. Find student: Ivy Ramirez (2025-276819)\n";
        echo "3. Re-enter the grades (Midterm, Finals, components)\n";
        echo "4. Click 'Save Term Grades'\n";
        echo "5. Verify database now stores: 93.33%, 90%, 91.33%\n";
        echo "6. Student dashboard will automatically show correct values\n";
    } else {
        echo "✗ Failed to delete: " . $stmt->error . "\n";
    }
    $stmt->close();
    
} else {
    echo "=== CLEAR OLD GRADES UTILITY ===\n\n";
    echo "This will delete the old incorrect grades for student Ivy Ramirez\n";
    echo "so they can be re-entered correctly with the fixed calculation.\n\n";
    echo "To proceed, visit this URL:\n";
    echo "http://localhost/automation_system/clear_old_grades.php?action=confirm_clear\n\n";
    echo "⚠️  WARNING: This will delete existing grade data!\n";
}

?>
