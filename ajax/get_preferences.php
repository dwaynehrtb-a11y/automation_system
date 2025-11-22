<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("SELECT email_notifications, dashboard_notifications, class_alerts FROM user_preferences WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'preferences' => [
                'email_notifications' => (int)$row['email_notifications'],
                'dashboard_notifications' => (int)$row['dashboard_notifications'],
                'class_alerts' => (int)$row['class_alerts']
            ]
        ]);
    } else {
        // Defaults if not set yet
        echo json_encode([
            'success' => true,
            'preferences' => [
                'email_notifications' => 1,
                'dashboard_notifications' => 1,
                'class_alerts' => 1
            ]
        ]);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: '.$e->getMessage()]);
}
?>