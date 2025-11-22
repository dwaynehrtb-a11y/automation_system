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
require_once '../../config/encryption.php';

// Security check
header('Content-Type: application/json');

// Initialize encryption
Encryption::init();

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
        // STUDENT MANAGEMENT
        // ============================================
        case 'get_students':
            $class_code = $_POST['class_code'] ?? '';
            if (empty($class_code)) {
                throw new Exception('Class code required');
            }
            $result = getStudents($conn, $class_code, $faculty_id);
            break;
            
        // ============================================
        // CLASS OPERATIONS
        // ============================================
        case 'get_classes':
            $academic_year = $_POST['academic_year'] ?? '';
            $term = $_POST['term'] ?? '';
            
            if (empty($academic_year) || empty($term)) {
                throw new Exception('Academic year and term required');
            }
            
            $result = getClasses($conn, $faculty_id, $academic_year, $term);
            break;
            
        case 'get_class_details':
            $class_code = $_POST['class_code'] ?? '';
            if (empty($class_code)) {
                throw new Exception('Class code required');
            }
            $result = getClassDetails($conn, $class_code, $faculty_id);
            break;
            
        // ============================================
        // GRADE OPERATIONS
        // ============================================
        case 'get_grades':
            $component_id = $_POST['component_id'] ?? '';
            if (empty($component_id)) {
                throw new Exception('Component ID required');
            }
            $result = getGrades($conn, $component_id, $faculty_id);
            break;
            
        case 'save_grade':
            $student_id = $_POST['student_id'] ?? '';
            $column_id = $_POST['column_id'] ?? '';
            $score = $_POST['score'] ?? null;
            $percentage = $_POST['percentage'] ?? null;
            
            if (empty($student_id) || empty($column_id)) {
                throw new Exception('Student ID and Column ID required');
            }
            
            $result = saveGrade($conn, $student_id, $column_id, $score, $percentage, $faculty_id);
            break;
            
        case 'initialize_grades':
            $class_code = $_POST['class_code'] ?? '';
            if (empty($class_code)) {
                throw new Exception('Class code required');
            }
            $result = initializeGrades($conn, $class_code, $faculty_id);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Process Grades Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

// ============================================
// STUDENT FUNCTIONS
// ============================================

/**
 * Get students enrolled in a class
 */
