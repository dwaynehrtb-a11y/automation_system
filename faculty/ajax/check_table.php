<?php
require_once '../../config/db.php';

echo "=== Table Structure & Data Check ===\n\n";

// Check if grading_component_columns table exists and has data
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM grading_component_columns");
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
echo "Total rows in grading_component_columns: " . $result['cnt'] . "\n\n";

// Get some sample rows
$stmt = $conn->prepare("SELECT * FROM grading_component_columns LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();

echo "Sample rows:\n";
while ($row = $result->fetch_assoc()) {
    echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
}
?>
