<?php
session_start();

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/db.php';

ob_clean();

header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$course_code = $_GET['code'] ?? '';

if (empty($course_code)) {
    echo json_encode(['success' => false, 'message' => 'Course code required']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT co_id, co_number, co_description
        FROM course_outcomes
        WHERE course_code = ?
        ORDER BY co_number ASC
    ");
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $outcomes = [];
    while ($row = $result->fetch_assoc()) {
        $outcomes[] = [
            'id' => (int)$row['co_id'],
            'number' => (int)$row['co_number'],
            'description' => $row['co_description']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'outcomes' => $outcomes
    ]);
    
} catch (Exception $e) {
    error_log("Get outcomes error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

exit;
?>