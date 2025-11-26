<?php
// Prevent HTML output - SET FIRST
ob_start();

// Start session
session_start();

// Set strict error handling before includes
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Never display errors
ini_set('log_errors', 1);

// Set JSON header FIRST before any logic
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
header('Pragma: no-cache');
header('Expires: 0');

// Now include dependencies
require_once '../../config/db.php';
require_once '../../includes/GradesModel.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    ob_end_flush();
    exit;
}

$faculty_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'update_grade_status') {
    updateGradeStatusOnly($conn, $faculty_id);
    ob_end_flush();
    exit;
}
if ($action === 'get_grade_statuses') {
    getGradeStatuses($conn, $faculty_id);
    ob_end_flush();
    exit;
}
if ($action === 'get_lacking_requirements') {
    getLackingRequirements($conn, $faculty_id);
    ob_end_flush();
    exit;
}
if ($action === 'update_lacking_requirements') {
    updateLackingRequirements($conn, $faculty_id);
    ob_end_flush();
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    ob_end_flush();
    exit;
}

$class_code = $input['class_code'] ?? '';
$grades = $input['grades'] ?? [];

if (empty($class_code)) {
    echo json_encode(['success' => false, 'message' => 'Class code required']);
    ob_end_flush();
    exit;
}

if (empty($grades) || !is_array($grades)) {
    echo json_encode(['success' => false, 'message' => 'Grades data required']);
    ob_end_flush();
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
        ob_end_flush();
        exit;
    }
    $stmt->close();
    
    // Use GradesModel for automatic encryption on save
    $gradesModel = new GradesModel($conn);
    
    $saved_count = 0;
    $failed_count = 0;
    $errors = [];
    
    foreach ($grades as $grade) {
        $student_id = $grade['student_id'] ?? '';
        
        if (empty($student_id)) {
            $failed_count++;
            continue;
        }
        
        try {
            // Prepare grade data
            $gradeData = [
                'student_id' => $student_id,
                'class_code' => $class_code,
                'midterm_percentage' => $grade['midterm_percentage'] ?? null,
                'finals_percentage' => $grade['finals_percentage'] ?? null,
                'term_percentage' => $grade['term_percentage'] ?? null,
                'term_grade' => $grade['term_grade'] ?? null,
                'lacking_requirements' => $grade['lacking_requirements'] ?? ''
            ];
            
            // Add grade_status if provided
            if (isset($grade['grade_status'])) {
                $gradeData['grade_status'] = $grade['grade_status'];
            }
            
            // Save using model - automatically encrypts
            $result = $gradesModel->saveGrades($gradeData, $faculty_id);
            
            if ($result) {
                $saved_count++;
            } else {
                $failed_count++;
                $errors[] = "Student $student_id: Failed to save";
            }
        } catch (Exception $e) {
            $failed_count++;
            $errors[] = "Student $student_id: " . $e->getMessage();
            error_log("Failed to save grade for student $student_id: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully saved $saved_count grades" . ($failed_count > 0 ? " ($failed_count failed)" : ""),
        'saved_count' => $saved_count,
        'failed_count' => $failed_count,
        'errors' => $errors
    ]);
    ob_end_flush();
    
} catch (Exception $e) {
    error_log("Save term grades error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error saving grades: ' . $e->getMessage()
    ]);
    ob_end_flush();
}

exit;
/**
 * Update only the grade status for a student
 */
