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

// Check if user is logged in (basic check)
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
        case 'delete':
            // Check both GET and POST for section ID
            $section_id = $_GET['id'] ?? $_POST['id'] ?? '';

            if (empty($section_id)) {
                echo json_encode(['success' => false, 'message' => 'No section ID provided']);
                exit;
            }

            // First, get the section code
            $get_code_stmt = $conn->prepare("SELECT section_code FROM sections WHERE section_id = ?");
            $get_code_stmt->bind_param("i", $section_id);
            $get_code_stmt->execute();
            $get_code_stmt->bind_result($section_code);
            $get_code_stmt->fetch();
            $get_code_stmt->close();

            // Check if there are any classes using this section
            $check_classes_stmt = $conn->prepare("SELECT COUNT(*) FROM class WHERE section = ?");
            $check_classes_stmt->bind_param("s", $section_code);
            $check_classes_stmt->execute();
            $check_classes_stmt->bind_result($class_count);
            $check_classes_stmt->fetch();
            $check_classes_stmt->close();

            if ($class_count > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete this section. It is currently used by $class_count class" . ($class_count > 1 ? 'es' : '') . ". Please delete or reassign those classes first."]);
                exit;
            }

            // Delete from sections table
            $stmt = $conn->prepare("DELETE FROM sections WHERE section_id = ?");
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database prepare error']);
                exit;
            }

            $stmt->bind_param("i", $section_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Section deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Section not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Delete failed']);
            }
            break;

        case 'add':
            $section_code = strtoupper(trim($_POST['section_code'] ?? ''));

            if (empty($section_code)) {
                echo json_encode(['success' => false, 'message' => 'Section code is required']);
                exit;
            }

            // Validate section code format
            if (!preg_match('/^[A-Z0-9\-_]{3,20}$/', $section_code)) {
                echo json_encode(['success' => false, 'message' => 'Invalid section code format. Use only letters, numbers, hyphens, and underscores (3-20 characters)']);
                exit;
            }

            // Check if section already exists
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM sections WHERE section_code = ?");
            $check_stmt->bind_param("s", $section_code);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();
            
            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => 'Section code already exists']);
                exit;
            }

            // Insert new section
            $stmt = $conn->prepare("INSERT INTO sections (section_code, created_at) VALUES (?, NOW())");
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Database prepare error']);
                exit;
            }

            $stmt->bind_param("s", $section_code);

            if ($stmt->execute()) {
                $section_id = $conn->insert_id;
                echo json_encode([
                    'success' => true, 
                    'message' => 'Section added successfully!',
                    'newItem' => [
                        'section_id' => $section_id,
                        'section_code' => $section_code,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Insert failed']);
            }
            break;

        case 'update':
            $section_id = intval($_POST['section_id'] ?? 0);
            $section_code = strtoupper(trim($_POST['section_code'] ?? ''));

            if ($section_id <= 0 || empty($section_code)) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }

            // Validate section code format
            if (!preg_match('/^[A-Z0-9\-_]{3,20}$/', $section_code)) {
                echo json_encode(['success' => false, 'message' => 'Invalid section code format']);
                exit;
            }

            // Check if new section code already exists (excluding current record)
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM sections WHERE section_code = ? AND section_id != ?");
            $check_stmt->bind_param("si", $section_code, $section_id);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();
            
            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => 'Section code already exists']);
                exit;
            }

            // First, get the old section code so we can update related classes
            $old_code_stmt = $conn->prepare("SELECT section_code FROM sections WHERE section_id = ?");
            $old_code_stmt->bind_param("i", $section_id);
            $old_code_stmt->execute();
            $old_code_stmt->bind_result($old_section_code);
            $old_code_stmt->fetch();
            $old_code_stmt->close();

            // Start transaction to ensure consistency
            $conn->begin_transaction();

            try {
                // Update the section
                $stmt = $conn->prepare("UPDATE sections SET section_code = ?, updated_at = NOW() WHERE section_id = ?");
                if (!$stmt) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Database prepare error']);
                    exit;
                }

                $stmt->bind_param("si", $section_code, $section_id);

                if (!$stmt->execute()) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Update failed']);
                    exit;
                }

                if ($stmt->affected_rows == 0) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'No changes made or section not found']);
                    exit;
                }

                // CASCADE: Update all classes that reference the old section code
                if (!empty($old_section_code) && $old_section_code !== $section_code) {
                    $update_classes_stmt = $conn->prepare("UPDATE class SET section = ? WHERE section = ?");
                    if (!$update_classes_stmt) {
                        $conn->rollback();
                        echo json_encode(['success' => false, 'message' => 'Database prepare error for class update']);
                        exit;
                    }

                    $update_classes_stmt->bind_param("ss", $section_code, $old_section_code);
                    
                    if (!$update_classes_stmt->execute()) {
                        $conn->rollback();
                        echo json_encode(['success' => false, 'message' => 'Failed to update related classes']);
                        exit;
                    }

                    $classes_affected = $update_classes_stmt->affected_rows;
                    $update_classes_stmt->close();
                }

                // Commit the transaction
                $conn->commit();
                
                $message = 'Section updated successfully!';
                if (!empty($classes_affected) && $classes_affected > 0) {
                    $message .= " Updated $classes_affected class" . ($classes_affected > 1 ? 'es' : '') . " as well.";
                }

                echo json_encode(['success' => true, 'message' => $message]);

            } catch (Exception $e) {
                $conn->rollback();
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