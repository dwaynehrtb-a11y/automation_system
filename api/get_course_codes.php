<?php
require_once '../config/db.php';
require_once '../config/session.php';

header('Content-Type: application/json');

try {
    // Get all course codes from subjects table
    $stmt = $conn->prepare("SELECT course_code FROM subjects ORDER BY course_code");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $course_codes = [];
    while ($row = $result->fetch_assoc()) {
        $course_codes[] = [
            'course_code' => $row['course_code']
        ];
    }
    
    echo json_encode($course_codes);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch course codes']);
}
?>