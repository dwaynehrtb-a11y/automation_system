<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
/**
 * Generate CAR as HTML (clean rebuild)
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$faculty_id = $_SESSION['user_id'];
$class_code = $_GET['class_code'] ?? '';
if (empty($class_code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Class code required']);
    exit;
}

try {
    // Class ownership
    $stmt = $conn->prepare("SELECT * FROM class WHERE class_code = ? AND faculty_id = ? LIMIT 1");
    $stmt->bind_param("si", $class_code, $faculty_id);
    $stmt->execute(); $class = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$class) { throw new Exception('Class not found'); }

    // Subject
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE course_code = ? LIMIT 1");
    $stmt->bind_param("s", $class['course_code']);
    $stmt->execute(); $subject = $stmt->get_result()->fetch_assoc(); $stmt->close();

    // Faculty
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute(); $faculty = $stmt->get_result()->fetch_assoc(); $stmt->close();

    // Class size
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM class_enrollments WHERE class_code = ? AND status='enrolled'");
    $stmt->bind_param("s", $class_code);
    $stmt->execute(); $studentCount = $stmt->get_result()->fetch_assoc()['total']; $stmt->close();

    // Metadata & CAR Data
    $metadata = null;
    $stmt = $conn->prepare("SELECT * FROM car_metadata WHERE class_code = ? LIMIT 1");
    if ($stmt) { $stmt->bind_param("s", $class_code); $stmt->execute(); $r=$stmt->get_result(); if($r){ $metadata=$r->fetch_assoc(); } $stmt->close(); }
    if (!$metadata) {
        $stmt = $conn->prepare("SELECT teaching_strategies, intervention_activities, problems_encountered, actions_taken, proposed_actions, status FROM car_data WHERE class_id = ? LIMIT 1");
        if ($stmt) { $stmt->bind_param("i", $class['class_id']); $stmt->execute(); $r=$stmt->get_result(); if($r){ $metadata=$r->fetch_assoc(); } $stmt->close(); }
    }
    if ($metadata) {
        if (isset($metadata['intervention_activities']) && !isset($metadata['interventions'])) {
            $acts = json_decode($metadata['intervention_activities'], true); if(is_array($acts)){ $t=''; foreach($acts as $a){ if(isset($a['description'])) $t.=$a['description']."\n"; } $metadata['interventions']=$t; }
        }
        if (isset($metadata['proposed_actions']) && !isset($metadata['proposed_improvements'])) { $metadata['proposed_improvements']=$metadata['proposed_actions']; }
    }

    // Grade distribution
    $gradeDistQuery = "SELECT CASE WHEN tg.grade_status='incomplete' THEN 'INC' WHEN tg.grade_status='dropped' THEN 'DRP' WHEN tg.grade_status='repeat' THEN 'R' WHEN tg.grade_status='failed' THEN 'FAILED' WHEN tg.grade_status='passed' AND tg.term_grade=4.0 THEN '4.00' WHEN tg.grade_status='passed' AND tg.term_grade=3.5 THEN '3.50' WHEN tg.grade_status='passed' AND tg.term_grade=3.0 THEN '3.00' WHEN tg.grade_status='passed' AND tg.term_grade=2.5 THEN '2.50' WHEN tg.grade_status='passed' AND tg.term_grade=2.0 THEN '2.00' WHEN tg.grade_status='passed' AND tg.term_grade=1.5 THEN '1.50' WHEN tg.grade_status='passed' AND tg.term_grade=1.0 THEN '1.00' ELSE 'IP' END AS grade, COUNT(*) AS count FROM grade_term tg WHERE tg.class_code=? GROUP BY grade ORDER BY FIELD(grade,'4.00','3.50','3.00','2.50','2.00','1.50','1.00','INC','DRP','R','FAILED','IP')";
    $stmt = $conn->prepare($gradeDistQuery); $stmt->bind_param("s", $class_code); $stmt->execute(); $gradeDist=[]; $res=$stmt->get_result(); while($row=$res->fetch_assoc()){ $gradeDist[$row['grade']]=$row['count']; } $stmt->close();

    // CO Performance
    $coPerfQuery = "SELECT co.co_number, co.co_description, gc.component_name AS assessment_name, gcc.performance_target, COUNT(DISTINCT CASE WHEN (CAST(sfg.raw_score AS DECIMAL(10,2))/gcc.max_score*100)>=gcc.performance_target THEN ce.student_id END) AS students_met_target, COUNT(DISTINCT ce.student_id) AS total_students, IFNULL(ROUND((COUNT(DISTINCT CASE WHEN (CAST(sfg.raw_score AS DECIMAL(10,2))/gcc.max_score*100)>=gcc.performance_target THEN ce.student_id END)*100.0/NULLIF(COUNT(DISTINCT ce.student_id),0)),2),0) AS success_rate FROM grading_components gc JOIN grading_component_columns gcc ON gc.id=gcc.component_id JOIN class_enrollments ce ON gc.class_code=ce.class_code AND ce.status='enrolled' LEFT JOIN student_flexible_grades sfg ON gcc.id=sfg.column_id AND ce.student_id=sfg.student_id LEFT JOIN course_outcomes co ON (gcc.co_mappings IS NOT NULL AND JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))) WHERE gc.class_code=? AND gcc.is_summative='yes' AND co.co_number IS NOT NULL GROUP BY co.co_id, co.co_number, co.co_description, gc.id, gc.component_name, gcc.performance_target ORDER BY co.co_number, gc.id";
    $stmt = $conn->prepare($coPerfQuery); $stmt->bind_param("s", $class_code); $stmt->execute(); $coPerf=[]; $res=$stmt->get_result(); while($row=$res->fetch_assoc()){ $coPerf[]=$row; } $stmt->close();

    // Course Outcomes
    $stmt = $conn->prepare("SELECT * FROM course_outcomes WHERE course_code = ? ORDER BY co_number"); $stmt->bind_param("s", $class['course_code']); $stmt->execute(); $courseOutcomes=[]; $res=$stmt->get_result(); while($row=$res->fetch_assoc()){ $courseOutcomes[]=$row; } $stmt->close();

    // INC / DROPPED Students
    $stmt = $conn->prepare("SELECT s.student_id, CONCAT(s.last_name, ', ', s.first_name) AS name, tg.grade_status, tg.lacking_requirements FROM class_enrollments ce JOIN student s ON ce.student_id=s.student_id JOIN grade_term tg ON tg.class_code=ce.class_code AND tg.student_id=s.student_id WHERE ce.class_code=? AND ce.status='enrolled' AND tg.grade_status IN ('incomplete','dropped') ORDER BY s.last_name, s.first_name");
    $stmt->bind_param("s", $class_code); $stmt->execute(); $incStudents=[]; $res=$stmt->get_result(); while($row=$res->fetch_assoc()){ $incStudents[]=$row; } $stmt->close();

    // HTML build
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>CAR - ' . htmlspecialchars($class_code) . '</title>'
        . '<style>@page { size:A4 landscape; margin:20.64mm 25.4mm 26.99mm 25.4mm;} @page :first{margin-top:12.7mm;} @page :last{margin-bottom:12.7mm;}'
        . 'body{font-family:Calibri,Arial,sans-serif;font-size:9pt;line-height:1.0;margin:0;} .page{page-break-after:always;} .page:last-child{page-break-after:avoid;}'
        . '.header-container{width:100%;display:table;margin-bottom:15px;} .logo-section{display:table-cell;width:10%;vertical-align:middle;} .header-info{display:table-cell;width:90%;text-align:right;vertical-align:middle;}'
        . '.header-title{font-weight:bold;font-size:11pt;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #000;padding:6px 5px;}'
        . '.shade1{background:#A5A5A5;font-weight:bold;} .shade2{background:#AEAAAA;font-weight:bold;} .footer{text-align:right;font-size:8pt;margin-top:8px;border-top:1px solid #000;padding-top:4px;}'
        . '</style></head><body>';

    // Page 1
    $html .= '<div class="page"><div class="header-container"><div class="logo-section"><div style="display:flex;align-items:center;gap:10px"><img src="/automation_system/assets/images/nu_logo.png" style="width:77px;height:81px;object-fit:contain"><span style="font-weight:bold;font-size:18pt;letter-spacing:.5px;white-space:nowrap">NU LIPA</span></div></div><div class="header-info"><div class="header-title">COURSE ASSESSMENT REPORT</div><div>AD-FO-01</div><div>OCT 2022</div></div></div>'
        . '<table style="width:23.2cm;border-collapse:collapse;margin:15px 0;font-size:11pt;font-family:Calibri">
        <colgroup>
            <col style="width:5.8cm">
            <col style="width:5.8cm">
            <col style="width:11.6cm">
        </colgroup>
        <!-- Row 1: Header -->
        <tr>
            <td style="border:1px solid #000;padding:6px;background-color:#ccc;vertical-align:middle"><strong>ACADEMIC TERM/ SCHOOL YEAR:</strong></td>
            <td style="border:1px solid #000;padding:6px;background-color:#ccc;vertical-align:middle"><strong>COURSE CODE:</strong></td>
            <td style="border:1px solid #000;padding:6px;background-color:#ccc;vertical-align:middle"><strong>COPIES ISSUED TO:</strong></td>
        </tr>
        <!-- Row 2: Term/AY and Copies -->
        <tr>
            <td colspan="2" style="border:1px solid #000;padding:6px;text-align:center;vertical-align:middle"><strong>' . htmlspecialchars($class['term'] ?? 'N/A') . ', AY ' . htmlspecialchars($class['academic_year'] ?? 'N/A') . '</strong></td>
            <td style="border:1px solid #000;padding:6px;text-align:left;vertical-align:middle">
                <div><strong>PROGRAM</strong></div>
                <div><strong>CHAIR</strong></div>
                <div><strong>ACADEMIC</strong></div>
                <div><strong>DIRECTOR</strong></div>
            </td>
        </tr>
        <!-- Row 3: Course Code and Title -->
        <tr>
            <td colspan="2" style="border:1px solid #000;padding:6px;text-align:center;vertical-align:middle"><strong>' . htmlspecialchars($subject['course_code'] ?? 'N/A') . '</strong></td>
            <td style="border:1px solid #000;padding:6px;text-align:center;vertical-align:middle"><strong>' . htmlspecialchars($subject['course_title'] ?? 'N/A') . '</strong></td>
        </tr>
        <!-- Row 4: Section/Class Size Header and Course Description Header -->
        <tr>
            <td style="border:1px solid #000;padding:6px;background-color:#ccc;vertical-align:middle"><strong>SECTION</strong></td>
            <td style="border:1px solid #000;padding:6px;background-color:#ccc;vertical-align:middle"><strong>CLASS SIZE</strong></td>
            <td style="border:1px solid #000;padding:6px;background-color:#ccc;vertical-align:middle"><strong>COURSE DESCRIPTION:</strong></td>
        </tr>
        <!-- Row 5: Section/Class Size Data and Description -->
        <tr>
            <td style="border:1px solid #000;padding:6px;text-align:center;vertical-align:middle"><strong>' . htmlspecialchars($class['section'] ?? 'N/A') . '</strong></td>
            <td style="border:1px solid #000;padding:6px;text-align:center;vertical-align:middle"><strong>' . htmlspecialchars($studentCount ?? 'N/A') . '</strong></td>
            <td rowspan="3" style="border:1px solid #000;padding:6px;text-align:left;vertical-align:top">' . htmlspecialchars($subject['course_desc'] ?? 'N/A') . '</td>
        </tr>
        <!-- Row 6: Instructor Label -->
        <tr>
            <td colspan="2" style="border:1px solid #000;padding:6px;text-align:left;vertical-align:middle"><strong>INSTRUCTOR:</strong></td>
        </tr>
        <!-- Row 7: Instructor Name -->
        <tr>
            <td colspan="2" style="border:1px solid #000;padding:6px;text-align:left;vertical-align:middle"><strong>' . htmlspecialchars($faculty['name'] ?? 'N/A') . '</strong></td>
        </tr>
        <!-- Row 8: Signature -->
        <tr>
            <td colspan="2" style="border:1px solid #000;padding:6px;text-align:left;vertical-align:middle"><strong>SIGNATURE:</strong></td>
            <td style="border:1px solid #000;padding:6px;text-align:center;vertical-align:middle">&nbsp;</td>
        </tr>
        <!-- Row 9: Grade Distribution and CO-SO Map -->
        <tr>
            <td style="border:1px solid #000;padding:6px;background-color:#ccc;vertical-align:middle"><strong>GRADE DISTRIBUTION:</strong></td>
            <td colspan="2" style="border:1px solid #000;padding:6px;background-color:#ccc;vertical-align:middle"><strong>CO-SO MAP</strong></td>
        </tr>
        </table></div>';

    // Page 2
    $html .= '<div class="page"><div class="header-container"><div class="logo-section"><div style="display:flex;align-items:center;gap:10px"><img src="/automation_system/assets/images/nu_logo.png" style="width:77px;height:81px;object-fit:contain"><span style="font-weight:bold;font-size:18pt;letter-spacing:.5px">NU LIPA</span></div></div><div class="header-info"><div class="header-title">COURSE ASSESSMENT REPORT</div><div>AD-FO-01</div><div>OCT 2022</div></div></div>';

    $html .= '<p>Content for Page 2 will go here</p></div>';

    // Page 3
    $html .= '<div class="page"><div class="header-container"><div class="logo-section"><div style="display:flex;align-items:center;gap:10px"><img src="/automation_system/assets/images/nu_logo.png" style="width:77px;height:81px;object-fit:contain"><span style="font-weight:bold;font-size:18pt;letter-spacing:.5px">NU LIPA</span></div></div><div class="header-info"><div class="header-title">COURSE ASSESSMENT REPORT</div><div>AD-FO-01</div><div>OCT 2022</div></div></div>';

    $html .= '<p>Content for Page 3 will go here</p></div>';

    $html .= '</body></html>';
    $html = preg_replace('/>\s+</', ">\n<", $html);
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['success'=>true,'html'=>$html,'class_code'=>$class_code], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Exception $e) {
    if (ob_get_level()) { ob_end_clean(); }
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}