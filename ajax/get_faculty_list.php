<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

try {
    $stmt = $conn->query("SELECT id, name FROM users WHERE role = 'faculty' ORDER BY name");
    $faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(["success" => true, "faculty" => $faculty]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error loading faculty"]);
}
?>