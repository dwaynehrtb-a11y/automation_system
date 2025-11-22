<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

try {
    $section = $_GET['section'] ?? '';
    $academic_year = $_GET['academic_year'] ?? '';
    $term = $_GET['term'] ?? '';
    $course_code = $_GET['course_code'] ?? '';
    $faculty_id = intval($_GET['faculty_id'] ?? 0);
    
    $stmt = $conn->prepare("
        SELECT class_id, day, time 
        FROM class 
        WHERE section = ? AND academic_year = ? AND term = ? AND course_code = ? AND faculty_id = ?
        ORDER BY FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), time
    ");
    $stmt->bind_param('ssssi', $section, $academic_year, $term, $course_code, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    
    echo json_encode(["success" => true, "schedules" => $schedules]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>