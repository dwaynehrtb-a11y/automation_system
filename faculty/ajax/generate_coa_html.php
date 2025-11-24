<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('log_errors_max_len', 0);
/**
 * Generate COA (Course Outcomes Assessment Summary) as HTML
 */

// Define log file
$logFile = __DIR__ . '/../../logs/coa_debug.log';
if (!is_dir(dirname($logFile))) {
    @mkdir(dirname($logFile), 0755, true);
}

function log_debug($msg) {
    global $logFile;
    @file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

log_debug('=== COA Generation Started ===');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Initialize error tracking
$errors = [];

try {
    log_debug('Loading config files...');
    require_once '../../config/db.php';
    require_once '../../config/encryption.php';
    log_debug('Config files loaded successfully');
} catch (Exception $e) {
    log_debug('Config error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database configuration error: ' . $e->getMessage()]);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    log_debug('Auth check failed. user_id=' . ($_SESSION['user_id'] ?? 'null') . ', role=' . ($_SESSION['role'] ?? 'null'));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

log_debug('Auth passed. user_id=' . $_SESSION['user_id'] . ', role=' . $_SESSION['role']);

$faculty_id = $_SESSION['user_id'];
$class_code = $_GET['class_code'] ?? '';
if (empty($class_code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Class code required']);
    exit;
}

try {
    // Get class info from class_code
    $class_parts = explode('_', $class_code);
    
    // Get class from class table with proper matching
    $stmt = $conn->prepare("SELECT * FROM class WHERE class_code = ?");
    $stmt->bind_param("s", $class_code);
    $stmt->execute(); 
    $class = $stmt->get_result()->fetch_assoc(); 
    $stmt->close();
    
    if (!$class) { 
        log_debug("Class not found: $class_code");
        throw new Exception('Class not found'); 
    }

    log_debug("Class found: ID=" . $class['id'] . ", Course=" . $class['course_code']);

    // Subject info
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE course_code = ? LIMIT 1");
    $stmt->bind_param("s", $class['course_code']);
    $stmt->execute(); 
    $subject = $stmt->get_result()->fetch_assoc() ?? []; 
    $stmt->close();

    // Faculty name
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute(); 
    $faculty = $stmt->get_result()->fetch_assoc() ?? []; 
    $stmt->close();

    // Get course outcomes for this course
    $coQuery = "SELECT co_number, co_description FROM course_outcomes WHERE course_code = ? ORDER BY co_number";
    $stmt = $conn->prepare($coQuery);
    if (!$stmt) {
        log_debug("CO query prepare failed: " . $conn->error);
        throw new Exception("CO query prepare failed");
    }
    
    $stmt->bind_param("s", $class['course_code']);
    if (!$stmt->execute()) {
        log_debug("CO query execute failed: " . $stmt->error);
        throw new Exception("CO query execute failed");
    }
    
    $courseOutcomes = []; 
    $res = $stmt->get_result();
    if ($res) {
        while($row = $res->fetch_assoc()){ 
            $courseOutcomes[] = $row; 
        }
    }
    $stmt->close();
    log_debug("Found " . count($courseOutcomes) . " course outcomes");

    // Performance metrics - query all data then aggregate in PHP for summary
    // INNER JOIN on student_flexible_grades ensures only components with actual grades are included
    $coPerfQuery = "SELECT 
                    co.co_number,
                    co.co_description,
                    gc.component_name AS assessment_name,
                    gcc.performance_target,
                    gcc.max_score,
                    COUNT(DISTINCT CASE 
                        WHEN sfg.raw_score >= (gcc.performance_target / 100 * gcc.max_score) 
                        THEN sfg.student_id 
                    END) AS students_met_target,
                    COUNT(DISTINCT sfg.student_id) AS total_students,
                    COALESCE(ROUND(
                        COUNT(DISTINCT CASE 
                            WHEN sfg.raw_score >= (gcc.performance_target / 100 * gcc.max_score) 
                            THEN sfg.student_id 
                        END) * 100.0 / NULLIF(COUNT(DISTINCT sfg.student_id), 0),
                        2
                    ), 0) AS success_rate
                   FROM course_outcomes co
                   INNER JOIN class c ON c.course_code = co.course_code
                   INNER JOIN grading_components gc ON gc.class_code = c.class_code
                   INNER JOIN grading_component_columns gcc ON gcc.component_id = gc.id AND JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR))
                   INNER JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
                   INNER JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id
                   WHERE co.course_code = ?
                        AND c.class_code = ?
                        AND ce.status = 'enrolled'
                   GROUP BY co.co_number, co.co_description, gc.id, gcc.id, gcc.performance_target, gcc.max_score
                   ORDER BY co.co_number, gc.id";
    
    log_debug("Executing performance query...");
    $stmt = $conn->prepare($coPerfQuery);
    if (!$stmt) {
        log_debug("Perf query prepare failed: " . $conn->error);
        throw new Exception("Perf query prepare failed");
    }
    
    $stmt->bind_param("sss", $class_code, $class['course_code'], $class_code);
    if (!$stmt->execute()) {
        log_debug("Perf query execute failed: " . $stmt->error);
        throw new Exception("Perf query execute failed");
    }
    
    $coPerf = []; 
    $res = $stmt->get_result();
    if ($res) {
        while($row = $res->fetch_assoc()){ 
            $coPerf[] = $row; 
        }
    }
    $stmt->close();
    log_debug("Found " . count($coPerf) . " performance records");

    // Build COA HTML - Exact format matching official COA document
    $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>COA - ' . htmlspecialchars($class_code) . '</title>
<style>
* { box-sizing: border-box; }
html, body { 
    margin: 0;
    padding: 15px;
    font-family: "Calibri", "Arial", sans-serif;
    font-size: 10pt;
    line-height: 1.3;
    color: #000;
    background: white;
}
@media print {
    @page { size: A4 portrait; margin: 10mm 12mm; }
    body { padding: 10px; }
}
.info-table {
    border-collapse: collapse;
    width: 100%;
    margin: 0 0 20px 0;
    border: 2px solid #000;
    font-size: 10pt;
}
.info-table tr {
    height: auto;
}
.info-table td {
    border: 1px solid #000;
    padding: 6px 8px;
    text-align: left;
    vertical-align: top;
}
.assessment-table {
    border-collapse: collapse;
    width: 100%;
    margin: 0;
    border: 2px solid #000;
    font-size: 10pt;
}
.assessment-table td {
    border: 1px solid #000;
    padding: 5px 6px;
    text-align: left;
    vertical-align: middle;
    height: auto;
}
.gray-header {
    background-color: #d3d3d3;
    font-weight: bold;
    text-align: center;
    padding: 6px 4px;
}
.sub-header {
    background-color: #e8e8e8;
    font-weight: bold;
    text-align: center;
    padding: 5px 4px;
}
.center {
    text-align: center;
}
.bold {
    font-weight: bold;
}
</style>
</head>
<body>';

    // Get course information
    $course_query = "SELECT course_code, course_title, course_desc FROM subjects WHERE course_code = ?";
    $course_stmt = $conn->prepare($course_query);
    $course_stmt->bind_param("s", $class['course_code']);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    $course_info = $course_result->fetch_assoc() ?? [];
    $course_stmt->close();
    
    $instructor = $faculty['name'] ?? 'N/A';
    
    // EXACT FORMAT: Info table with proper borders and structure
    $html .= '<table class="info-table">'
        . '<tr>'
        . '<td style="width:25%; font-weight:bold;">ACADEMIC TERM/ SCHOOL YEAR:</td>'
        . '<td style="width:25%;">' . htmlspecialchars($class['term'] ?? 'N/A') . ', AY ' . htmlspecialchars($class['academic_year'] ?? 'N/A') . '</td>'
        . '<td style="width:25%; font-weight:bold;">COURSE CODE:</td>'
        . '<td style="width:25%;">' . htmlspecialchars($class['course_code']) . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="font-weight:bold;">COPIES ISSUED TO:</td>'
        . '<td colspan="3">PROGRAM CHAIR<br/>ACADEMIC DIRECTOR</td>'
        . '</tr>'
        . '</table>'
        . '<table class="info-table">'
        . '<tr>'
        . '<td style="font-weight:bold;">COURSE TITLE:</td>'
        . '<td colspan="3">' . htmlspecialchars($course_info['course_title'] ?? 'N/A') . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="font-weight:bold;">INSTRUCTOR:</td>'
        . '<td colspan="3">' . htmlspecialchars($instructor) . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="font-weight:bold;">COURSE DESCRIPTION:</td>'
        . '<td colspan="3" style="background:#f9f9f9;">' . htmlspecialchars(substr($course_info['course_desc'] ?? '', 0, 300)) . '</td>'
        . '</tr>'
        . '</table>';

    // Assessment Performance Section - EXACT format from official document with section column
    $section_code = htmlspecialchars($class['section'] ?? 'INF222');
    $html .= '<table class="assessment-table" style="margin-top:15px;">'
        . '<tr>'
        . '<td class="gray-header" style="width:12%;">Course Outcome</td>'
        . '<td class="gray-header" style="width:12%;">Assessment</td>'
        . '<td class="gray-header" style="width:10%;">Performance Target</td>'
        . '<td class="gray-header" colspan="3" style="width:28%;">Number of Students who obtained a PASSING Rate</td>'
        . '<td class="gray-header" style="width:12%;">Evaluation</td>'
        . '<td class="gray-header" style="width:16%;">Recommendation</td>'
        . '</tr>'
        . '<tr>'
        . '<td class="sub-header"></td>'
        . '<td class="sub-header"></td>'
        . '<td class="sub-header"></td>'
        . '<td class="sub-header" style="width:8%;">' . $section_code . '</td>'
        . '<td class="sub-header" style="width:10%;">Total</td>'
        . '<td class="sub-header" style="width:10%;">%</td>'
        . '<td class="sub-header"></td>'
        . '<td class="sub-header"></td>'
        . '</tr>';

    // Add performance data rows - organized by CO with aggregated assessments
    if (count($coPerf) === 0) {
        $html .= '<tr><td colspan="8" style="text-align:center; padding:15px; color:#999;">No assessment data available</td></tr>';
    } else {
        // Group by Course Outcome and aggregate assessments by name, tracking unique students
        $coGroups = [];
        foreach ($coPerf as $perf) {
            $co_key = $perf['co_number'];
            
            // Normalize assessment names: combine 'Quiz' and 'Quizzes' into one
            $assessment_name = $perf['assessment_name'];
            $assessment_key = strtolower(trim($assessment_name));
            // Normalize quiz variations
            if (strpos($assessment_key, 'quiz') !== false) {
                $assessment_key = 'quiz'; // Both 'Quiz' and 'Quizzes' become 'quiz'
            }
            
            if (!isset($coGroups[$co_key])) {
                $coGroups[$co_key] = [
                    'co_number' => $perf['co_number'],
                    'co_description' => $perf['co_description'],
                    'assessments' => []
                ];
            }
            
            // Aggregate assessments by name - track met/total with DISTINCT student counts
            if (!isset($coGroups[$co_key]['assessments'][$assessment_key])) {
                // Use normalized display name for consistency
                $display_name = $assessment_key === 'quiz' ? 'Quiz' : ucfirst($assessment_key);
                
                $coGroups[$co_key]['assessments'][$assessment_key] = [
                    'name' => $display_name,  // Use normalized name
                    'target' => $perf['performance_target'] ?? 60,
                    'met_students' => [],      // Track unique met students per column
                    'all_students' => [],       // Track all students per column
                    'success_rate' => 0
                ];
            }
            
            // Collect student counts - we'll deduplicate at the end
            $met = intval($perf['students_met_target'] ?? 0);
            $total = intval($perf['total_students'] ?? 0);
            
            // Store for this column
            $coGroups[$co_key]['assessments'][$assessment_key]['met_students'][] = $met;
            $coGroups[$co_key]['assessments'][$assessment_key]['all_students'][] = $total;
        }
        
        // Post-process: Convert met/all students arrays to actual max unique count
        // (The query uses DISTINCT student_id per column, so max represents unique students)
        foreach ($coGroups as &$coData) {
            foreach ($coData['assessments'] as &$assessment) {
                $assessment['met'] = max($assessment['met_students']);
                $assessment['total'] = max($assessment['all_students']);
                unset($assessment['met_students']);
                unset($assessment['all_students']);
                
                if ($assessment['total'] > 0) {
                    $assessment['success_rate'] = round(($assessment['met'] / $assessment['total']) * 100, 2);
                } else {
                    $assessment['success_rate'] = 0;
                }
            }
        }
        unset($coData, $assessment);  // Remove references to prevent PHP array corruption
        
        // Display each CO with its aggregated assessments
        foreach ($coGroups as $coData) {
            $co_num = htmlspecialchars($coData['co_number']);
            $co_desc = htmlspecialchars($coData['co_description']);
            $assessments = array_values($coData['assessments']); // Reindex to sequential
            $numAssessments = count($assessments);
            
            // Display each aggregated assessment for this CO
            foreach ($assessments as $assessmentIdx => $assessment) {
                $assessment_name = htmlspecialchars($assessment['name'] ?? 'Unknown');
                $target = intval($assessment['target'] ?? 60);
                $met = intval($assessment['met'] ?? 0);
                $total = intval($assessment['total'] ?? 0);
                $success_rate = floatval($assessment['success_rate'] ?? 0);
                
                // Evaluation logic
                if ($success_rate >= 80) {
                    $evaluation = 'Passed';
                    $recommendation = 'Encourage students to think critically and analyze problems from different perspectives. Help them develop deeper logical reasoning skills and apply concepts learned to real-world scenarios.';
                } elseif ($success_rate >= 60) {
                    $evaluation = 'Partially Met';
                    $recommendation = 'Incorporate collaborative exercises where students can work together in pairs or small groups to solve counting problems. Foster discussion, idea-sharing, and learning. Learn from different perspective approaches.';
                } else {
                    $evaluation = 'Not Met';
                    $recommendation = 'Require real-world scenarios of project-based applications where students can see the relevance and applicability of concepts learned. Combine theory and practical knowledge.';
                }
                
                if ($assessmentIdx === 0) {
                    // First assessment of this CO - show CO description with rowspan
                    $html .= '<tr>'
                        . '<td rowspan="' . $numAssessments . '" style="text-align:left; font-size:8.5pt; vertical-align:top; padding:6px 4px; border-right:2px solid #000;"><strong>CO' . $co_num . '</strong><br/>' . $co_desc . '</td>'
                        . '<td style="text-align:left; font-size:9pt;">' . $assessment_name . '</td>'
                        . '<td style="text-align:center; font-size:9pt;">' . $target . '</td>'
                        . '<td style="text-align:center; font-size:9pt;">' . $section_code . '</td>'
                        . '<td style="text-align:center; font-size:9pt;">' . $total . '</td>'
                        . '<td style="text-align:center; font-size:9pt;">' . number_format($success_rate, 2) . '</td>'
                        . '<td style="text-align:center; font-size:9pt;">' . $evaluation . '</td>'
                        . '<td style="text-align:left; font-size:7.5pt; line-height:1.2;">' . htmlspecialchars($recommendation) . '</td>'
                        . '</tr>';
                } else {
                    // Subsequent assessments - still show assessment name but no CO column
                    $html .= '<tr>'
                        . '<td style="text-align:left; font-size:9pt;">' . $assessment_name . '</td>'
                        . '<td style="text-align:center; font-size:9pt;">' . $target . '</td>'
                        . '<td style="text-align:center; font-size:9pt;">' . $section_code . '</td>'
                        . '<td style="text-align:center; font-size:9pt;">' . $total . '</td>'
                        . '<td style="text-align:center; font-size:9pt;">' . number_format($success_rate, 2) . '</td>'
                        . '<td style="text-align:center; font-size:9pt;">' . $evaluation . '</td>'
                        . '<td style="text-align:left; font-size:7.5pt; line-height:1.2;">' . htmlspecialchars($recommendation) . '</td>'
                        . '</tr>';
                }
            }
        }
    }

    $html .= '</table>';
    
    $html .= '</body>'
        . '</html>';

    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['success' => true, 'html' => $html, 'class_code' => $class_code], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Exception $e) {
    log_debug('Exception caught: ' . $e->getMessage());
    if (ob_get_level()) { ob_end_clean(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
