<?php
/**
 * Verify Grade Clear Status
 */

require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== GRADE STATUS VERIFICATION ===\n\n";

$stmt = $conn->prepare("SELECT id, term_grade, midterm_percentage, finals_percentage, term_percentage FROM grade_term WHERE student_id = ? AND class_code = ?");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "❌ Grade record still exists (not cleared):\n";
    $row = $result->fetch_assoc();
    echo json_encode($row, JSON_PRETTY_PRINT);
} else {
    echo "✅ Grade record cleared successfully!\n";
    echo "Ready for faculty to re-enter grades with corrected calculation.\n\n";
    echo "NEXT ACTION:\n";
    echo "Faculty must navigate to grading dashboard and re-enter grades for:\n";
    echo "  Student: Ivy Ramirez (2025-276819)\n";
    echo "  Class: CCPRGG1L (25_T2_CCPRGG1L_INF223)\n";
    echo "  With intended percentages: 93.33% midterm, 90% finals\n";
}

$stmt->close();

?>