function getStudents($conn, $class_code, $faculty_id) {
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
    
    // Get enrolled students with encrypted fields
    $query = "
        SELECT 
            s.student_id,
            s.first_name,
            s.last_name,
            s.middle_initial,
            s.email,
            ce.enrollment_date,
            ce.status
        FROM class_enrollments ce
        INNER JOIN student s ON ce.student_id = s.student_id
        WHERE ce.class_code = ? 
          AND ce.status = 'enrolled'
        ORDER BY s.student_id
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Helper function to check if data is encrypted
    $is_encrypted = function($data) {
        if (empty($data)) return false;
        if (strpos($data, ' ') !== false) return false; // Plaintext usually has spaces
        if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $data)) return false;
        if ((strlen($data) % 4) != 0) return false;
        if (strlen($data) < 24) return false; // Encrypted data is always longer than 24 chars
        $decoded = @base64_decode($data, true);
        return $decoded !== false;
    };
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        // Decrypt encrypted fields if needed
        try {
            if (!empty($row['first_name']) && $is_encrypted($row['first_name'])) {
                try {
                    $row['first_name'] = Encryption::decrypt($row['first_name']);
                } catch (Exception $e) {
                    error_log("Failed to decrypt first_name for {$row['student_id']}: " . $e->getMessage());
                }
            }
            
            if (!empty($row['last_name']) && $is_encrypted($row['last_name'])) {
                try {
                    $row['last_name'] = Encryption::decrypt($row['last_name']);
                } catch (Exception $e) {
                    error_log("Failed to decrypt last_name for {$row['student_id']}: " . $e->getMessage());
                }
            }
            
            if (!empty($row['email']) && $is_encrypted($row['email'])) {
                try {
                    $row['email'] = Encryption::decrypt($row['email']);
                } catch (Exception $e) {
                    error_log("Failed to decrypt email for {$row['student_id']}: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log("Decryption error in getStudents: " . $e->getMessage());
        }
        
        $row['name'] = trim(($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? '') . 
            (isset($row['middle_initial']) && $row['middle_initial'] ? ' ' . $row['middle_initial'] . '.' : ''));
        
        // Use student ID if names are empty
        if (empty(trim($row['name']))) {
            $row['name'] = 'Student ' . $row['student_id'];
        }
        
        $students[] = $row;
    }
    $stmt->close();
    
    return [
        'success' => true,
        'students' => $students,
        'count' => count($students)
    ];
}

// ============================================
// GRADE FUNCTIONS
// ============================================

/**
 * Get grades for a specific component
 */
function getGrades($conn, $component_id, $faculty_id) {
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
    
    // Get all grades for this component's columns
    $query = "
        SELECT 
            g.id as grade_id,
            g.student_id,
            g.column_id,
            g.raw_score as score,
            g.updated_at
        FROM student_flexible_grades g
        INNER JOIN grading_component_columns gcc ON g.column_id = gcc.id
        WHERE gcc.component_id = ?
        ORDER BY g.student_id, gcc.order_index
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $component_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $grades = [];
    while ($row = $result->fetch_assoc()) {
        // Create key as: studentId_columnId
        $key = $row['student_id'] . '_' . $row['column_id'];
        $grades[$key] = [
            'score' => $row['score']
        ];
    }
$stmt->close();

return [
    'success' => true,
    'grades' => $grades,
    'count' => count($grades)
];
}

/**
 * Save or update a grade
 */
function saveGrade($conn, $student_id, $column_id, $score, $percentage, $faculty_id) {
    // Verify faculty owns this column's component's class
    $verify = $conn->prepare("
        SELECT gcc.id, gc.class_code
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
    $verify_result = $verify->get_result();
    
    if ($verify_result->num_rows === 0) {
        return ['success' => false, 'message' => 'Column not found or access denied'];
    }
    
    $verify_data = $verify_result->fetch_assoc();
    $class_code = $verify_data['class_code'];
    $verify->close();
    
    // Get component_id for this column
    $comp_query = "SELECT component_id FROM grading_component_columns WHERE id = ?";
    $comp_stmt = $conn->prepare($comp_query);
    $comp_stmt->bind_param("i", $column_id);
    $comp_stmt->execute();
    $comp_result = $comp_stmt->get_result();
    $component_id = $comp_result->fetch_assoc()['component_id'];
    $comp_stmt->close();
    
    // Check if grade exists
    $check = $conn->prepare("
        SELECT id 
        FROM student_flexible_grades 
        WHERE student_id = ? AND column_id = ?
        LIMIT 1
    ");
    
    if (!$check) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $check->bind_param("si", $student_id, $column_id);
    $check->execute();
    $existing = $check->get_result();
    $check->close();
    
    // Handle empty score
    $scoreValue = ($score === '' || $score === null) ? null : floatval($score);
    $percentageValue = ($percentage === '' || $percentage === null) ? null : floatval($percentage);
    
    if ($existing->num_rows > 0) {
        // Update existing grade
        $stmt = $conn->prepare("
            UPDATE student_flexible_grades 
            SET raw_score = ?, grade_value = ?, updated_at = NOW()
            WHERE student_id = ? AND column_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("sssi", $scoreValue, $percentageValue, $student_id, $column_id);
    } else {
        // Insert new grade
        $stmt = $conn->prepare("
            INSERT INTO student_flexible_grades 
            (student_id, class_code, component_id, column_id, raw_score, grade_value, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("ssiiss", $student_id, $class_code, $component_id, $column_id, $scoreValue, $percentageValue);
    }
    
    if ($stmt->execute()) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Grade saved successfully'
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Failed to save grade: ' . $error);
    }
}

// ============================================
// CLASS FUNCTIONS
// ============================================

/**
 * Get classes for faculty by academic year and term
 */
function getClasses($conn, $faculty_id, $academic_year, $term) {
    $query = "
        SELECT DISTINCT
            c.class_code,
            c.section,
            c.course_code,
            s.course_title,
            s.units,
            c.academic_year,
            c.term,
            COUNT(DISTINCT ce.student_id) as student_count
        FROM class c
        LEFT JOIN subjects s ON c.course_code = s.course_code
        LEFT JOIN class_enrollments ce ON c.class_code = ce.class_code AND ce.status = 'enrolled'
        WHERE c.faculty_id = ? 
          AND c.academic_year = ? 
          AND c.term = ?
        GROUP BY c.class_code, c.section, c.course_code, s.course_title, s.units, c.academic_year, c.term
        ORDER BY c.course_code, c.section
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    
    $stmt->bind_param("iss", $faculty_id, $academic_year, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt->close();
    
    return [
        'success' => true,
        'classes' => $classes,
        'count' => count($classes)
    ];
}

/**
 * Get class details
 */
function getClassDetails($conn, $class_code, $faculty_id) {
    // Verify faculty owns this class
    $verify = $conn->prepare("
        SELECT c.*, s.course_title 
        FROM class c
        LEFT JOIN subjects s ON c.course_code = s.course_code
        WHERE c.class_code = ? AND c.faculty_id = ? 
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("si", $class_code, $faculty_id);
    $verify->execute();
    $result = $verify->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Class not found or access denied'];
    }
    
    $class_data = $result->fetch_assoc();
    $verify->close();
    
    return [
        'success' => true,
        'class' => $class_data
    ];
}

/**
 * Initialize grade records for students
 */
function initializeGrades($conn, $class_code, $faculty_id) {
    // Verify faculty owns this class
    $verify = $conn->prepare("
        SELECT academic_year, term 
        FROM class 
        WHERE class_code = ? AND faculty_id = ? 
        LIMIT 1
    ");
    
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("si", $class_code, $faculty_id);
    $verify->execute();
    $result = $verify->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Class not found or access denied'];
    }
    
    $class_data = $result->fetch_assoc();
    $verify->close();
    
    // Insert grade records for enrolled students who don't have grades yet
    $query = "
        INSERT INTO student_grades (student_id, class_code, academic_year, term, created_at)
        SELECT DISTINCT 
            ce.student_id, 
            ce.class_code, 
            ?, 
            ?, 
            NOW()
        FROM class_enrollments ce
        WHERE ce.class_code = ? 
          AND ce.status = 'enrolled'
          AND NOT EXISTS (
              SELECT 1 FROM student_grades sg 
              WHERE sg.student_id = ce.student_id 
                AND sg.class_code = ce.class_code
          )
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("sss", 
        $class_data['academic_year'], 
        $class_data['term'], 
        $class_code
    );
    
    if ($stmt->execute()) {
        $records_created = $stmt->affected_rows;
        $stmt->close();
        
        return [
            'success' => true,
            'message' => "Initialized {$records_created} grade records",
            'records_created' => $records_created
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Failed to initialize grades: ' . $error);
    }
}

$conn->close();
?>