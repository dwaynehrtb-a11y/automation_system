<?php
//  Prevent ANY HTML output
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

// Check if user is logged in (basic check)
if (!isset($_SESSION['user_id'])) {
echo json_encode(['success' => false, 'message' => 'User not logged in']);
exit;
}

// Validation function for course code
function validateCourseCode($code) {
    // New format: 3-15 alphanumeric characters (e.g., CTAPROJ1, SYSAD101)
    return preg_match('/^[A-Z0-9]{3,15}$/', strtoupper($code));
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
case 'delete':
// Check both GET and POST for course code (since delete can come from URL params)
$course_code = $_GET['code'] ?? $_POST['code'] ?? '';

// Debug: Log what we received
error_log("Delete request - GET code: " . ($_GET['code'] ?? 'not set') . ", POST code: " . ($_POST['code'] ?? 'not set'));
error_log("Full GET data: " . print_r($_GET, true));
error_log("Full POST data: " . print_r($_POST, true));

if (empty($course_code)) {
echo json_encode(['success' => false, 'message' => 'No course code provided. GET: ' . json_encode($_GET) . ' POST: ' . json_encode($_POST)]);
exit;
}

// Simple delete
$stmt = $conn->prepare("DELETE FROM subjects WHERE course_code = ?");
if (!$stmt) {
echo json_encode(['success' => false, 'message' => 'Database prepare error']);
exit;
}

$stmt->bind_param("s", $course_code);

if ($stmt->execute()) {
if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Subject deleted successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Subject not found']);
}
} else {
echo json_encode(['success' => false, 'message' => 'Delete failed']);
}
break;

