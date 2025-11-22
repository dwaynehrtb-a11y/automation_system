<?php

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require_once '../../config/db.php';
require_once '../../config/session.php';

// Security check
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// CSRF Token validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$faculty_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        // ============================================
        // TERM WEIGHTS
        // ============================================
        case 'get_term_weights':
            $class_code = $_POST['class_code'] ?? '';
            if (empty($class_code)) {
                throw new Exception('Class code required');
            }
            $result = getTermWeights($conn, $class_code, $faculty_id);
            break;
            
        case 'update_term_weights':
            $class_code = $_POST['class_code'] ?? '';
            $midterm_weight = $_POST['midterm_weight'] ?? '';
            $finals_weight = $_POST['finals_weight'] ?? '';
            
            if (empty($class_code) || $midterm_weight === '' || $finals_weight === '') {
                throw new Exception('All fields required');
            }
            
            $result = updateTermWeights($conn, $class_code, $midterm_weight, $finals_weight, $faculty_id);
            break;
            
        // ============================================
        // COMPONENTS
        // ============================================
        case 'get_components':
            $class_code = $_POST['class_code'] ?? '';
            $term_type = $_POST['term_type'] ?? '';
            
            if (empty($class_code) || empty($term_type)) {
                throw new Exception('Class code and term type required');
            }
            
            $result = getComponents($conn, $class_code, $term_type, $faculty_id);
            break;
            
        case 'add_component':
            $class_code = $_POST['class_code'] ?? '';
            $term_type = $_POST['term_type'] ?? '';
            $component_name = $_POST['component_name'] ?? '';
            $percentage = $_POST['percentage'] ?? '';
            
            if (empty($class_code) || empty($term_type) || empty($component_name) || $percentage === '') {
                throw new Exception('All fields required');
            }
            
            $result = addComponent($conn, $class_code, $term_type, $component_name, $percentage, $faculty_id);
            break;
            
        case 'update_component':
            $component_id = $_POST['component_id'] ?? '';
            $component_name = $_POST['component_name'] ?? '';
            $percentage = $_POST['percentage'] ?? '';
            
            if (empty($component_id) || empty($component_name) || $percentage === '') {
                throw new Exception('All fields required');
            }
            
            $result = updateComponent($conn, $component_id, $component_name, $percentage, $faculty_id);
            break;
            
        case 'delete_component':
            $component_id = $_POST['component_id'] ?? '';
            
            if (empty($component_id)) {
                throw new Exception('Component ID required');
            }
            
            $result = deleteComponent($conn, $component_id, $faculty_id);
            break;
            
        // COLUMNS/ITEMS// 

       case 'add_column':
    $component_id = $_POST['component_id'] ?? '';
    $column_name = $_POST['column_name'] ?? '';
    $max_score = $_POST['max_score'] ?? 100;
    $co_mappings = $_POST['co_mappings'] ?? '[]'; // 
    
    if (empty($component_id) || empty($column_name)) {
        throw new Exception('Component ID and column name required');
    }
    $co_array = json_decode($co_mappings, true);
    if ($co_array === null) {
        $co_array = [];
    } 
    $result = addColumn($conn, $component_id, $column_name, $max_score, $faculty_id, $co_array); // 
    break;
        case 'update_column':
            $column_id = $_POST['column_id'] ?? '';
            $max_score = $_POST['max_score'] ?? '';
            
            if (empty($column_id) || $max_score === '') {
                throw new Exception('Column ID and max score required');
            }
            
            $result = updateColumn($conn, $column_id, $max_score, $faculty_id);
            break;
            
        case 'update_column_full':
            $column_id = $_POST['column_id'] ?? '';
            $column_name = $_POST['column_name'] ?? '';
            $max_score = $_POST['max_score'] ?? '';
            
            if (empty($column_id) || empty($column_name) || $max_score === '') {
                throw new Exception('All fields required');
            }
            
            $result = updateColumnFull($conn, $column_id, $column_name, $max_score, $faculty_id);
            break;
            
        case 'delete_column':
            $column_id = $_POST['column_id'] ?? '';
            
            if (empty($column_id)) {
                throw new Exception('Column ID required');
            }
            
            $result = deleteColumn($conn, $column_id, $faculty_id);
            break;
            
        case 'delete_columns_bulk':
            $column_ids_json = $_POST['column_ids'] ?? '[]';
            $column_ids = json_decode($column_ids_json, true);
            
            if (empty($column_ids) || !is_array($column_ids)) {
                throw new Exception('No columns selected for deletion');
            }
            
            $deleted_count = 0;
            foreach ($column_ids as $col_id) {
                $col_id = intval($col_id);
                if ($col_id > 0) {
                    $del_result = deleteColumn($conn, $col_id, $faculty_id);
                    if ($del_result['success']) {
                        $deleted_count++;
                    }
                }
            }
            
            $result = [
                'success' => true,
                'message' => "$deleted_count column(s) deleted successfully",
                'deleted_count' => $deleted_count
            ];
            break;
            
