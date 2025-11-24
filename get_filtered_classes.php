<?php
// Prevent duplicate session starts
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/get_filtered_classes_error.log');

// Direct database connection to avoid config issues
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "automation_system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get filter parameters
$academic_year = $_POST['academic_year'] ?? '';
$term = $_POST['term'] ?? '';

if (empty($academic_year) || empty($term)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters', 'received' => $_POST]);
    exit;
}

try {
    // Query to get classes with schedule (day and time columns)
    // Group by class_code and section to avoid duplicates
    $query = "
        SELECT 
            MIN(c.class_id) as class_id,
            c.class_code,
            c.course_code,
            c.section,
            c.room,
            GROUP_CONCAT(DISTINCT CONCAT(c.day, ' ', COALESCE(c.time, '')) ORDER BY FIELD(c.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') SEPARATOR ', ') as schedule,
            MIN(s.course_title) as course_title,
            MIN(u.name) as faculty_name
        FROM class c
        LEFT JOIN subjects s ON c.course_code = s.course_code
        LEFT JOIN users u ON c.faculty_id = u.id
        WHERE c.academic_year = ? 
        AND c.term = ?
        GROUP BY c.class_code, c.section, c.course_code, c.room
        ORDER BY c.course_code, c.section
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    if (!$stmt->bind_param("ss", $academic_year, $term)) {
        throw new Exception('Bind failed: ' . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = [
            'class_id' => $row['class_id'],
            'class_code' => $row['class_code'],
            'subject_code' => $row['course_code'] ?? 'N/A',
            'subject_name' => $row['course_title'] ?? 'Unknown Subject',
            'section' => $row['section'] ?? 'N/A',
            'schedule' => $row['schedule'] ?? 'N/A',
            'room' => $row['room'] ?? 'N/A',
            'faculty_name' => $row['faculty_name'] ?? 'TBA'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'classes' => $classes,
        'count' => count($classes)
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    if (isset($conn)) {
        $conn->close();
    }
}
