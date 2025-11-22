<?php
// Start session first
session_start();

// Prevent HTML output
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/db.php';

// Clean output buffer
ob_clean();

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$faculty_id = $_SESSION['user_id'];

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch($action) {
        case 'check':
            checkCARData($conn, $faculty_id);
            break;
            
        case 'save':
            saveCARData($conn, $faculty_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit;

/**
 * Check if CAR data exists for a class
 */
function checkCARData($conn, $faculty_id) {
    $class_code = $_GET['class_code'] ?? '';
    
    if (empty($class_code)) {
        echo json_encode(['success' => false, 'message' => 'Class code required']);
        return;
    }
    
    // Verify faculty owns this class
    $stmt = $conn->prepare("SELECT class_id FROM class WHERE class_code = ? AND faculty_id = ?");
    $stmt->bind_param("si", $class_code, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        $stmt->close();
        return;
    }
    
    $class = $result->fetch_assoc();
    $class_id = $class['class_id'];
    $stmt->close();
    
    // Check if CAR data exists
    $stmt = $conn->prepare("
        SELECT * FROM car_data 
        WHERE class_id = ?
    ");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $car_data = $result->fetch_assoc();
        
        // Get recommendations
        $stmt2 = $conn->prepare("
            SELECT co_number, recommendation 
            FROM car_recommendations 
            WHERE car_id = ?
        ");
        $stmt2->bind_param("i", $car_data['car_id']);
        $stmt2->execute();
        $rec_result = $stmt2->get_result();
        
        $recommendations = [];
        while ($rec = $rec_result->fetch_assoc()) {
            $recommendations['co' . $rec['co_number']] = $rec['recommendation'];
        }
        $stmt2->close();
        
        $car_data['recommendations'] = $recommendations;
        
        echo json_encode([
            'success' => true,
            'exists' => true,
            'car_data' => $car_data
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false
        ]);
    }
    
    $stmt->close();
}

/**
 * Save CAR data
 */
function saveCARData($conn, $faculty_id) {
    $class_code = $_POST['class_code'] ?? '';
    $teaching_strategies = $_POST['teaching_strategies'] ?? '';
    $intervention_activities = $_POST['intervention_activities'] ?? '';
    $problems_encountered = $_POST['problems_encountered'] ?? '';
    $actions_taken = $_POST['actions_taken'] ?? '';
    $proposed_actions = $_POST['proposed_actions'] ?? '';
    $recommendations_json = $_POST['recommendations'] ?? '{}';
    $status = $_POST['status'] ?? 'draft';
    
    // Validate required fields
    if (empty($class_code)) {
        echo json_encode(['success' => false, 'message' => 'Class code required']);
        return;
    }
    
    // Verify faculty owns this class
    $stmt = $conn->prepare("SELECT class_id FROM class WHERE class_code = ? AND faculty_id = ?");
    $stmt->bind_param("si", $class_code, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        $stmt->close();
        return;
    }
    
    $class = $result->fetch_assoc();
    $class_id = $class['class_id'];
    $stmt->close();
    
    // Parse recommendations
    $recommendations = json_decode($recommendations_json, true);
    if (!$recommendations) {
        $recommendations = [];
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Check if CAR data already exists
        $stmt = $conn->prepare("SELECT car_id FROM car_data WHERE class_id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing
            $car = $result->fetch_assoc();
            $car_id = $car['car_id'];
            $stmt->close();
            
            $stmt = $conn->prepare("
                UPDATE car_data SET
                    teaching_strategies = ?,
                    intervention_activities = ?,
                    problems_encountered = ?,
                    actions_taken = ?,
                    proposed_actions = ?,
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE car_id = ?
            ");
            $stmt->bind_param("ssssssi", 
                $teaching_strategies,
                $intervention_activities,
                $problems_encountered,
                $actions_taken,
                $proposed_actions,
                $status,
                $car_id
            );
            $stmt->execute();
            $stmt->close();
            
            // Delete old recommendations
            $stmt = $conn->prepare("DELETE FROM car_recommendations WHERE car_id = ?");
            $stmt->bind_param("i", $car_id);
            $stmt->execute();
            $stmt->close();
            
        } else {
            // Insert new
            $stmt->close();
            
            $stmt = $conn->prepare("
                INSERT INTO car_data (
                    class_id,
                    teaching_strategies,
                    intervention_activities,
                    problems_encountered,
                    actions_taken,
                    proposed_actions,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issssss",
                $class_id,
                $teaching_strategies,
                $intervention_activities,
                $problems_encountered,
                $actions_taken,
                $proposed_actions,
                $status
            );
            $stmt->execute();
            $car_id = $conn->insert_id;
            $stmt->close();
        }
        
        // Insert recommendations
        if (!empty($recommendations)) {
            $stmt = $conn->prepare("
                INSERT INTO car_recommendations (car_id, co_number, recommendation)
                VALUES (?, ?, ?)
            ");
            
            foreach ($recommendations as $key => $value) {
                // Extract CO number from key (e.g., "co1" -> 1)
                $co_number = (int)str_replace('co', '', $key);
                if ($co_number > 0 && !empty($value)) {
                    $stmt->bind_param("iis", $car_id, $co_number, $value);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'CAR data saved successfully',
            'car_id' => $car_id
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        throw $e;
    }
}
?>