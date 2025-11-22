<?php
define('SYSTEM_ACCESS', true);
require_once '../config/session.php';
require_once '../config/db.php';

// DEBUG: Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DEBUG: Log everything
error_log("=== DEBUG get_class_schedules.php ===");
error_log("Session status: " . session_status());
error_log("Session ID: " . session_id());
error_log("User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Role: " . ($_SESSION['role'] ?? 'NOT SET'));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("GET parameters: " . print_r($_GET, true));

// Set content type to JSON
header('Content-Type: application/json');

// DEBUG: More permissive check temporarily
if (!isset($_SESSION['user_id'])) {
    error_log("FAILING: No user_id in session");
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No user session found',
        'debug' => [
            'session_status' => session_status(),
            'session_id' => session_id(),
            'has_user_id' => isset($_SESSION['user_id']),
            'has_role' => isset($_SESSION['role']),
            'session_keys' => array_keys($_SESSION ?? [])
        ]
    ]);
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'faculty'])) {
    error_log("FAILING: Wrong role - " . ($_SESSION['role'] ?? 'NO ROLE'));
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Wrong user role: ' . ($_SESSION['role'] ?? 'NO ROLE'),
        'debug' => [
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'] ?? 'NO ROLE'
        ]
    ]);
    exit;
}

error_log("SUCCESS: Authorization passed for user " . $_SESSION['user_id'] . " with role " . $_SESSION['role']);

// Get parameters from URL
$section = $_GET['section'] ?? '';
$academic_year = $_GET['academic_year'] ?? '';
$term = $_GET['term'] ?? '';
$course_code = $_GET['course_code'] ?? '';
$faculty_id = $_GET['faculty_id'] ?? ''; // Optional for enhanced editing

// Validate required parameters (faculty_id is optional)
if (empty($section) || empty($academic_year) || empty($term) || empty($course_code)) {
    error_log("FAILING: Missing required parameters");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameters: section, academic_year, term, course_code',
        'received' => [
            'section' => $section,
            'academic_year' => $academic_year,
            'term' => $term,
            'course_code' => $course_code,
            'faculty_id' => $faculty_id
        ]
    ]);
    exit;
}

try {
    // Build query dynamically based on available parameters
    $query = "
        SELECT 
            c.class_id,
            c.section,
            c.academic_year,
            c.term,
            c.course_code,
            c.day,
            c.time,
            c.room,
            c.faculty_id,
            s.course_title,
            s.units,
            u.name as faculty_name,
            u.employee_id
        FROM class c
        LEFT JOIN subjects s ON c.course_code = s.course_code
        LEFT JOIN users u ON c.faculty_id = u.id
        WHERE c.section = ? 
        AND c.academic_year = ? 
        AND c.term = ? 
        AND c.course_code = ?
    ";
    
    $params = [$section, $academic_year, $term, $course_code];
    $types = 'ssss';
    
    // Add faculty_id condition if provided
    if (!empty($faculty_id)) {
        $query .= " AND c.faculty_id = ?";
        $params[] = intval($faculty_id);
        $types .= 'i';
    }
    
    $query .= " ORDER BY FIELD(c.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), c.time";
    
    error_log("Query: " . $query);
    error_log("Parameters: " . print_r($params, true));

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $schedules = [];

    while ($row = $result->fetch_assoc()) {
        $schedules[] = [
            'class_id' => intval($row['class_id']),
            'section' => $row['section'],
            'academic_year' => $row['academic_year'],
            'term' => $row['term'],
            'course_code' => $row['course_code'],
            'course_title' => $row['course_title'] ?? '',
            'units' => $row['units'],
            'day' => $row['day'],
            'time' => $row['time'],
            'room' => $row['room'],
            'faculty_id' => intval($row['faculty_id']),
            'faculty_name' => $row['faculty_name'] ?? 'Unassigned',
            'employee_id' => $row['employee_id']
        ];
    }

    $stmt->close();

    error_log("Found " . count($schedules) . " schedules");

    // Return the schedules data
    echo json_encode([
        'success' => true,
        'data' => $schedules,
        'count' => count($schedules),
        'message' => 'Schedules loaded successfully',
        'query_params' => [
            'section' => $section,
            'academic_year' => $academic_year,
            'term' => $term,
            'course_code' => $course_code,
            'faculty_id' => $faculty_id
        ]
    ]);

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'query_params' => [
            'section' => $section,
            'academic_year' => $academic_year,
            'term' => $term,
            'course_code' => $course_code,
            'faculty_id' => $faculty_id
        ]
    ]);
}
?>