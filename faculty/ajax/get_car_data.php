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
    // ========================================
    // 1. GET CLASS INFO & VERIFY OWNERSHIP
    // ========================================
    $sql = "SELECT c.class_id, c.class_code, c.course_code, c.section, c.academic_year, c.term, c.faculty_id, s.course_title, s.course_desc, s.units, u.name as instructor_name FROM class c LEFT JOIN subjects s ON c.course_code = s.course_code LEFT JOIN users u ON c.faculty_id = u.id WHERE c.class_code = ? AND c.faculty_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("si", $class_code, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found or access denied']);
        $stmt->close();
        exit;
    }
    
    $class_info = $result->fetch_assoc();
    $stmt->close();
    
// ========================================
    // 2. GET STUDENT COUNT
    // ========================================
    $sql = "SELECT COUNT(*) as student_count FROM class_enrollments WHERE class_code = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_count = $result->fetch_assoc()['student_count'] ?? 0;
    $stmt->close();
    
    // ========================================
    // 3. GET COURSE OUTCOMES
    // ========================================
    $course_code = $class_info['course_code'];
    
    $stmt = $conn->prepare("
        SELECT 
            co_id,
            co_number,
            co_description
        FROM course_outcomes
        WHERE course_code = ?
        ORDER BY co_number ASC
    ");
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $course_outcomes = [];
    while ($row = $result->fetch_assoc()) {
        $course_outcomes[] = [
            'number' => (int)$row['co_number'],
            'description' => $row['co_description']
        ];
    }
    $stmt->close();
    
    // ========================================
    // 4. GET CO-SO MAPPINGS
    // ========================================
    $stmt = $conn->prepare("
        SELECT 
            m.co_id,
            co.co_number,
            m.so_number
        FROM co_so_mapping m
        INNER JOIN course_outcomes co ON m.co_id = co.co_id
        WHERE co.course_code = ?
        ORDER BY co.co_number, m.so_number
    ");
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $co_so_mappings = [];
    while ($row = $result->fetch_assoc()) {
        $co_number = (int)$row['co_number'];
        $so_number = (int)$row['so_number'];
        
        if (!isset($co_so_mappings[$co_number])) {
            $co_so_mappings[$co_number] = [];
        }
        $co_so_mappings[$co_number][] = $so_number;
    }
    $stmt->close();
    
    // ========================================
    // 5. GET STUDENT OUTCOMES (HARD-CODED)
    // ========================================
    $student_outcomes = [
        [
            'number' => 1,
            'description' => 'Analyze a complex computing problem and apply principles of computing and other relevant disciplines to identify solutions.'
        ],
        [
            'number' => 2,
            'description' => 'Design, implement, and evaluate a computing-based solution to meet a given set of computing requirements in the context of the program\'s discipline.'
        ],
        [
            'number' => 3,
            'description' => 'Communicate effectively in a variety of professional contexts.'
        ],
        [
            'number' => 4,
            'description' => 'Recognize professional responsibilities and make informed judgments in computing practice based on legal and ethical principles.'
        ],
        [
            'number' => 5,
            'description' => 'Function effectively as a member or leader of a team engaged in activities appropriate to the program\'s discipline.'
        ],
        [
            'number' => 6,
            'description' => 'Apply computer science theory and software development fundamentals to produce computing-based solutions.'
        ]
    ];
    
    // ========================================
    // 6. GET GRADE DISTRIBUTION
    // ========================================
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
    
    $distribution = [
        '4.00' => 0, '3.50' => 0, '3.00' => 0, '2.50' => 0,
        '2.00' => 0, '1.75' => 0, '1.50' => 0, '1.25' => 0,
        '1.00' => 0, 'INC' => 0, 'DRP' => 0, 'FAILED' => 0
    ];
    
    $total_graded = 0;
    
    while ($row = $result->fetch_assoc()) {
        $grade = $row['term_grade'];
        $status = $row['grade_status'];
        $count = (int)$row['count'];
        
        if ($status === 'incomplete') {
            $distribution['INC'] += $count;
        } elseif ($status === 'DRP') {
            $distribution['DRP'] += $count;
        } elseif ($status === 'failed' || $grade < 1.0) {
            $distribution['FAILED'] += $count;
        } else {
            $grade_key = number_format($grade, 2);
            if (isset($distribution[$grade_key])) {
                $distribution[$grade_key] += $count;
            }
        }
        $total_graded += $count;
    }
    $stmt->close();
    
    // Calculate percentages
    $grade_distribution = [];
    foreach ($distribution as $grade => $count) {
        $percentage = $total_graded > 0 ? ($count / $total_graded) * 100 : 0;
        $grade_distribution[] = [
            'grade' => $grade,
            'count' => $count,
            'percentage' => round($percentage, 2)
        ];
    }
    
    // ========================================
    // 7. GET CAR DATA (from wizard)
    // ========================================
    $stmt = $conn->prepare("
        SELECT 
            car_id,
            teaching_strategies,
            intervention_activities,
            problems_encountered,
            actions_taken,
            proposed_actions,
            status
        FROM car_data
        WHERE class_id = (SELECT class_id FROM class WHERE class_code = ? LIMIT 1)
    ");
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $car_data = null;
    if ($result->num_rows > 0) {
        $car_data = $result->fetch_assoc();
        
        // Get recommendations
        $car_id = $car_data['car_id'];
        $stmt2 = $conn->prepare("
            SELECT co_number, recommendation
            FROM car_recommendations
            WHERE car_id = ?
            ORDER BY co_number
        ");
        $stmt2->bind_param("i", $car_id);
        $stmt2->execute();
        $rec_result = $stmt2->get_result();
        
        $recommendations = [];
        while ($rec = $rec_result->fetch_assoc()) {
            $recommendations[] = [
                'co_number' => (int)$rec['co_number'],
                'recommendation' => $rec['recommendation']
            ];
        }
        $stmt2->close();
        
        $car_data['recommendations'] = $recommendations;
    }
    $stmt->close();
    
    // ========================================
    // 8. BUILD RESPONSE
    // ========================================
    $response = [
        'success' => true,
        'header' => [
            'course_code' => $class_info['course_code'],
            'course_title' => $class_info['course_title'],
            'school_year' => $class_info['academic_year'],
            'term' => $class_info['term'],
            'section' => $class_info['section'],
            'student_count' => (int)$student_count,
            'instructor' => $class_info['instructor_name'],
            'units' => $class_info['units']
        ],
        'course_description' => $class_info['course_desc'] ?? '',
        'course_outcomes' => $course_outcomes,
        'co_so_mappings' => $co_so_mappings,
        'student_outcomes' => $student_outcomes,
        'grade_distribution' => $grade_distribution,
        'car_data' => $car_data,
        'co_assessment' => null // Will be added later when prof confirms
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Get CAR data error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error getting CAR data: ' . $e->getMessage()
    ]);
}

exit;
?>