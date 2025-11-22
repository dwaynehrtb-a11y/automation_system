<?php
// Prevent ANY HTML output
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ini_set('html_errors', 0);

// Try to load config files
$config_error = false;
try {
    require_once '../config/session.php';
    require_once '../config/db.php';

    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    $config_error = $e->getMessage();
}

// Clean any output that might have been generated
ob_clean();

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Check config loading
if ($config_error) {
    echo json_encode(['success' => false, 'message' => 'Config error: ' . $config_error]);
    exit;
}

// Check database connection
if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database not connected']);
    exit;
}

// Check if session exists and has data
if (!isset($_SESSION) || empty($_SESSION)) {
    echo json_encode(['success' => false, 'message' => 'Session not initialized or empty']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Handle request
try {
    // Get all active student outcomes
    $stmt = $conn->prepare("
        SELECT 
            so_number, 
            so_description, 
            so_short_desc,
            order_index
        FROM student_outcomes 
        WHERE is_active = 1
        ORDER BY so_number ASC
    ");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database prepare error']);
        exit;
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $outcomes = [];
    while ($row = $result->fetch_assoc()) {
        $outcomes[] = [
            'so_number' => intval($row['so_number']),
            'so_description' => $row['so_description'],
            'so_short_desc' => $row['so_short_desc'],
            'order_index' => intval($row['order_index'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'outcomes' => $outcomes,
        'count' => count($outcomes)
    ]);
    
} catch (Exception $e) {
    error_log("Student Outcomes API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

exit;
?>