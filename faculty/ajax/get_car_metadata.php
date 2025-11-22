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

$faculty_id = $_SESSION['user_id'];
$class_code = $_GET['class_code'] ?? '';

if (empty($class_code)) {
    echo json_encode(['success' => false, 'message' => 'Class code required']);
    exit;
}

try {
    // Verify faculty owns this class
    $verify = $conn->prepare("
        SELECT class_id 
        FROM class 
        WHERE class_code = ? AND faculty_id = ? 
        LIMIT 1
    ");
    
    $verify->bind_param("si", $class_code, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found or access denied']);
        $verify->close();
        exit;
    }
    $verify->close();
    
    // Get CAR metadata
    $stmt = $conn->prepare("
        SELECT 
            teaching_strategies,
            interventions,
            intervention_student_count,
            problems_encountered,
            actions_taken,
            proposed_improvements,
            created_at,
            updated_at
        FROM car_metadata
        WHERE class_code = ?
        LIMIT 1
    ");
    
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $metadata = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'metadata' => $metadata
        ]);
    } else {
        $stmt->close();
        
        // No metadata found - return empty
        echo json_encode([
            'success' => true,
            'metadata' => [
                'teaching_strategies' => '',
                'interventions' => '',
                'intervention_student_count' => null,
                'problems_encountered' => '',
                'actions_taken' => '',
                'proposed_improvements' => '',
                'created_at' => null,
                'updated_at' => null
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get CAR metadata error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving metadata: ' . $e->getMessage()
    ]);
}

exit;
?>