<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once '../config/db.php';

$student_id = $_GET['id'] ?? '';

if (empty($student_id)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode(['exists' => $result->num_rows > 0]);
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
