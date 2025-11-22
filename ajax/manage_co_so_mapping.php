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

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

// Handle actions
try {
    switch ($action) {
        
        // ============================================
        // GET CO-SO MAPPINGS FOR A COURSE
        // ============================================
        case 'get_mappings':
            $course_code = $_GET['course_code'] ?? $_POST['course_code'] ?? '';
            
            if (empty($course_code)) {
                echo json_encode(['success' => false, 'message' => 'Course code required']);
                exit;
            }
            
            // Use the view we created for easy matrix display
            $stmt = $conn->prepare("
                SELECT * FROM v_co_so_matrix 
                WHERE course_code = ? 
                ORDER BY co_number
            ");
            
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database prepare error']);
                exit;
            }
            
            $stmt->bind_param("s", $course_code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $mappings = [];
            while ($row = $result->fetch_assoc()) {
                $mappings[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'mappings' => $mappings,
                'count' => count($mappings)
            ]);
            break;
            
        // ============================================
        // SAVE CO-SO MAPPING (for a single CO)
        // ============================================
        case 'save_mapping':
            $co_id = intval($_POST['co_id'] ?? 0);
            $so_numbers = json_decode($_POST['so_numbers'] ?? '[]', true);
            
            if ($co_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'CO ID required']);
                exit;
            }
            
            // Validate SO numbers (must be 1-6)
            foreach ($so_numbers as $so) {
                if (!is_numeric($so) || $so < 1 || $so > 6) {
                    echo json_encode(['success' => false, 'message' => 'Invalid SO number']);
                    exit;
                }
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Delete existing mappings for this CO
                $delete_stmt = $conn->prepare("DELETE FROM co_so_mapping WHERE co_id = ?");
                $delete_stmt->bind_param("i", $co_id);
                $delete_stmt->execute();
                
                // Insert new mappings
                if (!empty($so_numbers)) {
                    $insert_stmt = $conn->prepare("
                        INSERT INTO co_so_mapping (co_id, so_number, created_by) 
                        VALUES (?, ?, ?)
                    ");
                    
                    $user_id = $_SESSION['user_id'];
                    
                    foreach ($so_numbers as $so_number) {
                        $insert_stmt->bind_param("iii", $co_id, $so_number, $user_id);
                        $insert_stmt->execute();
                    }
                }
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Mapping saved successfully!'
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
            }
            break;
            
        // ============================================
        // SAVE ALL CO-SO MAPPINGS (bulk save for entire matrix)
        // ============================================
        case 'save_all_mappings':
            $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
            $mappings = json_decode($_POST['mappings'] ?? '[]', true);
            
            if (empty($course_code)) {
                echo json_encode(['success' => false, 'message' => 'Course code required']);
                exit;
            }
            
            if (!is_array($mappings)) {
                echo json_encode(['success' => false, 'message' => 'Invalid mappings data']);
                exit;
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Get all CO IDs for this course
                $co_stmt = $conn->prepare("SELECT co_id FROM course_outcomes WHERE course_code = ?");
                $co_stmt->bind_param("s", $course_code);
                $co_stmt->execute();
                $co_result = $co_stmt->get_result();
                
                $co_ids = [];
                while ($row = $co_result->fetch_assoc()) {
                    $co_ids[] = $row['co_id'];
                }
                
                // Delete all existing mappings for these COs
                if (!empty($co_ids)) {
                    $placeholders = implode(',', array_fill(0, count($co_ids), '?'));
                    $delete_stmt = $conn->prepare("DELETE FROM co_so_mapping WHERE co_id IN ($placeholders)");
                    $delete_stmt->bind_param(str_repeat('i', count($co_ids)), ...$co_ids);
                    $delete_stmt->execute();
                }
                
                // Insert new mappings
                $insert_stmt = $conn->prepare("
                    INSERT INTO co_so_mapping (co_id, so_number, created_by) 
                    VALUES (?, ?, ?)
                ");
                
                $user_id = $_SESSION['user_id'];
                
                foreach ($mappings as $mapping) {
                    $co_id = intval($mapping['co_id'] ?? 0);
                    $so_numbers = $mapping['so_numbers'] ?? [];
                    
                    if ($co_id > 0 && is_array($so_numbers)) {
                        foreach ($so_numbers as $so_number) {
                            $so_num = intval($so_number);
                            if ($so_num >= 1 && $so_num <= 6) {
                                $insert_stmt->bind_param("iii", $co_id, $so_num, $user_id);
                                $insert_stmt->execute();
                            }
                        }
                    }
                }
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'All mappings saved successfully!'
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
            }
            break;
            
        // ============================================
        // GET MAPPINGS FOR A SPECIFIC CO
        // ============================================
        case 'get_co_mappings':
            $co_id = intval($_GET['co_id'] ?? $_POST['co_id'] ?? 0);
            
            if ($co_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'CO ID required']);
                exit;
            }
            
            $stmt = $conn->prepare("SELECT so_number FROM co_so_mapping WHERE co_id = ? ORDER BY so_number");
            $stmt->bind_param("i", $co_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $so_numbers = [];
            while ($row = $result->fetch_assoc()) {
                $so_numbers[] = intval($row['so_number']);
            }
            
            echo json_encode([
                'success' => true,
                'so_numbers' => $so_numbers
            ]);
            break;
            
        // ============================================
        // DELETE ALL MAPPINGS FOR A CO
        // ============================================
        case 'delete_co_mappings':
            $co_id = intval($_POST['co_id'] ?? $_GET['co_id'] ?? 0);
            
            if ($co_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'CO ID required']);
                exit;
            }
            
            $stmt = $conn->prepare("DELETE FROM co_so_mapping WHERE co_id = ?");
            $stmt->bind_param("i", $co_id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Mappings deleted successfully!'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Delete failed']);
            }
            break;
            
        // ============================================
        // GET COMPLETE CO-SO MAP FOR CAR GENERATION
        // ============================================
        case 'get_car_data':
            $course_code = $_GET['course_code'] ?? $_POST['course_code'] ?? '';
            
            if (empty($course_code)) {
                echo json_encode(['success' => false, 'message' => 'Course code required']);
                exit;
            }
            
            // Get complete data using the view
            $stmt = $conn->prepare("
                SELECT 
                    co_number,
                    co_description,
                    mapped_sos,
                    so_descriptions
                FROM v_co_so_complete
                WHERE course_code = ?
                ORDER BY co_number
            ");
            
            $stmt->bind_param("s", $course_code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $car_data = [];
            while ($row = $result->fetch_assoc()) {
                $car_data[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'car_data' => $car_data,
                'count' => count($car_data)
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("CO-SO Mapping API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

exit;
?>