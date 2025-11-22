<?php
// compute_term_grades.php
// Canonical grade computation endpoint

if (session_status() === PHP_SESSION_NONE) { session_start(); }
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../config/encryption.php';
require_once '../../config/helpers.php';
require_once '../../config/middleware.php';
require_once '../../config/error_handler.php';
require_once '../../config/decryption_access.php';
require_once '../../includes/GradesModel.php';
require_once '../../config/audit_logger.php';

// Security & auth
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$faculty_id = $_SESSION['user_id'];
$class_code = $_POST['class_code'] ?? '';
if ($class_code === '') { echo json_encode(['success' => false, 'message' => 'Class code required']); exit(); }

$startTime = microtime(true);

try {
    // Verify ownership
    $own = $conn->prepare("SELECT class_id FROM class WHERE class_code=? AND faculty_id=? LIMIT 1");
    $own->bind_param('si', $class_code, $faculty_id);
    $own->execute();
    if ($own->get_result()->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'Class not found or access denied']); exit(); }
    $own->close();

    // Get enrolled students
    $stStmt = $conn->prepare("SELECT s.student_id FROM class_enrollments ce JOIN student s ON ce.student_id=s.student_id WHERE ce.class_code=? AND ce.status='enrolled' ORDER BY s.student_id");
    $stStmt->bind_param('s', $class_code);
    $stStmt->execute();
    $studentsRes = $stStmt->get_result();
    $students = [];
    while ($r = $studentsRes->fetch_assoc()) { $students[] = $r['student_id']; }
    $stStmt->close();

    // Term weights
    $wStmt = $conn->prepare("SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code=? LIMIT 1");
    $wStmt->bind_param('s', $class_code);
    $wStmt->execute();
    $wRow = $wStmt->get_result()->fetch_assoc();
    $wStmt->close();
    $midtermWeight = $wRow ? floatval($wRow['midterm_weight']) : 40.0;
    $finalsWeight = $wRow ? floatval($wRow['finals_weight']) : 60.0;

    // Components + columns
    $compStmt = $conn->prepare("SELECT id, component_name, percentage, term_type FROM grading_components WHERE class_code=? ORDER BY term_type, order_index, id");
    $compStmt->bind_param('s',$class_code);
    $compStmt->execute();
    $compRes = $compStmt->get_result();
    $components = [];
    while ($c = $compRes->fetch_assoc()) { $components[] = $c; }
    $compStmt->close();

    // Fetch columns per component
    $colsStmt = $conn->prepare("SELECT gcc.id, gcc.component_id, gcc.max_score FROM grading_component_columns gcc JOIN grading_components gc ON gcc.component_id=gc.id WHERE gc.class_code=?");
    $colsStmt->bind_param('s',$class_code);
    $colsStmt->execute();
    $colsRes = $colsStmt->get_result();
    $columnsByComponent = [];
    while ($col = $colsRes->fetch_assoc()) { $columnsByComponent[$col['component_id']][] = $col; }
    $colsStmt->close();

    // Pre-fetch grades (scores)
    $gradesStmt = $conn->prepare("SELECT g.student_id, g.column_id, g.raw_score, gcc.component_id FROM student_flexible_grades g JOIN grading_component_columns gcc ON g.column_id=gcc.id WHERE g.class_code=?");
    $gradesStmt->bind_param('s',$class_code);
    $gradesStmt->execute();
    $gradesRes = $gradesStmt->get_result();
    $scores = [];// scores[student_id][component_id] = ['earned'=>sum,'possible'=>sum]
    while ($gr = $gradesRes->fetch_assoc()) {
        $sid = $gr['student_id']; $cid = $gr['component_id'];
        if (!isset($scores[$sid])) { $scores[$sid] = []; }
        if (!isset($scores[$sid][$cid])) { $scores[$sid][$cid] = ['earned'=>0.0,'possible'=>0.0]; }
        $raw = ($gr['raw_score'] === null ? 0.0 : floatval($gr['raw_score']));
        // find max_score from columnsByComponent
        // We'll accumulate possible later based on columns list (avoid double counting if multiple grade rows per column) -> simpler: add max per row if raw_score not null OR always? We'll add max once per column per student.
        // For simplicity treat missing score (no row) as earned 0 & includes max. Since we only have rows for existing scores, we must add possible separately below.
        $scores[$sid][$cid]['earned'] += $raw;
    }
    $gradesStmt->close();

    // Add possible totals per component per student (every column counts)
    foreach ($students as $sid) {
        if (!isset($scores[$sid])) { $scores[$sid] = []; }
        foreach ($components as $comp) {
            $cid = $comp['id'];
            if (!isset($scores[$sid][$cid])) { $scores[$sid][$cid] = ['earned'=>0.0,'possible'=>0.0]; }
            $possible = 0.0;
            if (isset($columnsByComponent[$cid])) {
                foreach ($columnsByComponent[$cid] as $col) { $possible += floatval($col['max_score']); }
            }
            $scores[$sid][$cid]['possible'] = $possible;
        }
    }

    // Sum component percentages per term (for normalization)
    $midtermComponentsTotal = 0.0; $finalsComponentsTotal = 0.0;
    foreach ($components as $comp) {
        $pct = floatval($comp['percentage']);
        if ($comp['term_type'] === 'midterm') { $midtermComponentsTotal += $pct; }
        elseif ($comp['term_type'] === 'finals') { $finalsComponentsTotal += $pct; }
    }

    $results = [];
    $gm = new GradesModel($conn);

    // Pre-check columns existence for finals to determine midterm-only logic
    $finalsHasAnyColumns = $finalsComponentsTotal > 0; // refined later per student for score presence

    // Optional: detect status_manually_set column
    $hasManualCol = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM grade_term LIKE 'status_manually_set'");
    if ($colCheck && $colCheck->num_rows > 0) { $hasManualCol = true; }

    foreach ($students as $sid) {
        // Build component weighted averages per term
        $midtermWeightedSum = 0.0; $finalsWeightedSum = 0.0;
        $midtermWeightTotal = 0.0; $finalsWeightTotal = 0.0;
        $finalsHasScore = false;

        foreach ($components as $comp) {
            $cid = $comp['id']; $termType = $comp['term_type']; $compPct = floatval($comp['percentage']);
            $earned = $scores[$sid][$cid]['earned']; $possible = $scores[$sid][$cid]['possible'];
            $rawPct = ($possible > 0 ? ($earned / $possible) * 100.0 : 0.0);
            if ($termType === 'midterm') {
                $midtermWeightedSum += $rawPct * $compPct;
                $midtermWeightTotal += $compPct;
            } else {
                $finalsWeightedSum += $rawPct * $compPct;
                $finalsWeightTotal += $compPct;
                if ($earned > 0) { $finalsHasScore = true; }
            }
        }
        // Normalize inside term (silent)
        $midterm_percentage = ($midtermWeightTotal > 0 ? $midtermWeightedSum / $midtermWeightTotal : 0.0);
        $finals_percentage  = ($finalsWeightTotal > 0 ? $finalsWeightedSum / $finalsWeightTotal : 0.0);

        // Existing row for overrides
        $rowStmt = $conn->prepare("SELECT id, grade_status, term_grade, lacking_requirements, is_encrypted".($hasManualCol?", status_manually_set":"")." FROM grade_term WHERE student_id=? AND class_code=? LIMIT 1");
        $rowStmt->bind_param('ss',$sid,$class_code);
        $rowStmt->execute();
        $existingRow = $rowStmt->get_result()->fetch_assoc();
        $rowStmt->close();

        $manualFrozen = $hasManualCol && $existingRow && strtolower($existingRow['status_manually_set']) === 'yes';
        $lackingReq = $existingRow ? $existingRow['lacking_requirements'] : null;

        // Determine midterm-only
        $midtermOnly = (!$finalsHasAnyColumns) || ($finalsWeightTotal == 0) || (!$finalsHasScore);

        // Status logic (lowercase normalization)
        $grade_status = null; $term_grade = null;
        if ($manualFrozen && $existingRow) {
            // Preserve existing frozen status/grade verbatim
            $grade_status = $existingRow['grade_status'];
            $term_grade = $existingRow['term_grade'];
        } else {
            if ($lackingReq === 'yes') {
                $grade_status = 'incomplete';
            } elseif ($midtermOnly) {
                $grade_status = 'incomplete';
            } else {
                // Provisional term percentage pre-calculation
                $term_percentage_prov = ($midterm_percentage * ($midtermWeight/100.0)) + ($finals_percentage * ($finalsWeight/100.0));
                if ($term_percentage_prov < 57.0) { $grade_status = 'failed'; }
                elseif ($term_percentage_prov < 60.0) { $grade_status = 'incomplete'; }
                else { $grade_status = 'passed'; }
            }
        }

        // Compute final term_percentage
        $term_percentage = ($midterm_percentage * ($midtermWeight/100.0)) + ($finals_percentage * ($finalsWeight/100.0));
        // Discrete grade only when passed and not frozen override
        if ($grade_status === 'passed' && !$manualFrozen) {
            if ($term_percentage >= 96.0) $term_grade = '4.0';
            elseif ($term_percentage >= 90.0) $term_grade = '3.5';
            elseif ($term_percentage >= 84.0) $term_grade = '3.0';
            elseif ($term_percentage >= 78.0) $term_grade = '2.5';
            elseif ($term_percentage >= 72.0) $term_grade = '2.0';
            elseif ($term_percentage >= 66.0) $term_grade = '1.5';
            else $term_grade = '1.0';
        } elseif ($manualFrozen && $existingRow) {
            $term_grade = $existingRow['term_grade'];
        } else {
            $term_grade = null; // incomplete / failed
        }
        // Safety override: never persist passed below 60
        if ($grade_status === 'passed' && $term_percentage < 60.0) {
            $grade_status = ($term_percentage < 57.0 ? 'failed' : 'incomplete');
            $term_grade = null;
        }

        // Round percentages
        $midtermPctOut = number_format($midterm_percentage,2,'.','');
        $finalsPctOut  = number_format($finals_percentage,2,'.','');
        $termPctOut    = number_format($term_percentage,2,'.','');

        // Persist (encrypt-aware via GradesModel). Build gradeData
        $gradeData = [
            'student_id' => $sid,
            'class_code' => $class_code,
            'midterm_percentage' => $midtermPctOut,
            'finals_percentage' => $finalsPctOut,
            'term_percentage' => $termPctOut,
            'grade_status' => $grade_status,
            'term_grade' => $term_grade,
            'lacking_requirements' => $lackingReq ?? ''
        ];

        // Save (do not alter frozen status/grade)
        if ($manualFrozen) {
            // Overwrite percentages only, keep status/term_grade
            $gradeData['grade_status'] = $existingRow['grade_status'];
            $gradeData['term_grade'] = $existingRow['term_grade'];
        }
        try { $gm->saveGrades($gradeData, $faculty_id); } catch (Exception $e) { /* log but continue */ error_log('Persist error for '.$sid.': '.$e->getMessage()); }

        $results[] = [
            'student_id' => $sid,
            'midterm_percentage' => $midtermPctOut,
            'finals_percentage' => $finalsPctOut,
            'term_percentage' => $termPctOut,
            'grade_status' => $grade_status,
            'term_grade' => $term_grade,
            'midterm_only' => $midtermOnly,
            'manual_frozen' => $manualFrozen,
            'lacking_requirements' => $lackingReq,
        ];
    }

    $durationMs = round((microtime(true) - $startTime)*1000,2);
    // Audit log
    audit_log($faculty_id, 'faculty', 'compute_term_grades', $class_code, ['student_count'=>count($students)], count($results), $durationMs);
    echo json_encode([
        'success' => true,
        'class_code' => $class_code,
        'midterm_weight' => $midtermWeight,
        'finals_weight' => $finalsWeight,
        'component_midterm_total' => $midtermComponentsTotal,
        'component_finals_total' => $finalsComponentsTotal,
        'normalization_midterm_applied' => $midtermWeightTotal>0 && $midtermComponentsTotal>0 && abs($midtermComponentsTotal - $midtermComponentsTotal) < 0.00001 ? false : true,
        'normalization_finals_applied' => $finalsWeightTotal>0 && $finalsComponentsTotal>0 && abs($finalsComponentsTotal - $finalsComponentsTotal) < 0.00001 ? false : true,
        'rows' => $results,
        'duration_ms' => $durationMs
    ]);
    exit();

} catch (Exception $ex) {
    error_log('compute_term_grades error: '.$ex->getMessage());
    echo json_encode(['success'=>false,'message'=>$ex->getMessage()]);
    exit();
}
?>
