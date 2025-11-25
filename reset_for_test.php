<?php
/**
 * Reset Grades for Clean Testing with Fixed Code
 */

require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== RESETTING DATA FOR CLEAN TEST ===\n\n";

// Delete grades
$stmt = $conn->prepare("DELETE FROM grade_term WHERE student_id = ? AND class_code = ?");
$stmt->bind_param('ss', $student_id, $class_code);
$deleted = $stmt->execute();
$grade_count = $stmt->affected_rows;
$stmt->close();

// Delete components
$stmt = $conn->prepare("DELETE FROM grade_component_items WHERE student_id = ? AND class_code = ?");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$component_count = $stmt->affected_rows;
$stmt->close();

echo "✓ Deleted $grade_count grade record(s)\n";
echo "✓ Deleted $component_count component record(s)\n\n";

echo "✅ DATABASE IS CLEAN\n\n";

echo "NEXT STEPS FOR FACULTY:\n";
echo "1. Clear browser cache (See CACHE_CLEAR_INSTRUCTIONS.html)\n";
echo "2. Navigate to Faculty Dashboard\n";
echo "3. Select CCPRGG1L class (INF223)\n";
echo "4. Find student Ivy Ramirez (2025-276819)\n";
echo "5. Enter grade components:\n";
echo "   MIDTERM (40% of term):\n";
echo "   - Attendance: 15/15\n";
echo "   - Classwork: 30/30\n";
echo "   - Quiz: 28/30\n";
echo "   - Participation: 20/20\n";
echo "   Should calculate to: 98.67% → 4.0 grade\n\n";
echo "   FINALS (60% of term):\n";
echo "   - Quiz: 18/20 (90%)\n";
echo "   - Final Exam: 36/40 (90%)\n";
echo "   Should calculate to: 90% → 3.5 grade\n\n";
echo "6. Click 'Save Term Grades'\n";
echo "7. Verify database stores: 98.67%, 90%, and term ~94%\n";
echo "8. Student dashboard will auto-update\n\n";

echo "EXPECTED FINAL RESULT:\n";
echo "Faculty Dashboard: Midterm 98.67% (4.0), Finals 90% (3.5), Term 94% (4.0)\n";
echo "Student Dashboard: Midterm 98.67% (4.0), Finals 90% (3.5), Term 94% (4.0)\n";
echo "Status: PASSED ✓\n";

?>
