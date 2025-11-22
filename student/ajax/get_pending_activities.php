<?php
// Error handling FIRST (before anything else)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log file path
$log_file = __DIR__ . '/pending_activities_error.log';
ini_set('error_log', $log_file);

// Start session (BEFORE includes)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Write startup message
error_log("=== GET_PENDING_ACTIVITIES.PHP STARTED at " . date('Y-m-d H:i:s') . " ===");

// Database and config
require_once '../../config/db.php';
require_once '../../config/session.php';

// Set JSON header
header('Content-Type: application/json');

// Debug logging
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Session role: " . ($_SESSION['role'] ?? 'NOT SET'));
error_log("Session student_id: " . ($_SESSION['student_id'] ?? 'NOT SET'));

try {
    // Direct session validation (proven method from get_grades.php)
    if (!isset($_SESSION['user_id'])) {
        error_log("ERROR: user_id not in session");
        http_response_code(401);
        throw new Exception('Not authenticated');
    }

    if ($_SESSION['role'] !== 'student') {
        error_log("ERROR: role is " . ($_SESSION['role'] ?? 'null'));
        http_response_code(401);
        throw new Exception('Not a student');
    }

    // Get student_id - direct from session
    $student_id = $_SESSION['student_id'] ?? null;

    // If not in session, get from database using user_id
    if (!$student_id && isset($_SESSION['user_id'])) {
        error_log("Attempting to get student_id from database");
        $stmt = $conn->prepare("SELECT student_id FROM student WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $student_id = $row['student_id'];
                error_log("Found student_id from database: " . $student_id);
            }
            $stmt->close();
        }
    }

    if (!$student_id) {
        error_log("ERROR: Could not determine student_id");
        http_response_code(401);
        throw new Exception('No student ID found');
    }

    error_log("Proceeding with student_id: " . $student_id);

    // Query for classes with INC status components (flexible grades)
    $query = "
        SELECT DISTINCT
            c.class_code,
            c.course_code,
            s.course_title,
            c.term,
            gt.midterm_percentage,
            gt.finals_percentage,
            gt.term_percentage,
            gt.grade_status,
            u.name as faculty_name
        FROM student_flexible_grades sfg
        INNER JOIN class c ON sfg.class_code = c.class_code
        LEFT JOIN subjects s ON c.course_code = s.course_code
        LEFT JOIN users u ON c.faculty_id = u.id
        LEFT JOIN grade_term gt ON gt.student_id = sfg.student_id AND gt.class_code = sfg.class_code
        WHERE sfg.student_id = ? AND sfg.status = 'inc'
        ORDER BY c.academic_year DESC, c.term DESC, c.course_code
    ";

    error_log("Executing query for student_id: " . $student_id);
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        throw new Exception("Database prepare failed");
    }

    if (!$stmt->bind_param("s", $student_id)) {
        error_log("Bind param failed: " . $stmt->error);
        throw new Exception("Bind parameter failed");
    }

    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        throw new Exception("Query execution failed");
    }

    $result = $stmt->get_result();
    if (!$result) {
        error_log("Get result failed: " . $stmt->error);
        throw new Exception("Get result failed");
    }

    $pending_activities = [];
    $processed_classes = [];

    while ($row = $result->fetch_assoc()) {
        $class_code = $row['class_code'];
        
        // Skip if we already processed this class (due to DISTINCT join)
        if (in_array($class_code, $processed_classes)) {
            continue;
        }
        $processed_classes[] = $class_code;
        
        // Get all INC components for this class
        $inc_components = getIncComponents($conn, $student_id, $class_code);
        $row['inc_components'] = $inc_components;
        
        // Calculate actual percentages from grading components (like get_grades.php does)
        $midterm_pct = calculateFlexibleTermPercentage($conn, $student_id, $class_code, 'midterm');
        $midterm_status = formatPercentageStatus($midterm_pct);
        
        $finals_pct = calculateFlexibleTermPercentage($conn, $student_id, $class_code, 'finals');
        $finals_status = formatPercentageStatus($finals_pct);
        
        $row['midterm_status'] = $midterm_status;
        $row['finals_status'] = $finals_status;
        $row['faculty_name'] = $row['faculty_name'] ?: 'Unknown Faculty';
        
        $pending_activities[] = $row;
    }

    $stmt->close();

    error_log("Successfully retrieved " . count($pending_activities) . " pending activities");

    // Format response
    echo json_encode([
        'success' => true,
        'count' => count($pending_activities),
        'activities' => $pending_activities
    ]);

} catch (Exception $e) {
    error_log("ERROR in get_pending_activities.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching pending activities',
        'error' => $e->getMessage()
    ]);
}