function updateGradeStatusOnly($conn, $faculty_id) {
    $class_code = $_POST['class_code'] ?? '';
    $student_id = $_POST['student_id'] ?? '';
    $grade_status = $_POST['grade_status'] ?? '';
    
    if (empty($class_code) || empty($student_id) || empty($grade_status)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Validate grade_status is one of the allowed values
    $allowed_statuses = ['passed', 'failed', 'incomplete', 'dropped'];
    if (!in_array($grade_status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid grade status']);
        return;
    }
    
    // Verify faculty owns this class
    $stmt = $conn->prepare("SELECT class_id FROM class WHERE class_code = ? AND faculty_id = ?");
    $stmt->bind_param("si", $class_code, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found or access denied']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Get lacking requirements
    $lacking_reqs = $_POST['lacking_requirements'] ?? '';
    
    // Only set status_manually_set='yes' if status is passed/failed/dropped, NOT for incomplete
    // Incomplete should allow automatic recalculation when grades are entered
    $manually_set = ($grade_status === 'incomplete') ? 'no' : 'yes';

    // Use INSERT ON DUPLICATE KEY UPDATE to ensure record is created or updated
    $stmt = $conn->prepare("
        INSERT INTO grade_term (student_id, class_code, grade_status, lacking_requirements, status_manually_set, computed_at)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
            grade_status = VALUES(grade_status),
            lacking_requirements = VALUES(lacking_requirements),
            status_manually_set = VALUES(status_manually_set),
            computed_at = CURRENT_TIMESTAMP
    ");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param("sssss", $student_id, $class_code, $grade_status, $lacking_reqs, $manually_set);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'student_id' => $student_id,
            'class_code' => $class_code,
            'grade_status' => $grade_status
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update status: ' . $stmt->error
        ]);
    }
    $stmt->close();
}
/**
 * Get all grade statuses for a class
 */
function getGradeStatuses($conn, $faculty_id) {
    try {
        $class_code = $_GET['class_code'] ?? $_POST['class_code'] ?? '';
        
        if (empty($class_code)) {
            echo json_encode(['success' => false, 'message' => 'Class code required']);
            return;
        }
        
        // Verify faculty owns this class
        $stmt = $conn->prepare("SELECT class_id FROM class WHERE class_code = ? AND faculty_id = ?");
        $stmt->bind_param("si", $class_code, $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Class not found or access denied']);
            $stmt->close();
            return;
        }
        $stmt->close();
        
        // Get all grade statuses AND stored term percentages (including is_encrypted flag)
        $stmt = $conn->prepare("
            SELECT student_id, grade_status, midterm_percentage, finals_percentage, term_percentage, term_grade, is_encrypted
            FROM grade_term
            WHERE class_code = ?
        ");
        $stmt->bind_param("s", $class_code);
        $stmt->execute();
        $result = $stmt->get_result();

        // Use GradesModel for decryption - faculty should always see decrypted grades
        $gradesModel = new GradesModel($conn);

        $statuses = [];
        $termGrades = [];  // Store computed term grades from database
        while ($row = $result->fetch_assoc()) {
            $statuses[$row['student_id']] = $row['grade_status'];

            // Always decrypt for faculty viewing their own class
            try {
                $decryptedRow = $gradesModel->decryptFieldsPublic($row);
                if ($decryptedRow) {
                    $row = $decryptedRow;
                    error_log("Successfully decrypted grades for student {$row['student_id']}");
                } else {
                    error_log("decryptFieldsPublic returned null for student {$row['student_id']}, using original values");
                    // Continue with original values if decryption returns null
                }
            } catch (Exception $e) {
                error_log('Error decrypting grades for student ' . $row['student_id'] . ': ' . $e->getMessage());
                // Continue with original values if decryption fails
            }

            // Include stored term percentages for faculty summary display
            $termGrades[$row['student_id']] = [
                'midterm_percentage' => floatval(str_replace('%', '', $row['midterm_percentage'] ?? '0')),
                'finals_percentage' => floatval(str_replace('%', '', $row['finals_percentage'] ?? '0')),
                'term_percentage' => floatval(str_replace('%', '', $row['term_percentage'] ?? '0')),
                'term_grade' => floatval($row['term_grade'] ?? '0')
            ];
        }
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'statuses' => $statuses,
            'termGrades' => $termGrades  // Add stored term grades for faculty display
        ]);
    } catch (Exception $e) {
        error_log('getGradeStatuses error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error',
            'error' => $e->getMessage()
        ]);
    }
}
/**
 * Get lacking requirements for a student
 */
function getLackingRequirements($conn, $faculty_id) {
    $class_code = $_POST['class_code'] ?? '';
    $student_id = $_POST['student_id'] ?? '';
    
    if (empty($class_code) || empty($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Verify faculty owns this class
    $stmt = $conn->prepare("SELECT class_id FROM class WHERE class_code = ? AND faculty_id = ?");
    $stmt->bind_param("si", $class_code, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found or access denied']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Get lacking requirements
    $stmt = $conn->prepare("
        SELECT lacking_requirements 
        FROM grade_term 
        WHERE class_code = ? AND student_id = ?
        LIMIT 1
    ");
    
    $stmt->bind_param("ss", $class_code, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'lacking_requirements' => $row['lacking_requirements']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'lacking_requirements' => ''
        ]);
    }
    $stmt->close();
}

/**
 * Update lacking requirements for a student
 */
function updateLackingRequirements($conn, $faculty_id) {
    $class_code = $_POST['class_code'] ?? '';
    $student_id = $_POST['student_id'] ?? '';
    $lacking_requirements = $_POST['lacking_requirements'] ?? '';
    
    error_log("updateLackingRequirements called: class=$class_code, student=$student_id, lacking=$lacking_requirements");
    
    if (empty($class_code) || empty($student_id)) {
        error_log("❌ Missing parameters");
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        return;
    }
    
    error_log("Parameters validated, checking faculty access");
    
    // Verify faculty access
    $stmt = $conn->prepare("SELECT class_code FROM class WHERE class_code = ? AND faculty_id = ?");
    
    if (!$stmt) {
        error_log("❌ Failed to prepare statement: " . $conn->error);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    $stmt->bind_param("ss", $class_code, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    error_log("Faculty verification: rows=" . $result->num_rows . ", faculty_id=$faculty_id");
    
    if ($result->num_rows === 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Class not found or access denied']);
        $stmt->close();
        error_log("❌ Access denied for faculty $faculty_id to class $class_code");
        return;
    }
    $stmt->close();
    
    error_log("✅ Faculty access verified, proceeding with update");
    
    // Update or insert lacking requirements in grade_term
    $stmt = $conn->prepare("
        INSERT INTO grade_term (student_id, class_code, lacking_requirements, computed_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            lacking_requirements = VALUES(lacking_requirements),
            computed_at = NOW()
    ");
    
    $stmt->bind_param("sss", $student_id, $class_code, $lacking_requirements);
    
    error_log("Executing query with: student=$student_id, class=$class_code, lacking='$lacking_requirements'");
    
    if ($stmt->execute()) {
        error_log("✅ Query executed, affected rows: " . $stmt->affected_rows);
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Lacking requirements updated successfully'
        ]);
        error_log("✅ Lacking requirements saved successfully for $student_id");
    } else {
        error_log("❌ Query failed: " . $stmt->error);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update lacking requirements: ' . $stmt->error
        ]);
        error_log("❌ Failed to save lacking requirements: " . $stmt->error);
    }
    
    $stmt->close();
}
?>