case 'bulk_add_columns':
    $component_id = $_POST['component_id'] ?? '';
    $base_name = $_POST['base_name'] ?? '';
    $start_number = $_POST['start_number'] ?? 1;
    $count = $_POST['count'] ?? 1;
    $max_score = $_POST['max_score'] ?? 100;
    $co_mappings = $_POST['co_mappings'] ?? '[]'; 
    
    if (empty($component_id) || empty($base_name)) {
        throw new Exception('Component ID and base name required');
    }
    
    // Decode CO mappings JSON
    $co_array = json_decode($co_mappings, true);
    if ($co_array === null) {
        $co_array = [];
    }
    
    $result = bulkAddColumns($conn, $component_id, $base_name, $start_number, $count, $max_score, $faculty_id, $co_array); 
    break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Manage Grading Components Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

// ============================================
// TERM WEIGHTS FUNCTIONS
// ============================================

/**
 * Get term weights for a class
 */
function getTermWeights($conn, $class_code, $faculty_id) {
    // Verify faculty owns this class
    $verify = $conn->prepare("
        SELECT class_id 
        FROM class 
        WHERE class_code = ? AND faculty_id = ? 
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("si", $class_code, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Class not found or access denied'];
    }
    $verify->close();
    
    // Get or create term weights
    $query = "SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Create default weights if not exists
        $insert = $conn->prepare("
            INSERT INTO class_term_weights (class_code, midterm_weight, finals_weight, updated_by) 
            VALUES (?, 40.00, 60.00, ?)
        ");
        $insert->bind_param("si", $class_code, $faculty_id);
        $insert->execute();
        $insert->close();
        
        $stmt->close();
        return [
            'success' => true,
            'midterm_weight' => 40.00,
            'finals_weight' => 60.00
        ];
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return [
        'success' => true,
        'midterm_weight' => floatval($row['midterm_weight']),
        'finals_weight' => floatval($row['finals_weight'])
    ];
}

/**
 * Update term weights for a class
 */
function updateTermWeights($conn, $class_code, $midterm_weight, $finals_weight, $faculty_id) {
    // Verify faculty owns this class
    $verify = $conn->prepare("
        SELECT class_id 
        FROM class 
        WHERE class_code = ? AND faculty_id = ? 
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("si", $class_code, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Class not found or access denied'];
    }
    $verify->close();
    
    // Validate weights
    $midterm = floatval($midterm_weight);
    $finals = floatval($finals_weight);
    
    if ($midterm < 0 || $midterm > 100 || $finals < 0 || $finals > 100) {
        return ['success' => false, 'message' => 'Weights must be between 0 and 100'];
    }
    
    if (abs(($midterm + $finals) - 100) > 0.01) {
        return ['success' => false, 'message' => 'Weights must total 100%'];
    }
    
    // Update or insert term weights
    $stmt = $conn->prepare("
        INSERT INTO class_term_weights (class_code, midterm_weight, finals_weight, updated_by) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            midterm_weight = VALUES(midterm_weight),
            finals_weight = VALUES(finals_weight),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP
    ");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("sddi", $class_code, $midterm, $finals, $faculty_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Term weights updated successfully',
            'midterm_weight' => $midterm,
            'finals_weight' => $finals
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Failed to update term weights: ' . $error);
    }
}

// ============================================
// COMPONENT FUNCTIONS
// ============================================

/**
 * Get components for a class and term
 */
function getComponents($conn, $class_code, $term_type, $faculty_id) {
    // Verify faculty owns this class
    $verify = $conn->prepare("
        SELECT class_id 
        FROM class 
        WHERE class_code = ? AND faculty_id = ? 
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("si", $class_code, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Class not found or access denied'];
    }
    $verify->close();
    
    // Get components for the specified term
    $query = "
        SELECT 
            gc.id,
            gc.component_name,
            gc.percentage,
            gc.term_type,
            gc.order_index,
            gc.created_at
        FROM grading_components gc
        WHERE gc.class_code = ? AND gc.term_type = ?
        ORDER BY gc.order_index, gc.id
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("ss", $class_code, $term_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $components = [];
    while ($row = $result->fetch_assoc()) {
        // Get columns for this component
        $columns = getComponentColumns($conn, $row['id']);
        $row['columns'] = $columns;
        $components[] = $row;
    }
    $stmt->close();
    
    // Get ALL components (for summary calculation)
    $all_query = "
        SELECT 
            gc.id,
            gc.component_name,
            gc.percentage,
            gc.term_type,
            gc.order_index
        FROM grading_components gc
        WHERE gc.class_code = ?
        ORDER BY gc.term_type, gc.order_index, gc.id
    ";
    
    $all_stmt = $conn->prepare($all_query);
    $all_stmt->bind_param("s", $class_code);
    $all_stmt->execute();
    $all_result = $all_stmt->get_result();
    
    $all_components = [];
    while ($row = $all_result->fetch_assoc()) {
        $columns = getComponentColumns($conn, $row['id']);
        $row['columns'] = $columns;
        $all_components[] = $row;
    }
    $all_stmt->close();
    
    return [
        'success' => true,
        'components' => $components,
        'all_components' => $all_components
    ];
}

/**
 * Get columns for a component
 */
function getComponentColumns($conn, $component_id) {
    $query = "
        SELECT 
            id,
            column_name,
            max_score,
            order_index,
            co_mappings,
            is_summative,
            performance_target
        FROM grading_component_columns
        WHERE component_id = ?
        ORDER BY order_index, id
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $component_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        if (isset($row['co_mappings']) && !empty($row['co_mappings'])) {
            $row['co_mappings'] = json_decode($row['co_mappings'], true) ?: [];
        } else {
            $row['co_mappings'] = [];
        }
        
        $columns[] = $row;
    }
    $stmt->close();
    
    return $columns;
}

/**
 * Add a new component
 */
function addComponent($conn, $class_code, $term_type, $component_name, $percentage, $faculty_id) {
    // Verify faculty owns this class
    $verify = $conn->prepare("
        SELECT class_id 
        FROM class 
        WHERE class_code = ? AND faculty_id = ? 
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("si", $class_code, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Class not found or access denied'];
    }
    $verify->close();
    
    // Validate percentage
    $pct = floatval($percentage);
    if ($pct <= 0 || $pct > 100) {
        return ['success' => false, 'message' => 'Percentage must be between 0 and 100'];
    }
    
    // ✅ VALIDATE: Check if adding this component exceeds term limit
    // Get term weights
    $weights = getTermWeights($conn, $class_code, $faculty_id);
    if (!$weights['success']) {
        return $weights; // Return error
    }
    
    $term_limit = $term_type === 'midterm' ? $weights['midterm_weight'] : $weights['finals_weight'];
    
    // Get current total for this term
    $total_query = "SELECT COALESCE(SUM(percentage), 0) as total FROM grading_components WHERE class_code = ? AND term_type = ?";
    $total_stmt = $conn->prepare($total_query);
    $total_stmt->bind_param("ss", $class_code, $term_type);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
    $current_total = $total_result->fetch_assoc()['total'];
    $total_stmt->close();
    
    // Check if adding this component would exceed the limit
    if (($current_total + $pct) > $term_limit) {
        $remaining = $term_limit - $current_total;
        return [
            'success' => false, 
            'message' => "Cannot add component. Only {$remaining}% remaining out of {$term_limit}% {$term_type} allocation."
        ];
    }
    
    // Get current order index
    $order_query = "SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM grading_components WHERE class_code = ? AND term_type = ?";
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->bind_param("ss", $class_code, $term_type);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $next_order = $order_result->fetch_assoc()['next_order'];
    $order_stmt->close();
    
    // Insert component
    $stmt = $conn->prepare("
        INSERT INTO grading_components (class_code, term_type, component_name, percentage, order_index, created_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("sssdii", $class_code, $term_type, $component_name, $pct, $next_order, $faculty_id);
    
    if ($stmt->execute()) {
        $component_id = $conn->insert_id;
        $stmt->close();
        
        return [
            'success' => true,
            'message' => 'Component added successfully',
            'component_id' => $component_id
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Failed to add component: ' . $error);
    }
}

/**
 * Update a component
 */
function updateComponent($conn, $component_id, $component_name, $percentage, $faculty_id) {
    // Verify faculty owns this component's class
    $verify = $conn->prepare("
        SELECT gc.id 
        FROM grading_components gc
        INNER JOIN class c ON gc.class_code = c.class_code
        WHERE gc.id = ? AND c.faculty_id = ?
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("ii", $component_id, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Component not found or access denied'];
    }
    $verify->close();
    
    // Validate percentage
    $pct = floatval($percentage);
    if ($pct < 0 || $pct > 100) {
        return ['success' => false, 'message' => 'Percentage must be between 0 and 100'];
    }
    
    // Update component
    $stmt = $conn->prepare("
        UPDATE grading_components 
        SET component_name = ?, percentage = ?
        WHERE id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("sdi", $component_name, $pct, $component_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Component updated successfully'
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Failed to update component: ' . $error);
    }
}

/**
 * Delete a component
 */
function deleteComponent($conn, $component_id, $faculty_id) {
    // Verify faculty owns this component's class
    $verify = $conn->prepare("
        SELECT gc.id 
        FROM grading_components gc
        INNER JOIN class c ON gc.class_code = c.class_code
        WHERE gc.id = ? AND c.faculty_id = ?
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("ii", $component_id, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Component not found or access denied'];
    }
    $verify->close();
    
    // Delete grades associated with this component's columns
    $conn->query("
        DELETE g FROM student_flexible_grades g
        INNER JOIN grading_component_columns gcc ON g.column_id = gcc.id
        WHERE gcc.component_id = $component_id
    ");
    
    // Delete columns
    $conn->query("DELETE FROM grading_component_columns WHERE component_id = $component_id");
    
    // Delete component
    $stmt = $conn->prepare("DELETE FROM grading_components WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $component_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Component deleted successfully'
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Failed to delete component: ' . $error);
    }
}

// COLUMN/ITEM FUNCTIONS// 

/** Add a new column/item to a component*/
function addColumn($conn, $component_id, $column_name, $max_score, $faculty_id, $co_mappings = []) {
    // Verify faculty owns this component's class
    $verify = $conn->prepare("
        SELECT gc.id 
        FROM grading_components gc
        INNER JOIN class c ON gc.class_code = c.class_code
        WHERE gc.id = ? AND c.faculty_id = ?
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("ii", $component_id, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Component not found or access denied'];
    }
    $verify->close();
    
    // Validate max score
    $max = intval($max_score);
    if ($max <= 0) {
        return ['success' => false, 'message' => 'Max score must be greater than 0'];
    }
    
    // Get current order index
    $order_query = "SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM grading_component_columns WHERE component_id = ?";
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->bind_param("i", $component_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $next_order = $order_result->fetch_assoc()['next_order'];
    $order_stmt->close();
    
    // Get is_summative and performance_target from POST
$is_summative = $_POST['is_summative'] ?? 'no';
$performance_target = floatval($_POST['performance_target'] ?? 60.00);

//  Prepare CO mappings JSON
$co_mappings_json = !empty($co_mappings) && is_array($co_mappings) ? json_encode($co_mappings) : NULL;

//  Insert column WITH co_mappings, is_summative, and performance_target
$stmt = $conn->prepare("
    INSERT INTO grading_component_columns (component_id, column_name, max_score, order_index, co_mappings, is_summative, performance_target) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("isiissd", $component_id, $column_name, $max, $next_order, $co_mappings_json, $is_summative, $performance_target);
    
    if ($stmt->execute()) {
        $column_id = $conn->insert_id;
        $stmt->close();
        
        return [
            'success' => true,
            'message' => 'Item added successfully',
            'column_id' => $column_id
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Failed to add item: ' . $error);
    }
}

/**
 * Update a column's max score
 */
function updateColumn($conn, $column_id, $max_score, $faculty_id) {
    // Verify faculty owns this column's component's class
    $verify = $conn->prepare("
        SELECT gcc.id 
        FROM grading_component_columns gcc
        INNER JOIN grading_components gc ON gcc.component_id = gc.id
        INNER JOIN class c ON gc.class_code = c.class_code
        WHERE gcc.id = ? AND c.faculty_id = ?
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("ii", $column_id, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Column not found or access denied'];
    }
    $verify->close();
    
    // Validate max score
    $max = intval($max_score);
    if ($max <= 0) {
        return ['success' => false, 'message' => 'Max score must be greater than 0'];
    }
    
    // Update column
    $stmt = $conn->prepare("UPDATE grading_component_columns SET max_score = ? WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $max, $column_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Max score updated successfully'
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Failed to update max score: ' . $error);
    }
}

/**
 * Update a column's name and max score
 */
function updateColumnFull($conn, $column_id, $column_name, $max_score, $faculty_id) {
    $verify = $conn->prepare("
        SELECT gcc.id 
        FROM grading_component_columns gcc
        INNER JOIN grading_components gc ON gcc.component_id = gc.id
        INNER JOIN class c ON gc.class_code = c.class_code
        WHERE gcc.id = ? AND c.faculty_id = ?
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("ii", $column_id, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Column not found or access denied'];
    }
    $verify->close();
    
    $max = intval($max_score);
    if ($max <= 0) {
        return ['success' => false, 'message' => 'Max score must be greater than 0'];
    }
    
    // Get CO mappings, is_summative, and performance_target from POST
    $co_mappings = $_POST['co_mappings'] ?? '[]';
    $co_array = json_decode($co_mappings, true);
    if ($co_array === null) {
        $co_array = [];
    }
    $co_mappings_json = !empty($co_array) ? json_encode($co_array) : NULL;
    
    $is_summative = $_POST['is_summative'] ?? 'no';
    $performance_target = floatval($_POST['performance_target'] ?? 60.00);
    
    $stmt = $conn->prepare("
        UPDATE grading_component_columns 
        SET column_name = ?, 
            max_score = ?, 
            co_mappings = ?,
            is_summative = ?,
            performance_target = ?
        WHERE id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("sissdi", $column_name, $max, $co_mappings_json, $is_summative, $performance_target, $column_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Item updated successfully'
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Failed to update item: ' . $error);
    }
}

/**
 * Delete a column/item
 */
function deleteColumn($conn, $column_id, $faculty_id) {
    // Verify faculty owns this column's component's class
    $verify = $conn->prepare("
        SELECT gcc.id 
        FROM grading_component_columns gcc
        INNER JOIN grading_components gc ON gcc.component_id = gc.id
        INNER JOIN class c ON gc.class_code = c.class_code
        WHERE gcc.id = ? AND c.faculty_id = ?
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("ii", $column_id, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Column not found or access denied'];
    }
    $verify->close();
    
    // Delete grades for this column (FIXED - using prepared statement)
    $delete_grades = $conn->prepare("DELETE FROM student_flexible_grades WHERE column_id = ?");
    $delete_grades->bind_param("i", $column_id);
    $delete_grades->execute();
    $delete_grades->close();
    
    // Delete column
    $stmt = $conn->prepare("DELETE FROM grading_component_columns WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $column_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Item deleted successfully'
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Failed to delete item: ' . $error);
    }
}
/**
 * Bulk add columns/items to a component
 */
function bulkAddColumns($conn, $component_id, $base_name, $start_number, $count, $max_score, $faculty_id, $co_mappings = []) {
    // Verify faculty owns this component's class
    $verify = $conn->prepare("
        SELECT gc.id 
        FROM grading_components gc
        INNER JOIN class c ON gc.class_code = c.class_code
        WHERE gc.id = ? AND c.faculty_id = ?
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("ii", $component_id, $faculty_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Component not found or access denied'];
    }
    $verify->close();
    
    // Validate inputs
    $count = intval($count);
    $start_number = intval($start_number);
    $max = intval($max_score);
    
    if ($count < 1 || $count > 20) {
        return ['success' => false, 'message' => 'Count must be between 1 and 20'];
    }
    
    if ($max <= 0) {
        return ['success' => false, 'message' => 'Max score must be greater than 0'];
    }
    
    // Get current max order index
    $order_query = "SELECT COALESCE(MAX(order_index), 0) as max_order FROM grading_component_columns WHERE component_id = ?";
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->bind_param("i", $component_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $current_order = $order_result->fetch_assoc()['max_order'];
    $order_stmt->close();
    
    // Get is_summative and performance_target from POST
$is_summative = $_POST['is_summative'] ?? 'no';
$performance_target = floatval($_POST['performance_target'] ?? 60.00);

//  Prepare CO mappings JSON
$co_mappings_json = !empty($co_mappings) && is_array($co_mappings) ? json_encode($co_mappings) : NULL;

//  Prepare insert statement WITH co_mappings, is_summative, and performance_target
$stmt = $conn->prepare("
    INSERT INTO grading_component_columns (component_id, column_name, max_score, order_index, co_mappings, is_summative, performance_target) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $added_count = 0;
    
    // Insert multiple columns
    for ($i = 0; $i < $count; $i++) {
        $number = $start_number + $i;
        $column_name = trim($base_name) . " " . $number;
        $order_index = $current_order + $i + 1;
        
        $stmt->bind_param("isiissd", $component_id, $column_name, $max, $order_index, $co_mappings_json, $is_summative, $performance_target);
        
        if ($stmt->execute()) {
            $added_count++;
        }
    }
    
    $stmt->close();
    
    if ($added_count === $count) {
        return [
            'success' => true,
            'message' => "Successfully added {$added_count} items",
            'added_count' => $added_count
        ];
    } else {
        return [
            'success' => false,
            'message' => "Only added {$added_count} out of {$count} items"
        ];
    }
}
$conn->close();
?>