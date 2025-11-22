<?php
session_start();

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$class_code = $input['class_code'] ?? '';
$teaching_strategies = $input['teaching_strategies'] ?? '';
$interventions = $input['interventions'] ?? '';
$intervention_student_count = $input['intervention_student_count'] ?? null;
$problems_encountered = $input['problems_encountered'] ?? '';
$actions_taken = $input['actions_taken'] ?? '';
$proposed_improvements = $input['proposed_improvements'] ?? '';

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
    
    // Check if metadata already exists
    $check = $conn->prepare("SELECT id FROM car_metadata WHERE class_code = ?");
    $check->bind_param("s", $class_code);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();
    
    if ($exists) {
        // Update existing
        $stmt = $conn->prepare("
            UPDATE car_metadata 
            SET teaching_strategies = ?,
                interventions = ?,
                intervention_student_count = ?,
                problems_encountered = ?,
                actions_taken = ?,
                proposed_improvements = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE class_code = ?
        ");
        
        $stmt->bind_param(
            "ssissss",
            $teaching_strategies,
            $interventions,
            $intervention_student_count,
            $problems_encountered,
            $actions_taken,
            $proposed_improvements,
            $class_code
        );
    } else {
        // Insert new
        $stmt = $conn->prepare("
            INSERT INTO car_metadata 
            (class_code, teaching_strategies, interventions, intervention_student_count, 
             problems_encountered, actions_taken, proposed_improvements, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
       $stmt->bind_param(
    "sssisssi",
    $class_code,
    $teaching_strategies,
    $interventions,
    $intervention_student_count,
    $problems_encountered,
    $actions_taken,
    $proposed_improvements,
    $faculty_id
);
    }
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'CAR metadata saved successfully'
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Failed to save metadata: ' . $error);
    }
    
} catch (Exception $e) {
    error_log("Save CAR metadata error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error saving metadata: ' . $e->getMessage()
    ]);
}

exit;
?>