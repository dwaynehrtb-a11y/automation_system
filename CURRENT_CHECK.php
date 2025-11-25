<?php
require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== DATABASE GRADE CHECK ===\n\n";

$stmt = $conn->prepare("SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id = ? AND class_code = ?");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Current Database Values:\n";
    echo json_encode($row, JSON_PRETTY_PRINT) . "\n\n";
    
    echo "Analysis:\n";
    echo "Midterm: " . $row['midterm_percentage'] . "%\n";
    echo "Finals: " . $row['finals_percentage'] . "%\n";
    echo "Term: " . $row['term_percentage'] . "%\n";
    echo "Grade: " . $row['term_grade'] . "\n";
} else {
    echo "No grade record found\n";
}
$stmt->close();

echo "\n=== COMPONENT VALUES ===\n\n";
$stmt = $conn->prepare("SELECT component_name, component_type, percentage FROM grade_component_items WHERE student_id = ? AND class_code = ? ORDER BY component_name");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "{$row['component_name']} ({$row['component_type']}): {$row['percentage']}%\n";
    }
} else {
    echo "No component records found\n";
}
$stmt->close();
?>
