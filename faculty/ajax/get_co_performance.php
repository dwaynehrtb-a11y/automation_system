<?php
// Start session first
session_start();

// Prevent HTML output
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    $stmt = $conn->prepare("SELECT class_id, course_code FROM class WHERE class_code = ? AND faculty_id = ?");
    $stmt->bind_param("si", $class_code, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found or access denied']);
        $stmt->close();
        exit;
    }
    
    $class = $result->fetch_assoc();
    $course_code = $class['course_code'];
    $stmt->close();
    
    // Get all Course Outcomes for this subject
    $stmt = $conn->prepare("
        SELECT co_number, co_description 
        FROM course_outcomes 
        WHERE course_code = ? 
        ORDER BY co_number
    ");
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $course_outcomes = [];
    while ($row = $result->fetch_assoc()) {
        $course_outcomes[$row['co_number']] = [
    'co_number' => $row['co_number'],
    'description' => $row['co_description'],
    'summative_assessment' => '',
    'performance_target' => 60,
    'students_met_target' => 0,
    'total_students' => 0,
    'success_rate' => 0,
    'success_rate_display' => '0% (0)'
];
    }
    $stmt->close();
    
    if (empty($course_outcomes)) {
        echo json_encode([
            'success' => true,
            'course_code' => $course_code,
            'co_performance' => [],
            'message' => 'No course outcomes defined for this subject'
        ]);
        exit;
    }
    
    // Get all grading columns with CO mappings for this class
  $stmt = $conn->prepare("
    SELECT 
        gcc.id as column_id,
        gcc.column_name,
        gcc.max_score,
        gcc.co_mappings,
        gcc.is_summative,
        gcc.performance_target,
        gc.component_name,
        gc.term_type
    FROM grading_component_columns gcc
    INNER JOIN grading_components gc ON gcc.component_id = gc.id
    WHERE gc.class_code = ?
    AND gcc.co_mappings IS NOT NULL
    AND gcc.co_mappings != 'null'
    AND gcc.is_summative = 'yes'
");
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
   $columns_with_co = [];
while ($row = $result->fetch_assoc()) {
    $co_mappings = json_decode($row['co_mappings'], true);
    if (is_array($co_mappings) && !empty($co_mappings)) {
        $columns_with_co[] = [
    'column_id' => $row['column_id'],
    'column_name' => $row['column_name'],
    'max_score' => $row['max_score'],
    'co_mappings' => $co_mappings,
    'component_name' => $row['component_name'],
    'term_type' => $row['term_type'],
    'performance_target' => floatval($row['performance_target'])
];
        }
    }
    $stmt->close();
    
    if (empty($columns_with_co)) {
        echo json_encode([
            'success' => true,
            'course_code' => $course_code,
            'co_performance' => array_values($course_outcomes),
            'message' => 'No items mapped to course outcomes yet'
        ]);
        exit;
    }
    
    // Get all students in this class
    $stmt = $conn->prepare("
        SELECT DISTINCT student_id 
        FROM class_enrollments 
        WHERE class_code = ?
    ");
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row['student_id'];
    }
    $stmt->close();
    
    if (empty($students)) {
        echo json_encode([
            'success' => true,
            'course_code' => $course_code,
            'co_performance' => array_values($course_outcomes),
            'message' => 'No students enrolled in this class'
        ]);
        exit;
    }
    

// Calculate CO performance with success rates
$student_co_scores = []; // Track each student's score per CO
foreach ($columns_with_co as $column) {
    $column_id = $column['column_id'];
    $max_score = floatval($column['max_score']);
    $co_mappings = $column['co_mappings'];
    $performance_target = $column['performance_target'];
    
    // Get all grades for this column
    $stmt = $conn->prepare("
        SELECT student_id, raw_score as score 
        FROM student_flexible_grades 
        WHERE column_id = ? 
        AND class_code = ?
        AND raw_score IS NOT NULL
    ");
    
    if (!$stmt) {
        error_log("SQL Error: " . $conn->error);
        continue;
    }
    
    $stmt->bind_param("is", $column_id, $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $grades = [];
    while ($row = $result->fetch_assoc()) {
        $student_id = $row['student_id'];
        $score = floatval($row['score']);
        $percentage = ($score / $max_score) * 100;
        
        $grades[$student_id] = [
            'score' => $score,
            'percentage' => $percentage,
            'met_target' => $percentage >= $performance_target
        ];
    }
    $stmt->close();
    
    // Add to each CO this item assesses
    foreach ($co_mappings as $co_num) {
        $co_num = intval($co_num);
        if (!isset($course_outcomes[$co_num])) continue;
        
        // Set summative assessment name (first one found)
        if (empty($course_outcomes[$co_num]['summative_assessment'])) {
            $course_outcomes[$co_num]['summative_assessment'] = $column['component_name'];
            $course_outcomes[$co_num]['performance_target'] = $performance_target;
        }
        
        // Track student scores for this CO
        foreach ($grades as $student_id => $grade_data) {
            if (!isset($student_co_scores[$co_num])) {
                $student_co_scores[$co_num] = [];
            }
            
            if (!isset($student_co_scores[$co_num][$student_id])) {
                $student_co_scores[$co_num][$student_id] = [
                    'total_score' => 0,
                    'total_max' => 0
                ];
            }
            
            $student_co_scores[$co_num][$student_id]['total_score'] += $grade_data['score'];
            $student_co_scores[$co_num][$student_id]['total_max'] += $max_score;
        }
    }
}

// Calculate success rates
foreach ($course_outcomes as $co_num => &$co_data) {
    if (!isset($student_co_scores[$co_num])) {
        $co_data['success_rate_display'] = '0% (0)';
        continue;
    }
    
    $students_with_scores = $student_co_scores[$co_num];
    $total_students = count($students_with_scores);
    $students_met_target = 0;
    
    foreach ($students_with_scores as $student_id => $scores) {
        if ($scores['total_max'] > 0) {
            $student_percentage = ($scores['total_score'] / $scores['total_max']) * 100;
            if ($student_percentage >= $co_data['performance_target']) {
                $students_met_target++;
            }
        }
    }
    
    $co_data['total_students'] = $total_students;
    $co_data['students_met_target'] = $students_met_target;
    
    if ($total_students > 0) {
        $success_rate = round(($students_met_target / $total_students) * 100, 2);
        $co_data['success_rate'] = $success_rate;
        $co_data['success_rate_display'] = "{$success_rate}% ({$students_met_target})";
    } else {
        $co_data['success_rate_display'] = '0% (0)';
    }
}
    
    echo json_encode([
        'success' => true,
        'course_code' => $course_code,
        'co_performance' => array_values($course_outcomes),
        'total_students' => count($students),
        'total_items_with_co' => count($columns_with_co)
    ]);
    
} catch (Exception $e) {
    error_log("Get CO performance error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error calculating CO performance: ' . $e->getMessage()
    ]);
}

exit;
?>