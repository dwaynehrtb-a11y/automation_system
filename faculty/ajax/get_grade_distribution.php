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

if (empty($class_code)) {
    echo json_encode(['success' => false, 'message' => 'Class code required']);
    exit;
}

try {
    // Verify faculty owns this class
    $stmt = $conn->prepare("SELECT class_id FROM class WHERE class_code = ? AND faculty_id = ?");
    $stmt->bind_param("si", $class_code, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found or access denied']);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Get grade distribution from grade_term table
    $stmt = $conn->prepare("
        SELECT 
            term_grade,
            grade_status,
            COUNT(*) as count
        FROM grade_term
        WHERE class_code = ?
        GROUP BY term_grade, grade_status
        ORDER BY term_grade DESC
    ");
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Initialize distribution array
    $distribution = [
    '4.00' => 0,
    '3.50' => 0,
    '3.00' => 0,
    '2.50' => 0,
    '2.00' => 0,
    '1.50' => 0,
    '1.00' => 0,
    'IP' => 0,     
    'INC' => 0,     
    'R' => 0,       
    'DRP' => 0,     
    'FAILED' => 0   
];
    
    $total_students = 0;
    
    while ($row = $result->fetch_assoc()) {
    $grade = $row['term_grade'];
    $status = $row['grade_status'] ?? 'passed';
    $count = (int)$row['count'];
    
    // Handle special statuses first
    if ($status === 'incomplete') {
        $distribution['INC'] += $count;
        $total_students += $count;
    } elseif ($status === 'dropped') {
        $distribution['DRP'] += $count;
        $total_students += $count;
    } elseif ($status === 'in_progress') {
        $distribution['IP'] += $count;
        $total_students += $count;
    } elseif ($status === 'repeat') {
        $distribution['R'] += $count;
        $total_students += $count;
    } elseif ($status === 'failed' || $grade < 1.0) {
        $distribution['FAILED'] += $count;
        $total_students += $count;
    } else {
        // Regular passing grades
        $grade_value = (float)$grade;
        
        // Map to standard grade scale
        if ($grade_value >= 4.0) {
            $distribution['4.00'] += $count;
        } elseif ($grade_value >= 3.5) {
            $distribution['3.50'] += $count;
        } elseif ($grade_value >= 3.0) {
            $distribution['3.00'] += $count;
        } elseif ($grade_value >= 2.5) {
            $distribution['2.50'] += $count;
        } elseif ($grade_value >= 2.0) {
            $distribution['2.00'] += $count;
        } elseif ($grade_value >= 1.5) {
            $distribution['1.50'] += $count;
        } elseif ($grade_value >= 1.0) {
            $distribution['1.00'] += $count;
        } else {
            $distribution['FAILED'] += $count;
        }
        
        $total_students += $count;
    }
}
    $stmt->close();
    
    // Calculate percentages
    $distribution_with_percentage = [];
    foreach ($distribution as $grade => $count) {
        $percentage = $total_students > 0 ? ($count / $total_students) * 100 : 0;
        $distribution_with_percentage[] = [
            'grade' => $grade,
            'count' => $count,
            'percentage' => round($percentage, 2)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'total_students' => $total_students,
        'distribution' => $distribution_with_percentage
    ]);
    
} catch (Exception $e) {
    error_log("Get grade distribution error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error getting grade distribution: ' . $e->getMessage()
    ]);
}

exit;
?>