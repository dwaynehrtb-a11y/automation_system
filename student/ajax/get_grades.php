<?php
// Error handling FIRST
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log file path
$log_file = __DIR__ . '/grades_error.log';
ini_set('error_log', $log_file);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Write startup message to log
error_log("=== GET_GRADES.PHP STARTED at " . date('Y-m-d H:i:s') . " ===");

// Database connection
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../config/encryption.php';
require_once '../../includes/GradesModel.php';

error_log("Database and session files loaded");

// Security check
header('Content-Type: application/json');

// Debug: Log incoming request
error_log("POST data: " . json_encode($_POST));
error_log("User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Role: " . ($_SESSION['role'] ?? 'NOT SET'));
error_log("Student ID: " . ($_SESSION['student_id'] ?? 'NOT SET'));

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    error_log("ERROR: user_id not in session");
    exit();
}

if ($_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access - not a student']);
    error_log("ERROR: role is " . $_SESSION['role']);
    exit();
}

// CSRF Token validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    error_log("ERROR: CSRF token mismatch");
    exit();
}

// Get student_id - try multiple possible sources
$student_id = $_SESSION['student_id'] ?? null;

// If not in session, get from database using user_id
if (!$student_id && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT student_id FROM student WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $student_id = $row['student_id'];
        }
        $stmt->close();
    }
}

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID not found']);
    error_log("ERROR: Could not determine student_id");
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_student_grade_summary':
            $class_code = $_POST['class_code'] ?? '';
            if (empty($class_code)) {
                throw new Exception('Class code required');
            }
            error_log("Getting summary for class: $class_code");
            $result = getStudentGradeSummary($conn, $student_id, $class_code);
            break;
            
        case 'get_student_detailed_grades':
            $class_code = $_POST['class_code'] ?? '';
            if (empty($class_code)) {
                throw new Exception('Class code required');
            }
            error_log("Getting detailed grades for class: $class_code");
            $result = getStudentDetailedGrades($conn, $student_id, $class_code);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
    error_log("Success: " . json_encode($result));
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

/**
 * Convert percentage to grade (4.0 scale) - matching faculty system
 */
function percentageToGrade($percentage) {
    $p = floatval($percentage);
    // Adjusted scale to match your institution
    if ($p >= 98) return 4.0;
    if ($p >= 95) return 4.0;
    if ($p >= 92) return 3.5;
    if ($p >= 89) return 3.5;
    if ($p >= 86) return 3.0;
    if ($p >= 83) return 3.0;
    if ($p >= 80) return 2.5;
    if ($p >= 77) return 2.5;
    if ($p >= 74) return 2.0;
    if ($p >= 71) return 2.0;
    if ($p >= 68) return 1.5;
    if ($p >= 65) return 1.5;
    if ($p >= 60) return 1.0;
    return 0.0;
}

/**
 * Calculate term details from flexible grade components (fallback if grade_term doesn't exist)
 */
function calculateTermDetailsFromComponents($conn, $student_id, $class_code, $term_type) {
    // Get components for display (this also calculates component grades)
    $components = getComponentsForDisplay($conn, $class_code, $term_type, $student_id);
    
    // Calculate term grade from component grades
    $total_component_weight = 0;
    $weighted_grade = 0;
    $total_percentage = 0;
    $component_count = 0;
    
    foreach ($components as $comp) {
        $comp_grade = floatval($comp['grade'] ?? 0);
        $comp_pct = floatval($comp['percentage'] ?? 0);
        $comp_avg_pct = floatval($comp['average_percentage'] ?? 0);
        
        if ($comp_pct > 0) {
            // Weight the component grade
            $weighted_grade += $comp_grade * ($comp_pct / 100);
            $total_component_weight += $comp_pct;
            $total_percentage += $comp_avg_pct * ($comp_pct / 100);
            $component_count++;
        }
    }
    
    // Calculate final term grade
    $term_grade = 0;
    $term_percentage = 0;
    
    if ($total_component_weight > 0) {
        // Normalize the weighted grade
        $term_grade = ($weighted_grade / ($total_component_weight / 100));
        $term_percentage = ($total_percentage / ($total_component_weight / 100));
    }
    
    return [
        'components' => $components,
        'grade' => $term_grade,
        'percentage' => $term_percentage
    ];
}

