<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

// Check authentication
if (!isAuthenticated() || getCurrentUser()['role'] !== 'admin') {
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit;
}

try {
    $course_code = $_GET['course_code'] ?? '';
    
    if (empty($course_code)) {
        echo json_encode(["success" => false, "message" => "Course code is required"]);
        exit;
    }
    
    $course_query = "SELECT course_title, course_desc, units FROM subjects WHERE course_code = ?";
    $stmt = $conn->prepare($course_query);
    $stmt->bind_param('s', $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($course = $result->fetch_assoc()) {
        echo json_encode([
            "success" => true,
            "course_code" => $course_code,
            "course_title" => $course['course_title'],
            "course_desc" => $course['course_desc'],
            "units" => $course['units']
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Course not found"
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error loading course info: " . $e->getMessage()
    ]);
}
?>