case 'add':
    $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
    $course_title = trim($_POST['course_title'] ?? '');
    $course_desc = trim($_POST['course_desc'] ?? '');
    $units = intval($_POST['units'] ?? 0);
    
    // NEW: Get course outcomes
    $course_outcomes = $_POST['course_outcomes'] ?? '[]';
    $outcomes_array = json_decode($course_outcomes, true);
    
    //  DEBUG LOG
    error_log("=== ADD SUBJECT REQUEST ===");
    error_log("Course Code: $course_code");
    error_log("Course Outcomes JSON: $course_outcomes");
    error_log("Decoded Outcomes: " . print_r($outcomes_array, true));

    // Validate required fields
    if (empty($course_code) || empty($course_title) || $units <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    // Validate course code format
    if (!validateCourseCode($course_code)) {
        echo json_encode(['success' => false, 'message' => 'Course code must be 3-15 alphanumeric characters (e.g., CTAPROJ1, SYSAD101)']);
        exit;
    }

    // Validate units range
    if ($units < 1 || $units > 5) {
        echo json_encode(['success' => false, 'message' => 'Units must be between 1 and 5']);
        exit;
    }

    // Check if course code already exists
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM subjects WHERE course_code = ?");
    $check_stmt->bind_param("s", $course_code);
    $check_stmt->execute();
    $check_stmt->bind_result($count);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Course code already exists']);
        exit;
    }

    // Begin transaction
    $conn->begin_transaction();
    
    try {
        //  Insert subject FIRST
        error_log("Step 1: Inserting subject...");
        $stmt = $conn->prepare("INSERT INTO subjects (course_code, course_title, course_desc, units) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception('Subject prepare error: ' . $conn->error);
        }
        
        $stmt->bind_param("sssi", $course_code, $course_title, $course_desc, $units);
        
        if (!$stmt->execute()) {
            throw new Exception('Subject insert failed: ' . $stmt->error);
        }
        
        error_log(" Subject inserted successfully");
        
        // 2️ Handle course outcomes
        if (!empty($outcomes_array) && is_array($outcomes_array)) {
            error_log("Step 2: Managing " . count($outcomes_array) . " course outcomes...");
            
            //  DELETE existing outcomes first (in case of re-add/duplicate)
            $delete_stmt = $conn->prepare("DELETE FROM course_outcomes WHERE course_code = ?");
            if (!$delete_stmt) {
                throw new Exception('Delete outcomes prepare error: ' . $conn->error);
            }
            $delete_stmt->bind_param("s", $course_code);
            $delete_stmt->execute();
            $deleted_count = $delete_stmt->affected_rows;
            error_log("   Deleted $deleted_count existing outcomes");
            
            // INSERT new outcomes
            $co_stmt = $conn->prepare("
                INSERT INTO course_outcomes 
                (course_code, co_number, co_description, order_index, created_by) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            if (!$co_stmt) {
                throw new Exception('Course outcomes prepare error: ' . $conn->error);
            }
            
            $user_id = $_SESSION['user_id'] ?? null;
            
            foreach ($outcomes_array as $index => $outcome) {
                $co_number = intval($outcome['number'] ?? 0);
                $co_description = trim($outcome['description'] ?? '');
                
                error_log("  Processing outcome #" . ($index + 1) . ": CO$co_number");
                
                if ($co_number > 0 && !empty($co_description)) {
                    $co_stmt->bind_param("sisii", $course_code, $co_number, $co_description, $co_number, $user_id);
                    
                    if (!$co_stmt->execute()) {
                        throw new Exception("CO$co_number insert failed: " . $co_stmt->error);
                    }
                    
                    error_log("   CO$co_number inserted (ID: " . $co_stmt->insert_id . ")");
                } else {
                    error_log("   Skipped invalid outcome: number=$co_number");
                }
            }
            
            error_log(" All course outcomes inserted");
        } else {
            error_log("No course outcomes to insert");
        }
        // 3️ Handle CO-SO Mappings
$coso_mappings = $_POST['coso_mappings'] ?? '[]';
$mappings_array = json_decode($coso_mappings, true);

if (!empty($mappings_array) && is_array($mappings_array)) {
    error_log("Step 3: Managing " . count($mappings_array) . " CO-SO mappings...");
    
    // Delete existing mappings first
    $delete_stmt = $conn->prepare("
        DELETE m FROM co_so_mapping m
        INNER JOIN course_outcomes co ON m.co_id = co.co_id
        WHERE co.course_code = ?
    ");
    if ($delete_stmt) {
        $delete_stmt->bind_param("s", $course_code);
        $delete_stmt->execute();
        error_log("   Deleted existing mappings");
    }
    
    // Insert new mappings
    $map_stmt = $conn->prepare("
        INSERT INTO co_so_mapping (co_id, so_number, created_by)
        SELECT co.co_id, ?, ?
        FROM course_outcomes co
        WHERE co.course_code = ? AND co.co_number = ?
    ");
    
    if (!$map_stmt) {
        throw new Exception('CO-SO mapping prepare error: ' . $conn->error);
    }
    
    foreach ($mappings_array as $mapping) {
        $co_number = intval($mapping['co_number'] ?? 0);
        $so_number = intval($mapping['so_number'] ?? 0);
        
        if ($co_number > 0 && $so_number > 0) {
            $map_stmt->bind_param("iisi", $so_number, $user_id, $course_code, $co_number);
            
            if (!$map_stmt->execute()) {
                error_log("   Warning: Mapping CO{$co_number}-SO{$so_number} failed: " . $map_stmt->error);
            } else {
                error_log("   ✓ Mapped CO{$co_number} -> SO{$so_number}");
            }
        }
    }
    
    error_log(" All CO-SO mappings processed");
}
        
        // 3️ Commit transaction
        $conn->commit();
        error_log(" Transaction committed successfully");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Subject added successfully!',
            'newItem' => [
                'course_code' => $course_code,
                'course_title' => $course_title,
                'course_desc' => $course_desc,
                'units' => $units
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log(" Transaction rolled back: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    break;

case 'update':
    $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
    $original_code = trim($_POST['original_code'] ?? '');
    $course_title = trim($_POST['course_title'] ?? '');
    $course_desc = trim($_POST['course_desc'] ?? '');
    $units = intval($_POST['units'] ?? 0);
    
    // Get course outcomes and mappings
    $course_outcomes = $_POST['course_outcomes'] ?? '[]';
    $outcomes_array = json_decode($course_outcomes, true);
    
    $coso_mappings = $_POST['coso_mappings'] ?? '[]';
    $mappings_array = json_decode($coso_mappings, true);
    
    // DEBUG LOG
    error_log("=== UPDATE SUBJECT REQUEST ===");
    error_log("Original Code: $original_code");
    error_log("New Code: $course_code");
    error_log("Outcomes JSON: $course_outcomes");
    error_log("Mappings JSON: $coso_mappings");

    // Validate required fields
    if (empty($course_code) || empty($course_title) || $units <= 0 || empty($original_code)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    // Validate course code format
    if (!validateCourseCode($course_code)) {
        echo json_encode(['success' => false, 'message' => 'Course code must be 3-15 alphanumeric characters']);
        exit;
    }

    // Validate units range
    if ($units < 1 || $units > 5) {
        echo json_encode(['success' => false, 'message' => 'Units must be between 1 and 5']);
        exit;
    }

    // Check if new course code already exists (if it's different from original)
    if ($course_code !== $original_code) {
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM subjects WHERE course_code = ?");
        $check_stmt->bind_param("s", $course_code);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => 'Course code already exists']);
            exit;
        }
    }

    // Begin transaction
    $conn->begin_transaction();
    
    try {
        //  Update subject basic info
        error_log("Step 1: Updating subject...");
        $stmt = $conn->prepare("UPDATE subjects SET course_code = ?, course_title = ?, course_desc = ?, units = ? WHERE course_code = ?");
        
        if (!$stmt) {
            throw new Exception('Subject update prepare error: ' . $conn->error);
        }
        
        $stmt->bind_param("sssis", $course_code, $course_title, $course_desc, $units, $original_code);
        
        if (!$stmt->execute()) {
            throw new Exception('Subject update failed: ' . $stmt->error);
        }
        
        error_log(" Subject updated");
        
        // 2️ Handle course outcomes
        error_log("Step 2: Managing course outcomes...");
        
        // Delete existing outcomes for this subject
        $delete_stmt = $conn->prepare("DELETE FROM course_outcomes WHERE course_code = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("s", $original_code);
            $delete_stmt->execute();
            $deleted = $delete_stmt->affected_rows;
            error_log("   Deleted $deleted existing outcomes");
            $delete_stmt->close();
        }
        
        // Insert updated outcomes
        if (!empty($outcomes_array) && is_array($outcomes_array)) {
            $co_stmt = $conn->prepare("
                INSERT INTO course_outcomes 
                (course_code, co_number, co_description, order_index, created_by) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            if (!$co_stmt) {
                throw new Exception('Course outcomes prepare error: ' . $conn->error);
            }
            
            $user_id = $_SESSION['user_id'] ?? null;
            
            foreach ($outcomes_array as $outcome) {
                $co_number = intval($outcome['number'] ?? 0);
                $co_description = trim($outcome['description'] ?? '');
                
                if ($co_number > 0 && !empty($co_description)) {
                    $co_stmt->bind_param("sisii", $course_code, $co_number, $co_description, $co_number, $user_id);
                    
                    if (!$co_stmt->execute()) {
                        throw new Exception("CO$co_number insert failed: " . $co_stmt->error);
                    }
                    
                    error_log("    CO$co_number updated");
                }
            }
            
            $co_stmt->close();
        }
        
        // 3️ Handle CO-SO Mappings
        error_log("Step 3: Managing CO-SO mappings...");
        
        // Delete existing mappings
        $delete_map = $conn->prepare("
            DELETE m FROM co_so_mapping m
            INNER JOIN course_outcomes co ON m.co_id = co.co_id
            WHERE co.course_code = ?
        ");
        
        if ($delete_map) {
            $delete_map->bind_param("s", $original_code);
            $delete_map->execute();
            $deleted_maps = $delete_map->affected_rows;
            error_log("   Deleted $deleted_maps existing mappings");
            $delete_map->close();
        }
        
        // Insert updated mappings
        if (!empty($mappings_array) && is_array($mappings_array)) {
            $map_stmt = $conn->prepare("
                INSERT INTO co_so_mapping (co_id, so_number, created_by)
                SELECT co.co_id, ?, ?
                FROM course_outcomes co
                WHERE co.course_code = ? AND co.co_number = ?
            ");
            
            if (!$map_stmt) {
                throw new Exception('CO-SO mapping prepare error: ' . $conn->error);
            }
            
            $user_id = $_SESSION['user_id'] ?? null;
            
            foreach ($mappings_array as $mapping) {
                $co_number = intval($mapping['co_number'] ?? 0);
                $so_number = intval($mapping['so_number'] ?? 0);
                
                if ($co_number > 0 && $so_number > 0) {
                    $map_stmt->bind_param("iisi", $so_number, $user_id, $course_code, $co_number);
                    
                    if (!$map_stmt->execute()) {
                        error_log("    Mapping CO{$co_number}-SO{$so_number} failed: " . $map_stmt->error);
                    } else {
                        error_log("    Mapped CO{$co_number} -> SO{$so_number}");
                    }
                }
            }
            
            $map_stmt->close();
        }
        
        // 4️ Commit transaction
        $conn->commit();
        error_log(" Transaction committed - Update successful!");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Subject updated successfully!'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log(" Transaction rolled back: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    break;

default:
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    break;
}

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

exit;
?>