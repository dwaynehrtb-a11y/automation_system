<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$faculty_id = intval($_GET['faculty_id'] ?? 0);

if ($faculty_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid faculty ID"]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT c.*, s.course_title 
        FROM class c 
        LEFT JOIN subjects s ON c.course_code = s.course_code 
        WHERE c.faculty_id = ? 
        ORDER BY c.day, c.time
    ");
    
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    
    // Get the result using MySQLi (not PDO)
    $result = $stmt->get_result();
    $classes = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    
    echo json_encode(["success" => true, "classes" => $classes]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error loading classes: " . $e->getMessage()]);
}
?>