/**
 * Get all INC (incomplete) components for a student in a class
 */
function getIncComponents($conn, $student_id, $class_code) {
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT
                sfg.column_id,
                gcc.column_name,
                gcc.component_id
            FROM student_flexible_grades sfg
            JOIN grading_component_columns gcc ON sfg.column_id = gcc.id
            WHERE sfg.student_id = ? 
              AND sfg.class_code = ? 
              AND sfg.status = 'inc'
            ORDER BY gcc.column_name
        ");
        
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param("ss", $student_id, $class_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $components = [];
        while ($row = $result->fetch_assoc()) {
            $components[] = $row;
        }
        $stmt->close();
        
        return $components;
    } catch (Exception $e) {
        error_log("Error getting INC components: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate flexible term percentage from grading components
 */
function calculateFlexibleTermPercentage($conn, $student_id, $class_code, $term_type) {
    try {
        // Get grading components for this term
        $comp_stmt = $conn->prepare(
            "SELECT id, percentage FROM grading_components WHERE class_code = ? AND term_type = ?"
        );
        if (!$comp_stmt) {
            return 0;
        }
        
        $comp_stmt->bind_param("ss", $class_code, $term_type);
        $comp_stmt->execute();
        $comp_result = $comp_stmt->get_result();
        $components = [];
        
        while ($comp = $comp_result->fetch_assoc()) {
            $components[] = $comp;
        }
        $comp_stmt->close();
        
        if (empty($components)) {
            return 0;
        }
        
        // Calculate total percentage from all components
        $total_percentage = 0;
        
        foreach ($components as $component) {
            $component_id = $component['id'];
            $component_weight = $component['percentage'];
            
            // Get component's score percentage
            $score_stmt = $conn->prepare("
                SELECT SUM(CAST(g.raw_score AS DECIMAL(10,2))) as earned_total,
                       SUM(gcc.max_score) as possible_total
                FROM student_flexible_grades g
                JOIN grading_component_columns gcc ON g.column_id = gcc.id
                WHERE g.class_code = ? 
                  AND g.student_id = ? 
                  AND gcc.component_id = ?
            ");
            
            if ($score_stmt) {
                $score_stmt->bind_param("ssi", $class_code, $student_id, $component_id);
                $score_stmt->execute();
                $score_result = $score_stmt->get_result();
                
                if ($score = $score_result->fetch_assoc()) {
                    $earned = floatval($score['earned_total'] ?? 0);
                    $possible = floatval($score['possible_total'] ?? 0);
                    
                    if ($possible > 0) {
                        $component_pct = ($earned / $possible) * 100;
                        $total_percentage += ($component_pct * $component_weight / 100);
                    }
                }
                $score_stmt->close();
            }
        }
        
        return round($total_percentage, 2);
        
    } catch (Exception $e) {
        error_log("Error calculating flexible term percentage: " . $e->getMessage());
        return 0;
    }
}

/**
 * Format percentage to display status
 */
function formatPercentageStatus($percentage) {
    if ($percentage === null || $percentage === 0) {
        return 'No submission';
    }
    return round($percentage, 2) . '%';
}