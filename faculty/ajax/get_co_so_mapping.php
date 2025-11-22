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
$class_code = $_GET['class_code'] ?? '';
$course_code = $_GET['course_code'] ?? '';

// If class_code is provided, extract course_code from it
// Format: 24_T1_CTAPROJ1_INF221 -> CTAPROJ1
if (!empty($class_code) && empty($course_code)) {
    $parts = explode('_', $class_code);
    if (count($parts) >= 3) {
        $course_code = $parts[2];
    }
}

if (empty($course_code)) {
    echo json_encode(['success' => false, 'message' => 'Course code required']);
    exit;
}
try {
    // Get all Course Outcomes for this course
    $stmt = $conn->prepare("
        SELECT 
            co_id,
            co_number,
            co_description,
            order_index
        FROM course_outcomes
        WHERE course_code = ?
        ORDER BY co_number
    ");
    
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $course_outcomes = [];
    while ($row = $result->fetch_assoc()) {
        $co_id = $row['co_id'];
        
        // Get mapped Student Outcomes for this CO
        $so_stmt = $conn->prepare("
            SELECT so_number
            FROM co_so_mapping
            WHERE co_id = ?
            ORDER BY so_number
        ");
        
        $so_stmt->bind_param("i", $co_id);
        $so_stmt->execute();
        $so_result = $so_stmt->get_result();
        
        $mapped_sos = [];
        while ($so_row = $so_result->fetch_assoc()) {
            $mapped_sos[] = intval($so_row['so_number']);
        }
        $so_stmt->close();
        
        $course_outcomes[] = [
            'co_id' => $co_id,
            'co_number' => intval($row['co_number']),
            'description' => $row['co_description'],
            'mapped_sos' => $mapped_sos
        ];
    }
    $stmt->close();
    
    // Get Student Outcomes (hardcoded for now - could be from a table)
    $student_outcomes = [
        [
            'so_number' => 1,
            'description' => 'Analyze a complex computing problem and to apply principles of computing and other relevant disciplines to identify solutions.'
        ],
        [
            'so_number' => 2,
            'description' => 'Design, implement, and evaluate a computing-based solution to meet a given set of computing requirements in the context of the programs.'
        ],
        [
            'so_number' => 3,
            'description' => 'Communicate effectively in a variety of professional contexts.'
        ],
        [
            'so_number' => 4,
            'description' => 'Recognize professional responsibilities and make informed judgements in computing practice based on legal and ethical principles.'
        ],
        [
            'so_number' => 5,
            'description' => 'Function effectively as a member or leader of a team engaged in activities appropriate to the program\'s discipline.'
        ],
        [
            'so_number' => 6,
            'description' => 'Apply computer science theory and software development fundamentals to produce computing-based solutions.'
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'course_code' => $course_code,
        'course_outcomes' => $course_outcomes,
        'student_outcomes' => $student_outcomes
    ]);
    
} catch (Exception $e) {
    error_log("Get CO-SO mapping error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving CO-SO mapping: ' . $e->getMessage()
    ]);
}

exit;
?>