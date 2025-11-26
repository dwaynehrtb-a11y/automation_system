<?php
/**
 * Student Management API
 * Handles student enrollment and removal operations
 * 
 * @author Faculty Dashboard System
 * @version 1.0
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error handling - Show errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../../logs/php_errors.log');

// Database connection
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../config/encryption.php';
require_once '../../includes/StudentModel.php';

// Security check
header('Content-Type: application/json');

// Check if logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// CSRF Token validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$faculty_id = (string)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'bulk_enroll':
            $class_code = trim($_POST['class_code'] ?? '');
            $student_ids_json = $_POST['student_ids'] ?? '';
            if (empty($class_code) || empty($student_ids_json)) {
                throw new Exception('Class code and student IDs required');
            }
            $student_ids = json_decode($student_ids_json, true);
            if (!is_array($student_ids) || count($student_ids) === 0) {
                throw new Exception('No students selected');
            }
            // Debug logging
            error_log("Bulk enroll: class_code=$class_code, faculty_id=$faculty_id, students=" . count($student_ids));
            $success_count = 0;
            $errors = [];
            foreach ($student_ids as $sid) {
                try {
                    $result = enrollStudent($conn, $sid, $class_code, $faculty_id);
                    if (!empty($result['success'])) {
                        $success_count++;
                    } else {
                        $errors[] = $result['message'] ?? 'Unknown error';
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
            $result = [
                'success' => $success_count > 0,
                'enrolled' => $success_count,
                'errors' => $errors,
                'debug' => "class_code: $class_code, faculty_id: $faculty_id",
                'message' => $success_count > 0 ? ("$success_count students enrolled.") : ("No students enrolled. Debug: $class_code|$faculty_id | " . implode('; ', $errors)),
            ];
            break;
        case 'get_class_students':
            $class_code = $_POST['class_code'] ?? '';
            if (empty($class_code)) {
                throw new Exception('Class code required');
            }
            $result = getClassStudents($conn, $class_code, $faculty_id);
            break;
            
        case 'get_all_students':
            $class_code = $_POST['class_code'] ?? '';
            if (empty($class_code)) {
                throw new Exception('Class code required');
            }
            $result = getAllStudentsForEnrollment($conn, $class_code);
            break;
            
        case 'search_students':
            $search_term = $_POST['search_term'] ?? '';
            $class_code = $_POST['class_code'] ?? '';
            
            if (empty($search_term) || empty($class_code)) {
                throw new Exception('Search term and class code required');
            }
            
            $result = searchStudents($conn, $search_term, $class_code);
            break;
            
        case 'enroll_student':
            $student_id = $_POST['student_id'] ?? '';
            $class_code = $_POST['class_code'] ?? '';
            
            if (empty($student_id) || empty($class_code)) {
                throw new Exception('Student ID and class code required');
            }
            
            $result = enrollStudent($conn, $student_id, $class_code, $faculty_id);
            break;
            
        case 'remove_student':
            $student_id = $_POST['student_id'] ?? '';
            $class_code = $_POST['class_code'] ?? '';
            
            if (empty($student_id) || empty($class_code)) {
                throw new Exception('Student ID and class code required');
            }
            
            $result = removeStudent($conn, $student_id, $class_code, $faculty_id);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Student Management Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

/**
 * Get all students enrolled in a class (with decryption)
 */
