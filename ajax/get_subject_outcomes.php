<?php
// Set JSON header FIRST before any other output
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Prevent ANY HTML output
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Load database config
    require_once '../config/db.php';
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration error']);
    exit;
}

// Clean output buffer
ob_clean();

// Ensure JSON header is still set
header('Content-Type: application/json');

// Check database connection
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database not connected']);
    exit;
}

// Get course code from request
$course_code = $_GET['code'] ?? '';

if (empty($course_code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No course code provided']);
    exit;
}

try {
    //  Get Course Outcomes
    $stmt = $conn->prepare("
        SELECT 
            co_id,
            co_number, 
            co_description,
            order_index
        FROM course_outcomes 
        WHERE course_code = ? 
        ORDER BY co_number ASC
    ");
    
    if (!$stmt) {
        throw new Exception('Query prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $outcomes = [];
    $co_id_map = []; // Map co_id to co_number for mappings
    
    while ($row = $result->fetch_assoc()) {
        $outcomes[] = [
            'id' => 'db_' . $row['co_id'], // Prefix with 'db_'
            'number' => (int)$row['co_number'],
            'description' => $row['co_description']
        ];
        
        // Store mapping of co_id -> co_number
        $co_id_map[$row['co_id']] = (int)$row['co_number'];
    }
    $stmt->close();
    
    //  Get CO-SO Mappings (FIXED QUERY FOR YOUR TABLE STRUCTURE)
    $stmt = $conn->prepare("
        SELECT 
            m.co_id,
            m.so_number
        FROM co_so_mapping m
        INNER JOIN course_outcomes co ON m.co_id = co.co_id
        WHERE co.course_code = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Mappings query prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mappings = [];
    while ($row = $result->fetch_assoc()) {
        $co_id = $row['co_id'];
        
        // Get the co_number from our map
        if (isset($co_id_map[$co_id])) {
            $mappings[] = [
                'co_id' => 'db_' . $co_id, // Use same ID format as outcomes
                'co_number' => $co_id_map[$co_id],
                'so_number' => (int)$row['so_number']
            ];
        }
    }
    $stmt->close();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'outcomes' => $outcomes,
        'mappings' => $mappings
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    ob_clean(); // Clear any buffered output
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Database error'
    ]);
}

exit;
?>