/**
 * Calculate term grade percentage from actual component scores (matches faculty calculation)
 */
function calculateTermGradePercentage($conn, $student_id, $class_code, $term_type) {
    // Get all components for this term
    $components = [];
    $compStmt = $conn->prepare("SELECT id, percentage, term_type FROM grading_components WHERE class_code = ? AND term_type = ?");
    if (!$compStmt) {
        return ['percentage' => 0, 'grade' => 0];
    }
    
    $compStmt->bind_param("ss", $class_code, $term_type);
    $compStmt->execute();
    $compRes = $compStmt->get_result();
    while ($c = $compRes->fetch_assoc()) {
        $components[] = $c;
    }
    $compStmt->close();
    
    // Get all columns per component with max scores
    $columnsByComponent = [];
    $colsStmt = $conn->prepare("
        SELECT gcc.id, gcc.component_id, gcc.max_score 
        FROM grading_component_columns gcc 
        JOIN grading_components gc ON gcc.component_id = gc.id 
        WHERE gc.class_code = ? AND gc.term_type = ?
    ");
    if ($colsStmt) {
        $colsStmt->bind_param("ss", $class_code, $term_type);
        $colsStmt->execute();
        $colsRes = $colsStmt->get_result();
        while ($col = $colsRes->fetch_assoc()) {
            $cid = $col['component_id'];
            if (!isset($columnsByComponent[$cid])) {
                $columnsByComponent[$cid] = [];
            }
            $columnsByComponent[$cid][] = $col;
        }
        $colsStmt->close();
    }
    
    // Get student's raw scores
    $scores = [];
    $gradesStmt = $conn->prepare("
        SELECT g.raw_score, gcc.component_id 
        FROM student_flexible_grades g 
        JOIN grading_component_columns gcc ON g.column_id = gcc.id 
        WHERE g.class_code = ? AND g.student_id = ?
    ");
    if ($gradesStmt) {
        $gradesStmt->bind_param("ss", $class_code, $student_id);
        $gradesStmt->execute();
        $gradesRes = $gradesStmt->get_result();
        while ($gr = $gradesRes->fetch_assoc()) {
            $cid = $gr['component_id'];
            if (!isset($scores[$cid])) {
                $scores[$cid] = ['earned' => 0.0, 'possible' => 0.0];
            }
            $scores[$cid]['earned'] += ($gr['raw_score'] === null ? 0.0 : floatval($gr['raw_score']));
        }
        $gradesStmt->close();
    }
    
    // Calculate possible scores for each component
    foreach ($components as $comp) {
        $cid = $comp['id'];
        if (!isset($scores[$cid])) {
            $scores[$cid] = ['earned' => 0.0, 'possible' => 0.0];
        }
        $possible = 0.0;
        if (isset($columnsByComponent[$cid])) {
            foreach ($columnsByComponent[$cid] as $col) {
                $possible += floatval($col['max_score']);
            }
        }
        $scores[$cid]['possible'] = $possible;
    }
    
    // Calculate weighted percentage
    $weightedSum = 0.0;
    $weightTotal = 0.0;
    
    foreach ($components as $comp) {
        $cid = $comp['id'];
        $earned = $scores[$cid]['earned'];
        $possible = $scores[$cid]['possible'];
        $rawPct = ($possible > 0 ? ($earned / $possible) * 100.0 : 0.0);
        $pct = floatval($comp['percentage']);
        
        $weightedSum += $rawPct * $pct;
        $weightTotal += $pct;
    }
    
    $percentage = ($weightTotal > 0 ? $weightedSum / $weightTotal : 0.0);
    
    return [
        'percentage' => round($percentage, 2),
        'grade' => percentageToGrade($percentage)
    ];
}

/**
 * Get student grade summary for quick preview
 */
function getStudentGradeSummary($conn, $student_id, $class_code) {
    global $_SESSION;
    
    try {
        // Check if grades are hidden using the grade_visibility_status table
        $visibility_stmt = $conn->prepare(
            "SELECT grade_visibility FROM grade_visibility_status 
             WHERE student_id = ? AND class_code = ? LIMIT 1"
        );
        
        if ($visibility_stmt) {
            $visibility_stmt->bind_param("ss", $student_id, $class_code);
            $visibility_stmt->execute();
            $visibility_result = $visibility_stmt->get_result();
            if ($visibility_result->num_rows > 0) {
                $visibility_row = $visibility_result->fetch_assoc();
                if ($visibility_row['grade_visibility'] === 'hidden') {
                    $visibility_stmt->close();
                    return [
                        'success' => true,
                        'midterm_grade' => 0,
                        'finals_grade' => 0,
                        'term_grade' => 0,
                        'term_percentage' => 0,
                        'grade_status' => 'pending',
                        'term_grade_hidden' => true,
                        'message' => 'Grades have not been released yet'
                    ];
                }
            }
            $visibility_stmt->close();
        }
        
        // Use GradesModel for auto-decryption and access control
        $gradesModel = new GradesModel($conn);
        
        $stmt = $conn->prepare("SELECT midterm_percentage, finals_percentage, term_percentage, term_grade, grade_status, is_encrypted FROM grade_term WHERE student_id = ? AND class_code = ? LIMIT 1");
        $stmt->bind_param('ss', $student_id, $class_code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $is_encrypted = intval($row['is_encrypted']) === 1;
            if ($is_encrypted) {
                return [
                    'success' => true,
                    'midterm_grade' => 0,
                    'midterm_percentage' => 0,
                    'finals_grade' => 0,
                    'finals_percentage' => 0,
                    'term_percentage' => 0,
                    'term_grade' => 0,
                    'grade_status' => $row['grade_status'] ?? 'pending',
                    'term_grade_hidden' => true,
                    'message' => 'Grades are not yet released'
                ];
            }
            $midterm_pct = floatval($row['midterm_percentage'] ?? 0);
            $finals_pct = floatval($row['finals_percentage'] ?? 0);
            $term_pct = floatval($row['term_percentage'] ?? 0);
            $term_grade = ($row['term_grade'] !== null && $row['term_grade'] !== '') ? floatval($row['term_grade']) : 0;
            
            // CALCULATE percentages from components to get accurate grades
            $midterm_details = calculateTermGradePercentage($conn, $student_id, $class_code, 'midterm');
            $finals_details = calculateTermGradePercentage($conn, $student_id, $class_code, 'finals');
            
            // Use calculated percentages if available, otherwise use stored percentages
            $calc_midterm_pct = $midterm_details['percentage'] > 0 ? $midterm_details['percentage'] : $midterm_pct;
            $calc_finals_pct = $finals_details['percentage'] > 0 ? $finals_details['percentage'] : $finals_pct;
            
            // Convert percentages to grades
            $midterm_grade = percentageToGrade($calc_midterm_pct);
            $finals_grade = percentageToGrade($calc_finals_pct);
            
            // RECALCULATE term grade from term percentage to match faculty calculation
            $calc_term_grade = percentageToGrade($term_pct);
            
            error_log("Grade calculation: calc_midterm_pct=$calc_midterm_pct, midterm_grade=$midterm_grade");
            error_log("Finals calculation: calc_finals_pct=$calc_finals_pct, finals_grade=$finals_grade");
            error_log("Term calculation: term_pct=$term_pct, calc_term_grade=$calc_term_grade");
            
            return [
                'success' => true,
                'midterm_grade' => $midterm_grade,
                'midterm_percentage' => $calc_midterm_pct,
                'finals_grade' => $finals_grade,
                'finals_percentage' => $calc_finals_pct,
                'term_percentage' => $term_pct,
                'term_grade' => $calc_term_grade,
                'grade_status' => $row['grade_status'] ?? 'pending',
                'term_grade_hidden' => false,
                'message' => 'Grades have been released'
            ];
        }
    } catch (Exception $e) {
        error_log("GradesModel error: " . $e->getMessage());
        // Fall back to direct query if model fails
    }
    
    // Fallback: Calculate from components if no grade_term entry
    $weights_query = "SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code = ? LIMIT 1";
    $stmt = $conn->prepare($weights_query);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $weights_result = $stmt->get_result();
    
    if ($weights_result->num_rows === 0) {
        $midterm_weight = 40.00;
        $finals_weight = 60.00;
    } else {
        $weights = $weights_result->fetch_assoc();
        $midterm_weight = floatval($weights['midterm_weight']);
        $finals_weight = floatval($weights['finals_weight']);
    }
    $stmt->close();
    
    $midterm_grade = calculateTermGrade($conn, $student_id, $class_code, 'midterm');
    $finals_grade = calculateTermGrade($conn, $student_id, $class_code, 'finals');
    
    $term_grade = 0;
    if ($midterm_grade > 0 || $finals_grade > 0) {
        $term_grade = ($midterm_grade * ($midterm_weight / 100)) + ($finals_grade * ($finals_weight / 100));
    }
    
    // Try to get grade_status from grade_term even in fallback
    $grade_status = 'pending';
    $status_stmt = $conn->prepare("SELECT grade_status FROM grade_term WHERE student_id = ? AND class_code = ? LIMIT 1");
    if ($status_stmt) {
        $status_stmt->bind_param("ss", $student_id, $class_code);
        $status_stmt->execute();
        $status_result = $status_stmt->get_result();
        if ($status_row = $status_result->fetch_assoc()) {
            $grade_status = $status_row['grade_status'] ?? 'pending';
        }
        $status_stmt->close();
    }
    
    return [
        'success' => true,
        'midterm_grade' => round($midterm_grade, 2),
        'finals_grade' => round($finals_grade, 2),
        'term_grade' => round($term_grade, 2),
        'grade_status' => $grade_status,
        'status' => $term_grade >= 1.0 ? 'passed' : 'pending'
    ];
}

/**
 * Calculate term grade (midterm or finals)
 */
function calculateTermGrade($conn, $student_id, $class_code, $term_type) {
    $components_query = "
        SELECT gc.id, gc.percentage
        FROM grading_components gc
        WHERE gc.class_code = ? AND gc.term_type = ?
        ORDER BY gc.order_index, gc.id
    ";
    
    $stmt = $conn->prepare($components_query);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $class_code, $term_type);
    $stmt->execute();
    $components_result = $stmt->get_result();
    
    $total_weighted_score = 0;
    $total_weight = 0;
    
    while ($comp = $components_result->fetch_assoc()) {
        $columns_query = "
            SELECT gcc.id, gcc.max_score
            FROM grading_component_columns gcc
            WHERE gcc.component_id = ?
            ORDER BY gcc.order_index, gcc.id
        ";
        
        $col_stmt = $conn->prepare($columns_query);
        if (!$col_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $col_stmt->bind_param("i", $comp['id']);
        $col_stmt->execute();
        $columns_result = $col_stmt->get_result();
        
        $comp_score = 0;
        $comp_max = 0;
        
        while ($col = $columns_result->fetch_assoc()) {
            $grade_query = "
                SELECT COALESCE(grade_value, CAST(raw_score AS DECIMAL)) as score
                FROM student_flexible_grades 
                WHERE student_id = ? AND column_id = ? AND class_code = ?
            ";
            
            $grade_stmt = $conn->prepare($grade_query);
            if (!$grade_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $grade_stmt->bind_param("sis", $student_id, $col['id'], $class_code);
            $grade_stmt->execute();
            $grade_result = $grade_stmt->get_result();
            
            if ($grade_result->num_rows > 0) {
                $grade_row = $grade_result->fetch_assoc();
                $score = floatval($grade_row['score'] ?? 0);
                $comp_score += $score;
                $comp_max += floatval($col['max_score']);
            }
            $grade_stmt->close();
        }
        $col_stmt->close();
        
        if ($comp_max > 0) {
            $comp_percentage = ($comp_score / $comp_max) * 100;
            $weighted = $comp_percentage * (floatval($comp['percentage']) / 100);
            $total_weighted_score += $weighted;
            $total_weight += floatval($comp['percentage']);
        }
    }
    $stmt->close();
    
    if ($total_weight > 0) {
        $term_percentage = ($total_weighted_score / $total_weight) * 100;
        return percentageToGrade($term_percentage);
    }
    
    return 0.0;
}

/**
 * Get detailed grade breakdown with all components and items
 */
function getStudentDetailedGrades($conn, $student_id, $class_code) {
    // Get term weights
    $weights_query = "SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code = ? LIMIT 1";
    $stmt = $conn->prepare($weights_query);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $weights_result = $stmt->get_result();
    
    if ($weights_result->num_rows === 0) {
        $midterm_weight = 40.00;
        $finals_weight = 60.00;
    } else {
        $weights = $weights_result->fetch_assoc();
        $midterm_weight = floatval($weights['midterm_weight']);
        $finals_weight = floatval($weights['finals_weight']);
    }
    $stmt->close();
    
    // Initialize grade_status
    $grade_status = 'pending';
    
    // Get grades from grade_term (official source)
    $term_query = "
        SELECT 
            midterm_percentage,
            finals_percentage,
            term_percentage,
            grade_status
        FROM grade_term
        WHERE student_id = ? AND class_code = ?
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($term_query);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $student_id, $class_code);
    $stmt->execute();
    $term_result = $stmt->get_result();
    $stmt->close();
    
    if ($term_result->num_rows > 0) {
        $row = $term_result->fetch_assoc();
        
        // Check if grades are encrypted (hidden from students)
        $midterm_pct = $row['midterm_percentage'] ?? null;
        $finals_pct = $row['finals_percentage'] ?? null;
        $term_pct = $row['term_percentage'] ?? null;
        $grade_status = $row['grade_status'] ?? 'pending';
        
        // Determine encryption strictly via is_encrypted flag (no heuristics)
        $encFlagStmt = $conn->prepare("SELECT is_encrypted FROM grade_term WHERE student_id = ? AND class_code = ? LIMIT 1");
        $encFlagStmt->bind_param("ss", $student_id, $class_code);
        $encFlagStmt->execute();
        $encFlagRow = $encFlagStmt->get_result()->fetch_assoc();
        $encFlagStmt->close();
        $is_encrypted = $encFlagRow && intval($encFlagRow['is_encrypted']) === 1;
        
        if ($is_encrypted) {
            return [
                'success' => true,
                'midterm' => ['components' => [], 'grade' => 0, 'percentage' => 0, 'hidden' => true],
                'finals' => ['components' => [], 'grade' => 0, 'percentage' => 0, 'hidden' => true],
                'term_grade' => 0,
                'grade_status' => $grade_status ?? 'pending',
                'term_grade_hidden' => true,
                'midterm_weight' => $midterm_weight,
                'finals_weight' => $finals_weight,
                'message' => 'Grades are not yet released by your instructor'
            ];
        }
        
        // If we reach here, grades are NOT encrypted, so use them
        $midterm_pct = floatval($midterm_pct);
        $finals_pct = floatval($finals_pct);
        $term_pct = floatval($term_pct);
        
        // IMPORTANT: If term_pct is 0 or null, ALWAYS calculate it from midterm and finals
        // Don't rely on stored term_percentage as it might be stale or incomplete
        if ($term_pct == 0) {
            $term_pct = ($midterm_pct * ($midterm_weight / 100)) + ($finals_pct * ($finals_weight / 100));
        }
        
        // If percentages are 0 or null, calculate from components instead
        if ($midterm_pct == 0 && $finals_pct == 0 && $term_pct == 0) {
            // Fallback: calculate from components
            $midterm_data = calculateTermDetailsFromComponents($conn, $student_id, $class_code, 'midterm');
            $finals_data = calculateTermDetailsFromComponents($conn, $student_id, $class_code, 'finals');
            
            $term_grade = 0;
            if ($midterm_data['grade'] > 0 || $finals_data['grade'] > 0) {
                $term_grade = ($midterm_data['grade'] * ($midterm_weight / 100)) + 
                             ($finals_data['grade'] * ($finals_weight / 100));
            }
        } else {
            // Use stored percentages
            // Get midterm components (for display with items)
            $midterm_components = getComponentsForDisplay($conn, $class_code, 'midterm', $student_id);
            
            // Get finals components (for display with items)
            $finals_components = getComponentsForDisplay($conn, $class_code, 'finals', $student_id);
            
            // Create data structure with percentages and grades
            $midterm_data = [
                'components' => $midterm_components,
                'grade' => percentageToGrade($midterm_pct),
                'percentage' => $midterm_pct
            ];
            
            $finals_data = [
                'components' => $finals_components,
                'grade' => percentageToGrade($finals_pct),
                'percentage' => $finals_pct
            ];
            
            $term_grade = percentageToGrade($term_pct);
        }
    } else {
        // Fallback if no grade_term entry - calculate from flexible grades components
        $midterm_data = calculateTermDetailsFromComponents($conn, $student_id, $class_code, 'midterm');
        $finals_data = calculateTermDetailsFromComponents($conn, $student_id, $class_code, 'finals');
        
        $term_grade = 0;
        if ($midterm_data['grade'] > 0 || $finals_data['grade'] > 0) {
            $term_grade = ($midterm_data['grade'] * ($midterm_weight / 100)) + 
                         ($finals_data['grade'] * ($finals_weight / 100));
        }
    }
    
    return [
        'success' => true,
        'midterm' => $midterm_data,
        'finals' => $finals_data,
        'term_grade' => $term_grade,
        'grade_status' => !empty($grade_status) && $grade_status !== 'pending' ? $grade_status : ($term_grade >= 1.0 ? 'passed' : ($term_grade > 0 ? 'incomplete' : 'failed')),
        'term_grade_hidden' => false,
        'midterm_weight' => $midterm_weight,
        'finals_weight' => $finals_weight
    ];
}

/**
 * Get components for display with items (names, weights, and individual scores)
 */
function getComponentsForDisplay($conn, $class_code, $term_type, $student_id = null) {
    $query = "
        SELECT 
            gc.id,
            gc.component_name,
            gc.percentage,
            gc.order_index
        FROM grading_components gc
        WHERE gc.class_code = ? AND gc.term_type = ?
        ORDER BY gc.order_index, gc.id
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $class_code, $term_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $components = [];
    while ($row = $result->fetch_assoc()) {
        $component_id = $row['id'];
        $component_name = $row['component_name'];
        
        // Check if this is an Attendance component
        $is_attendance = stripos($component_name, 'attendance') !== false;
        
        if ($is_attendance) {
            // For Attendance, just get the total count
            $attendance_query = "
                SELECT 
                    SUM(CASE WHEN sfg.raw_score = '1' OR CAST(sfg.raw_score AS UNSIGNED) = 1 THEN 1 ELSE 0 END) as total_attended,
                    COUNT(gcc.id) as total_sessions
                FROM grading_component_columns gcc
                LEFT JOIN student_flexible_grades sfg 
                    ON gcc.id = sfg.column_id 
                    AND sfg.class_code = ?
                    AND sfg.student_id = ?
                WHERE gcc.component_id = ?
            ";
            
            $att_stmt = $conn->prepare($attendance_query);
            if (!$att_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $att_stmt->bind_param("ssi", $class_code, $student_id, $component_id);
            $att_stmt->execute();
            $att_result = $att_stmt->get_result();
            $att_row = $att_result->fetch_assoc();
            $att_stmt->close();
            
            $total_attended = intval($att_row['total_attended'] ?? 0);
            $total_sessions = intval($att_row['total_sessions'] ?? 0);
            
            $attendance_percentage = $total_sessions > 0 ? ($total_attended / $total_sessions) * 100 : 0;
            $comp_grade = percentageToGrade($attendance_percentage);
            
            // Create ONLY ONE item for attendance total
            $items = [
                [
                    'id' => 0,
                    'column_name' => 'Total Attendance',
                    'max_score' => $total_sessions,
                    'score' => $total_attended,
                    'order_index' => 0
                ]
            ];
            
            $components[] = [
                'id' => $component_id,
                'component_name' => $component_name,
                'percentage' => floatval($row['percentage']),
                'items' => $items,
                'average_percentage' => $attendance_percentage,
                'grade' => $comp_grade
            ];
        } else {
            // For other components, get all individual items
            $items_query = "
                SELECT 
                    gcc.id,
                    gcc.column_name,
                    gcc.max_score,
                    gcc.order_index,
                    sfg.grade_value,
                    sfg.raw_score
                FROM grading_component_columns gcc
                LEFT JOIN student_flexible_grades sfg 
                    ON gcc.id = sfg.column_id 
                    AND sfg.class_code = ?
                    AND sfg.student_id = ?
                WHERE gcc.component_id = ?
                ORDER BY gcc.order_index, gcc.id
            ";
            
            $items_stmt = $conn->prepare($items_query);
            if (!$items_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $items_stmt->bind_param("ssi", $class_code, $student_id, $component_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $items = [];
            $comp_total_score = 0;
            $comp_total_max = 0;
            
            while ($item = $items_result->fetch_assoc()) {
                $score = $item['grade_value'] !== null ? floatval($item['grade_value']) : 
                        ($item['raw_score'] !== null ? floatval($item['raw_score']) : null);
                
                $max_score = floatval($item['max_score']);
                
                if ($score !== null) {
                    $comp_total_score += $score;
                    $comp_total_max += $max_score;
                }
                
                $items[] = [
                    'id' => $item['id'],
                    'column_name' => $item['column_name'],
                    'max_score' => $max_score,
                    'score' => $score,
                    'order_index' => $item['order_index']
                ];
            }
            
            $items_stmt->close();
            
            // Calculate component average
            $avg_percentage = $comp_total_max > 0 ? ($comp_total_score / $comp_total_max) * 100 : 0;
            $comp_grade = percentageToGrade($avg_percentage);
            
            $components[] = [
                'id' => $component_id,
                'component_name' => $component_name,
                'percentage' => floatval($row['percentage']),
                'items' => $items,
                'average_percentage' => $avg_percentage,
                'grade' => $comp_grade
            ];
        }
    }
    
    $stmt->close();
    return $components;
}

// /**

// /**
// function getTermDetails($conn, $student_id, $class_code, $term_type) {
//     $components_query = "
//         SELECT gc.id, gc.component_name, gc.percentage, gc.order_index
//         FROM grading_components gc
//         WHERE gc.class_code = ? AND gc.term_type = ?
//         ORDER BY gc.order_index, gc.id
//     ";
    
//     $stmt = $conn->prepare($components_query);
//     if (!$stmt) {
//         throw new Exception("Database error: " . $conn->error);
//     }
    
//     $stmt->bind_param("ss", $class_code, $term_type);
//     $stmt->execute();
//     $components_result = $stmt->get_result();
    
//     $components = [];
//     $total_weighted_score = 0;
//     $total_weight = 0;
    
//     while ($comp = $components_result->fetch_assoc()) {
//         $items = getComponentItems($conn, $comp['id'], $student_id, $class_code);
        
//         $comp_score = 0;
//         $comp_max = 0;
        
//         foreach ($items as $item) {
//             if ($item['score'] !== null) {
//                 $comp_score += floatval($item['score']);
//                 $comp_max += floatval($item['max_score']);
//             }
//         }
        
//         $comp_percentage = $comp_max > 0 ? ($comp_score / $comp_max * 100) : 0;
//         $comp_grade = percentageToGrade($comp_percentage);
        
//         if ($comp_max > 0) {
//             $weighted = $comp_percentage * (floatval($comp['percentage']) / 100);
//             $total_weighted_score += $weighted;
//             $total_weight += floatval($comp['percentage']);
//         }
        
//         $components[] = [
//             'id' => $comp['id'],
//             'component_name' => $comp['component_name'],
//             'percentage' => floatval($comp['percentage']),
//             'items' => $items,
//             'average_percentage' => round($comp_percentage, 2),
//             'grade' => round($comp_grade, 2)
//         ];
//     }
//     $stmt->close();
    
//     $term_grade = $total_weight > 0 ? ($total_weighted_score / $total_weight) * 100 : 0;
//     $term_grade_value = percentageToGrade($term_grade);
    
//     return [
//         'components' => $components,
//         'grade' => round($term_grade_value, 2),
//         'percentage' => round($term_grade, 2)
//     ];
// }

$conn->close();
?>