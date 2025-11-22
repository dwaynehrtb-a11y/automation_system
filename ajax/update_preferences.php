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
$preference = $_POST['preference'] ?? '';
$value = intval($_POST['value'] ?? 0);

// Validate preference name
$valid_preferences = ['email_notifications', 'dashboard_notifications', 'class_alerts'];
if (!in_array($preference, $valid_preferences)) {
    echo json_encode(['success' => false, 'message' => 'Invalid preference']);
    exit;
}

try {
    // Check if preferences exist
    $check_stmt = $conn->prepare("SELECT user_id FROM user_preferences WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing
        $update_stmt = $conn->prepare("UPDATE user_preferences SET $preference = ? WHERE user_id = ?");
        $update_stmt->bind_param("ii", $value, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // Insert new
        $insert_stmt = $conn->prepare("INSERT INTO user_preferences (user_id, $preference) VALUES (?, ?)");
        $insert_stmt->bind_param("ii", $user_id, $value);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    $check_stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Preference updated']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
