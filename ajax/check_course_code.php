<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once '../config/db.php';

$course_code = $_GET['code'] ?? '';

if (empty($course_code)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT course_code FROM subjects WHERE course_code = ?");
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode(['exists' => $result->num_rows > 0]);
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
