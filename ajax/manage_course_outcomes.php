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
        // GET ALL COURSE OUTCOMES FOR A SUBJECT
        // ============================================
        case 'get_outcomes':
            $course_code = $_GET['course_code'] ?? $_POST['course_code'] ?? '';
            
            if (empty($course_code)) {
                echo json_encode(['success' => false, 'message' => 'Course code required']);
                exit;
            }
            
            // Get all COs for this subject
            $stmt = $conn->prepare("
                SELECT co_id, course_code, co_number, co_description, order_index, created_at
                FROM course_outcomes 
                WHERE course_code = ? 
                ORDER BY co_number ASC
            ");
            
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database prepare error']);
                exit;
            }
            
            $stmt->bind_param("s", $course_code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $outcomes = [];
            while ($row = $result->fetch_assoc()) {
                $outcomes[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'outcomes' => $outcomes,
                'count' => count($outcomes)
            ]);
            break;
            
        // ============================================
        // ADD NEW COURSE OUTCOME
        // ============================================
        case 'add_outcome':
            $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
            $co_description = trim($_POST['co_description'] ?? '');
            
            // Validate required fields
            if (empty($course_code) || empty($co_description)) {
                echo json_encode(['success' => false, 'message' => 'Course code and description required']);
                exit;
            }
            
            // Validate description length
            if (strlen($co_description) < 10) {
                echo json_encode(['success' => false, 'message' => 'Description must be at least 10 characters']);
                exit;
            }
            
            // Check if course exists
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM subjects WHERE course_code = ?");
            $check_stmt->bind_param("s", $course_code);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();
            
            if ($count == 0) {
                echo json_encode(['success' => false, 'message' => 'Course not found']);
                exit;
            }
            
            // Get next CO number
            $num_stmt = $conn->prepare("SELECT COALESCE(MAX(co_number), 0) + 1 as next_num FROM course_outcomes WHERE course_code = ?");
            $num_stmt->bind_param("s", $course_code);
            $num_stmt->execute();
            $num_stmt->bind_result($next_co_number);
            $num_stmt->fetch();
            $num_stmt->close();
            
            // Insert new CO
            $stmt = $conn->prepare("
                INSERT INTO course_outcomes (course_code, co_number, co_description, order_index, created_by) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database prepare error']);
                exit;
            }
            
            $user_id = $_SESSION['user_id'];
            $order_index = $next_co_number;
            
            $stmt->bind_param("sisii", $course_code, $next_co_number, $co_description, $order_index, $user_id);
            
            if ($stmt->execute()) {
                $new_co_id = $conn->insert_id;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Course outcome added successfully!',
                    'co_id' => $new_co_id,
                    'co_number' => $next_co_number,
                    'co_description' => $co_description
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
            }
            break;
            
        // ============================================
        // UPDATE COURSE OUTCOME
        // ============================================
        case 'update_outcome':
            $co_id = intval($_POST['co_id'] ?? 0);
            $co_description = trim($_POST['co_description'] ?? '');
            
            // Validate required fields
            if ($co_id <= 0 || empty($co_description)) {
                echo json_encode(['success' => false, 'message' => 'CO ID and description required']);
                exit;
            }
            
            // Validate description length
            if (strlen($co_description) < 10) {
                echo json_encode(['success' => false, 'message' => 'Description must be at least 10 characters']);
                exit;
            }
            
            // Update CO
            $stmt = $conn->prepare("UPDATE course_outcomes SET co_description = ?, updated_at = NOW() WHERE co_id = ?");
            
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database prepare error']);
                exit;
            }
            
            $stmt->bind_param("si", $co_description, $co_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Course outcome updated successfully!'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No changes made or CO not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Update failed']);
            }
            break;
            
        // ============================================
        // DELETE COURSE OUTCOME
        // ============================================
        case 'delete_outcome':
            $co_id = intval($_GET['co_id'] ?? $_POST['co_id'] ?? 0);
            
            if ($co_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'CO ID required']);
                exit;
            }
            
            // Get CO info before deleting (for logging)
            $info_stmt = $conn->prepare("SELECT course_code, co_number FROM course_outcomes WHERE co_id = ?");
            $info_stmt->bind_param("i", $co_id);
            $info_stmt->execute();
            $info_stmt->bind_result($course_code, $co_number);
            $info_stmt->fetch();
            $info_stmt->close();
            
            // Delete CO (cascade will delete mappings)
            $stmt = $conn->prepare("DELETE FROM course_outcomes WHERE co_id = ?");
            
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database prepare error']);
                exit;
            }
            
            $stmt->bind_param("i", $co_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Course outcome deleted successfully!'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'CO not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Delete failed']);
            }
            break;
            
        // ============================================
        // REORDER COURSE OUTCOMES
        // ============================================
        case 'reorder_outcomes':
            $order_data = json_decode($_POST['order_data'] ?? '[]', true);
            
            if (empty($order_data)) {
                echo json_encode(['success' => false, 'message' => 'No order data provided']);
                exit;
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                $stmt = $conn->prepare("UPDATE course_outcomes SET order_index = ? WHERE co_id = ?");
                
                foreach ($order_data as $item) {
                    $co_id = intval($item['co_id'] ?? 0);
                    $order_index = intval($item['order_index'] ?? 0);
                    
                    if ($co_id > 0) {
                        $stmt->bind_param("ii", $order_index, $co_id);
                        $stmt->execute();
                    }
                }
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Order updated successfully!'
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Reorder failed: ' . $e->getMessage()]);
            }
            break;
            
        // ============================================
        // GET COURSE OUTCOME COUNT
        // ============================================
        case 'get_count':
            $course_code = $_GET['course_code'] ?? $_POST['course_code'] ?? '';
            
            if (empty($course_code)) {
                echo json_encode(['success' => false, 'message' => 'Course code required']);
                exit;
            }
            
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM course_outcomes WHERE course_code = ?");
            $stmt->bind_param("s", $course_code);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Course Outcomes API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

exit;
?>