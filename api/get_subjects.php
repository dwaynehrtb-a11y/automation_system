<?php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

header('Content-Type: application/json');

try {
    $stmt = executeQuery($conn, "SELECT course_code, course_title FROM subjects ORDER BY course_code");
    $result = $stmt->get_result();
    
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    
    echo json_encode($subjects);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to load subjects']);
}
?>