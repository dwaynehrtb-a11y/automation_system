<?php
/**
 * Investigate Current Grade Data
 * Check what's actually in the database
 */

require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== INVESTIGATING GRADE DATA ===\n\n";

// Check if there are multiple records
$stmt = $conn->prepare("SELECT id, term_grade, midterm_percentage, finals_percentage, term_percentage, is_encrypted FROM grade_term WHERE student_id = ? AND class_code = ? ORDER BY id DESC LIMIT 5");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "All grade records for student $student_id in class $class_code:\n";
if ($result->num_rows > 0) {
    $count = 1;
    while ($row = $result->fetch_assoc()) {
        echo "\nRecord #" . $count . " (ID: {$row['id']}):\n";
        echo "  Midterm: {$row['midterm_percentage']}%\n";
        echo "  Finals: {$row['finals_percentage']}%\n";
        echo "  Term: {$row['term_percentage']}%\n";
        echo "  Term Grade: {$row['term_grade']}\n";
        echo "  Encrypted: {$row['is_encrypted']}\n";
        $count++;
    }
} else {
    echo "No records found.\n";
}
$stmt->close();

echo "\n\n=== CHECKING FOR COMPONENT DATA ===\n";

// Check if there are component records
$stmt = $conn->prepare("
    SELECT 
        gci.id,
        gci.component_id,
        gc.component_name,
        gci.score,
        gci.max_score,
        gci.term_type
    FROM grade_component_items gci
    JOIN grade_components gc ON gci.component_id = gc.id
    WHERE gci.student_id = ? AND gci.class_code = ?
    ORDER BY gci.term_type, gc.component_name
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "Component scores found:\n";
    $current_term = '';
    while ($row = $result->fetch_assoc()) {
        if ($row['term_type'] !== $current_term) {
            $current_term = $row['term_type'];
            echo "\n" . ucfirst($current_term) . ":\n";
        }
        echo "  {$row['component_name']}: {$row['score']}/{$row['max_score']}\n";
    }
} else {
    echo "No component data found.\n";
}
$stmt->close();

?>