function getClassStudents($conn, $class_code, $faculty_id) {
    global $_SESSION;
    
    // Verify faculty owns this class
    $verify = $conn->prepare("SELECT class_id FROM class WHERE class_code = ? AND faculty_id = ? LIMIT 1");
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("ss", $class_code, $faculty_id);
    $verify->execute();
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Class not found or access denied'];
    }
    $verify->close();
    
    // Get enrolled students with their IDs
    $query = "
        SELECT ce.student_id, ce.status, ce.enrollment_date
        FROM class_enrollments ce
        WHERE ce.class_code = ?
        ORDER BY ce.student_id
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $studentIds = [];
    $enrollmentData = [];
    
    while ($row = $result->fetch_assoc()) {
        $studentIds[] = $row['student_id'];
        $enrollmentData[$row['student_id']] = [
            'status' => $row['status'],
            'enrollment_date' => $row['enrollment_date']
        ];
    }
    $stmt->close();
    
    // Check grade_term table for dropped/withdrawn students
    if (!empty($studentIds)) {
        $placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
        $gradeQuery = "
            SELECT student_id, grade_status 
            FROM grade_term 
            WHERE class_code = ? AND student_id IN ($placeholders) AND grade_status IN ('dropped', 'withdrawn')
        ";
        
        $stmt = $conn->prepare($gradeQuery);
        if ($stmt) {
            $params = array_merge([$class_code], $studentIds);
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            $stmt->execute();
            $gradeResult = $stmt->get_result();
            
            while ($gradeRow = $gradeResult->fetch_assoc()) {
                // Override status if student is dropped/withdrawn in grade_term
                $enrollmentData[$gradeRow['student_id']]['status'] = $gradeRow['grade_status'];
            }
            $stmt->close();
        }
    }
    
    // Initialize encryption
    Encryption::init();
    
    // Helper function to check if data is encrypted
    $is_encrypted = function($data) {
        if (empty($data)) return false;
        // If it looks like plaintext (contains spaces, common letters, etc.), it's probably not encrypted
        // Encrypted data is base64 which only contains alphanumeric + /+= chars and has no spaces
        if (strpos($data, ' ') !== false) return false; // Plaintext usually has spaces
        if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $data)) return false; // Must be valid base64 chars only
        if ((strlen($data) % 4) != 0) return false; // Base64 must be divisible by 4
        // Additional check: encrypted data is usually longer (due to IV and ciphertext)
        if (strlen($data) < 24) return false; // Encrypted data is at least 24 chars (IV + tag + minimal ciphertext)
        $decoded = @base64_decode($data, true);
        return $decoded !== false;
    };
    
    // Decrypt student data
    $students = [];
    
    foreach ($studentIds as $studentId) {
        try {
            // Get encrypted data from database
            $stmt = $conn->prepare("
                SELECT student_id, first_name, last_name, middle_initial, email, birthday, status
                FROM student 
                WHERE student_id = ?
            ");
            $stmt->bind_param('s', $studentId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($row) {
                // Check if fields are encrypted and decrypt them
                if ($is_encrypted($row['first_name'])) {
                    try {
                        $row['first_name'] = Encryption::decrypt($row['first_name']);
                    } catch (Exception $e) {
                        error_log("Failed to decrypt first_name for $studentId: " . $e->getMessage());
                        $row['first_name'] = 'N/A';
                    }
                }
                
                if ($is_encrypted($row['last_name'])) {
                    try {
                        $row['last_name'] = Encryption::decrypt($row['last_name']);
                    } catch (Exception $e) {
                        error_log("Failed to decrypt last_name for $studentId: " . $e->getMessage());
                        $row['last_name'] = 'N/A';
                    }
                }
                
                if ($is_encrypted($row['email'])) {
                    try {
                        $row['email'] = Encryption::decrypt($row['email']);
                    } catch (Exception $e) {
                        error_log("Failed to decrypt email for $studentId: " . $e->getMessage());
                        $row['email'] = 'N/A';
                    }
                }
                
                if ($is_encrypted($row['birthday'])) {
                    try {
                        $row['birthday'] = Encryption::decrypt($row['birthday']);
                    } catch (Exception $e) {
                        $row['birthday'] = 'N/A';
                    }
                }
                
                $row['status'] = $enrollmentData[$studentId]['status'];
                $row['enrollment_date'] = $enrollmentData[$studentId]['enrollment_date'];
                $row['full_name'] = trim($row['last_name'] . ', ' . $row['first_name'] . 
                    (isset($row['middle_initial']) && $row['middle_initial'] ? ' ' . $row['middle_initial'] : ''));
                $students[] = $row;
            }
        } catch (Exception $e) {
            // If something goes wrong, still show student ID
            error_log("Failed to process student $studentId: " . $e->getMessage());
            $students[] = [
                'student_id' => $studentId,
                'first_name' => 'N/A',
                'last_name' => 'N/A',
                'email' => 'N/A',
                'full_name' => $studentId,
                'status' => $enrollmentData[$studentId]['status'] ?? 'enrolled',
                'enrollment_date' => $enrollmentData[$studentId]['enrollment_date'] ?? null
            ];
        }
    }
    
    return [
        'success' => true,
        'students' => $students,
        'count' => count($students)
    ];
}

/**
 * Search students not enrolled in the class
 * IMPORTANT: Due to encryption, search is by student_id only
 * This ensures only authorized access (can't search by name = can't find students you shouldn't know)
 */
function searchStudents($conn, $search_term, $class_code) {
    // Search can be by student_id OR by name after decryption
    $search = "%{$search_term}%";
    
    // First, try to get students by student_id (fast, unencrypted)
    $query = "
        SELECT 
            s.student_id,
            s.first_name,
            s.last_name,
            s.middle_initial,
            s.email,
            CONCAT(s.last_name, ', ', s.first_name, 
            CASE WHEN s.middle_initial IS NOT NULL THEN CONCAT(' ', s.middle_initial, '.') ELSE '' END) as full_name,
            s.status
        FROM student s
        WHERE (s.student_id LIKE ?)
        AND s.student_id NOT IN (
            SELECT student_id 
            FROM class_enrollments 
            WHERE class_code = ? AND status = 'enrolled'
        )
        AND s.status IN ('active','pending')
        ORDER BY s.student_id
        LIMIT 50
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ss", $search, $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Initialize encryption for decryption
    Encryption::init();
    
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
    $search_lower = strtolower($search_term);
    
    while ($row = $result->fetch_assoc()) {
        // Decrypt encrypted fields if needed
        try {
            if (!empty($row['first_name']) && $is_encrypted($row['first_name'])) {
                try {
                    $row['first_name'] = Encryption::decrypt($row['first_name']);
                } catch (Exception $e) {
                    error_log("Failed to decrypt first_name in searchStudents: " . $e->getMessage());
                }
            }
            if (!empty($row['last_name']) && $is_encrypted($row['last_name'])) {
                try {
                    $row['last_name'] = Encryption::decrypt($row['last_name']);
                } catch (Exception $e) {
                    error_log("Failed to decrypt last_name in searchStudents: " . $e->getMessage());
                }
            }
            if (!empty($row['email']) && $is_encrypted($row['email'])) {
                try {
                    $row['email'] = Encryption::decrypt($row['email']);
                } catch (Exception $e) {
                    error_log("Failed to decrypt email in searchStudents: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log("Decryption error in searchStudents: " . $e->getMessage());
        }
        
        // Reconstruct full_name with decrypted values
        $row['full_name'] = $row['last_name'] . ', ' . $row['first_name'];
        if (!empty($row['middle_initial'])) {
            $row['full_name'] .= ' ' . $row['middle_initial'] . '.';
        }
        
        $students[] = $row;
    }
    $stmt->close();
    
    // If no results by student_id, try fetching all active non-enrolled students and filter by name
    if (empty($students) && strlen($search_term) > 1) {
        $query2 = "
            SELECT 
                s.student_id,
                s.first_name,
                s.last_name,
                s.middle_initial,
                s.email,
                CONCAT(s.last_name, ', ', s.first_name, 
                CASE WHEN s.middle_initial IS NOT NULL THEN CONCAT(' ', s.middle_initial, '.') ELSE '' END) as full_name,
                s.status
            FROM student s
            WHERE s.student_id NOT IN (
                SELECT student_id 
                FROM class_enrollments 
                WHERE class_code = ? AND status = 'enrolled'
            )
            AND s.status IN ('active','pending')
            ORDER BY s.last_name, s.first_name
            LIMIT 100
        ";
        
        $stmt2 = $conn->prepare($query2);
        if (!$stmt2) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt2->bind_param("s", $class_code);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        while ($row = $result2->fetch_assoc()) {
            // Decrypt encrypted fields if needed
            try {
                if (!empty($row['first_name']) && $is_encrypted($row['first_name'])) {
                    try {
                        $row['first_name'] = Encryption::decrypt($row['first_name']);
                    } catch (Exception $e) {
                        error_log("Failed to decrypt first_name in searchStudents fallback: " . $e->getMessage());
                    }
                }
                if (!empty($row['last_name']) && $is_encrypted($row['last_name'])) {
                    try {
                        $row['last_name'] = Encryption::decrypt($row['last_name']);
                    } catch (Exception $e) {
                        error_log("Failed to decrypt last_name in searchStudents fallback: " . $e->getMessage());
                    }
                }
                if (!empty($row['email']) && $is_encrypted($row['email'])) {
                    try {
                        $row['email'] = Encryption::decrypt($row['email']);
                    } catch (Exception $e) {
                        error_log("Failed to decrypt email in searchStudents fallback: " . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                error_log("Decryption error in searchStudents fallback: " . $e->getMessage());
            }
            
            // Reconstruct full_name
            $row['full_name'] = $row['last_name'] . ', ' . $row['first_name'];
            if (!empty($row['middle_initial'])) {
                $row['full_name'] .= ' ' . $row['middle_initial'] . '.';
            }
            
            // Filter by name match (case-insensitive)
            $full_name_lower = strtolower($row['full_name']);
            $first_name_lower = strtolower($row['first_name']);
            $last_name_lower = strtolower($row['last_name']);
            
            if (strpos($full_name_lower, $search_lower) !== false || 
                strpos($first_name_lower, $search_lower) !== false || 
                strpos($last_name_lower, $search_lower) !== false) {
                $students[] = $row;
                if (count($students) >= 20) break; // Limit to 20 results
            }
        }
        $stmt2->close();
    }
    
    if (empty($students)) {
        return [
            'success' => true,
            'students' => [],
            'message' => 'No students found. Try searching by student ID or full name.'
        ];
    }
    
    return [
        'success' => true,
        'students' => $students
    ];
}

/**
 * Enroll a student to a class
 */
function enrollStudent($conn, $student_id, $class_code, $faculty_id) {
    // Verify faculty owns this class and get class details
    $verify = $conn->prepare("
        SELECT section, course_code, academic_year, term 
        FROM class 
        WHERE class_code = ? AND faculty_id = ? 
        LIMIT 1
    ");
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("ss", $class_code, $faculty_id);
    $verify->execute();
    $result = $verify->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Class not found or access denied'];
    }
    
    $class_data = $result->fetch_assoc();
    $verify->close();
    
    // Check if student exists
    $check_student = $conn->prepare("SELECT student_id FROM student WHERE student_id = ?");
    if (!$check_student) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $check_student->bind_param("s", $student_id);
    $check_student->execute();
    if ($check_student->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Student not found'];
    }
    $check_student->close();
    
    // Check if already enrolled
    $check_enrollment = $conn->prepare("
        SELECT enrollment_id 
        FROM class_enrollments 
        WHERE student_id = ? AND class_code = ?
    ");
    if (!$check_enrollment) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $check_enrollment->bind_param("ss", $student_id, $class_code);
    $check_enrollment->execute();
    $enrollment_result = $check_enrollment->get_result();
    
    if ($enrollment_result->num_rows > 0) {
        $check_enrollment->close();
        return ['success' => false, 'message' => 'Student already enrolled in this class'];
    }
    $check_enrollment->close();
    
    // Enroll student
    $stmt = $conn->prepare("
        INSERT INTO class_enrollments 
        (student_id, class_code, section, course_code, academic_year, term, faculty_id, enrollment_date, enrolled_by, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'enrolled')
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("sssssiii", 
        $student_id, 
        $class_code, 
        $class_data['section'],
        $class_data['course_code'],
        $class_data['academic_year'],
        $class_data['term'],
        $faculty_id,
        $faculty_id
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        
        //  AUTO-UPDATE STUDENT STATUS TO 'ACTIVE' WHEN ENROLLED
        $update_status = $conn->prepare("
            UPDATE student 
            SET status = 'active' 
            WHERE student_id = ? AND status = 'pending'
        ");
        
        if ($update_status) {
            $update_status->bind_param("s", $student_id);
            $update_status->execute();
            $update_status->close();
        }
        
        return ['success' => true, 'message' => 'Student enrolled successfully'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Enrollment failed: ' . $error);
    }
}

/**
 * Remove a student from a class
 */
function removeStudent($conn, $student_id, $class_code, $faculty_id) {
    // Verify faculty owns this class
    $verify = $conn->prepare("SELECT class_id FROM class WHERE class_code = ? AND faculty_id = ? LIMIT 1");
    if (!$verify) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $verify->bind_param("ss", $class_code, $faculty_id);
    $verify->execute();
    if ($verify->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Class not found or access denied'];
    }
    $verify->close();
    
    // Remove enrollment
    $stmt = $conn->prepare("
        DELETE FROM class_enrollments 
        WHERE student_id = ? AND class_code = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ss", $student_id, $class_code);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        if ($affected > 0) {
            return ['success' => true, 'message' => 'Student removed successfully'];
        } else {
            return ['success' => false, 'message' => 'Student not found in this class'];
        }
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Remove failed: ' . $error);
    }
}

/**
 * Get all students available for enrollment (not already enrolled in this class)
 */
function getAllStudentsForEnrollment($conn, $class_code) {
    // Get all active students and mark whether they're already enrolled in this class
    // We previously excluded already-enrolled students here; change to return them as well and include a flag
    $query = "
        SELECT 
            s.student_id,
            s.first_name,
            s.last_name,
            s.middle_initial,
            s.email,
            CONCAT(s.last_name, ', ', s.first_name, 
            CASE WHEN s.middle_initial IS NOT NULL THEN CONCAT(' ', s.middle_initial, '.') ELSE '' END) as full_name,
            s.status,
            CASE WHEN ce.student_id IS NOT NULL THEN 1 ELSE 0 END as already_enrolled
        FROM student s
        LEFT JOIN class_enrollments ce ON ce.student_id = s.student_id AND ce.class_code = ? AND ce.status = 'enrolled'
        WHERE s.status IN ('active', 'pending')
        GROUP BY s.student_id
        ORDER BY s.last_name, s.first_name
        LIMIT 500
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Initialize encryption for decryption
    Encryption::init();
    
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
                    error_log("Failed to decrypt first_name in getAllStudentsForEnrollment: " . $e->getMessage());
                }
            }
            if (!empty($row['last_name']) && $is_encrypted($row['last_name'])) {
                try {
                    $row['last_name'] = Encryption::decrypt($row['last_name']);
                } catch (Exception $e) {
                    error_log("Failed to decrypt last_name in getAllStudentsForEnrollment: " . $e->getMessage());
                }
            }
            if (!empty($row['email']) && $is_encrypted($row['email'])) {
                try {
                    $row['email'] = Encryption::decrypt($row['email']);
                } catch (Exception $e) {
                    error_log("Failed to decrypt email in getAllStudentsForEnrollment: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log("Decryption error in getAllStudentsForEnrollment: " . $e->getMessage());
        }
        
        // Reconstruct full_name with decrypted values
        $row['full_name'] = $row['last_name'] . ', ' . $row['first_name'];
        if (!empty($row['middle_initial'])) {
            $row['full_name'] .= ' ' . $row['middle_initial'] . '.';
        }
        
        $students[] = $row;
    }
    $stmt->close();
    
    return ['success' => true, 'students' => $students];
}

$conn->close();
?>