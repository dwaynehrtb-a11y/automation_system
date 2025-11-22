<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$phone = $_POST['phone'] ?? '';
$department = $_POST['department'] ?? '';
$office_location = $_POST['office_location'] ?? '';

try {
    $stmt = $conn->prepare("UPDATE users SET phone = ?, department = ?, office_location = ? WHERE id = ?");
    $stmt->bind_param("sssi", $phone, $department, $office_location, $user_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Contact information updated']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
