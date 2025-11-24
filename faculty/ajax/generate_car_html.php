<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
/**
 * Generate CAR as HTML (clean rebuild)
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/db.php';
require_once '../../config/encryption.php';

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
    $gradeDistQuery = "SELECT CASE WHEN tg.grade_status='incomplete' THEN 'INC' WHEN tg.grade_status='dropped' THEN 'DRP' WHEN tg.grade_status='repeat' THEN 'R' WHEN tg.grade_status='failed' THEN 'FAILED' WHEN tg.grade_status='passed' AND CAST(tg.term_grade AS DECIMAL(10,1))=4.0 THEN '4.00' WHEN tg.grade_status='passed' AND CAST(tg.term_grade AS DECIMAL(10,1))=3.5 THEN '3.50' WHEN tg.grade_status='passed' AND CAST(tg.term_grade AS DECIMAL(10,1))=3.0 THEN '3.00' WHEN tg.grade_status='passed' AND CAST(tg.term_grade AS DECIMAL(10,1))=2.5 THEN '2.50' WHEN tg.grade_status='passed' AND CAST(tg.term_grade AS DECIMAL(10,1))=2.0 THEN '2.00' WHEN tg.grade_status='passed' AND CAST(tg.term_grade AS DECIMAL(10,1))=1.5 THEN '1.50' WHEN tg.grade_status='passed' AND CAST(tg.term_grade AS DECIMAL(10,1))=1.0 THEN '1.00' ELSE 'IP' END AS grade, COUNT(DISTINCT tg.student_id) AS count FROM grade_term tg WHERE tg.class_code=? GROUP BY grade ORDER BY FIELD(grade,'4.00','3.50','3.00','2.50','2.00','1.50','1.00','INC','DRP','R','FAILED','IP')";
    $stmt = $conn->prepare($gradeDistQuery); $stmt->bind_param("s", $class_code); $stmt->execute(); $gradeDist=[]; $res=$stmt->get_result(); while($row=$res->fetch_assoc()){ $gradeDist[$row['grade']]=$row['count']; } $stmt->close();

    // CO Performance
    $coPerfQuery = "SELECT co.co_number, co.co_description, gc.component_name AS assessment_name, gcc.performance_target, COUNT(DISTINCT CASE WHEN (CAST(sfg.raw_score AS DECIMAL(10,2))/gcc.max_score*100)>=gcc.performance_target THEN ce.student_id END) AS students_met_target, COUNT(DISTINCT ce.student_id) AS total_students, IFNULL(ROUND((COUNT(DISTINCT CASE WHEN (CAST(sfg.raw_score AS DECIMAL(10,2))/gcc.max_score*100)>=gcc.performance_target THEN ce.student_id END)*100.0/NULLIF(COUNT(DISTINCT ce.student_id),0)),2),0) AS success_rate FROM grading_components gc JOIN grading_component_columns gcc ON gc.id=gcc.component_id JOIN class_enrollments ce ON gc.class_code=ce.class_code AND ce.status='enrolled' LEFT JOIN student_flexible_grades sfg ON gcc.id=sfg.column_id AND ce.student_id=sfg.student_id LEFT JOIN course_outcomes co ON (gcc.co_mappings IS NOT NULL AND JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))) WHERE gc.class_code=? AND gcc.is_summative='yes' AND co.co_number IS NOT NULL GROUP BY co.co_id, co.co_number, co.co_description, gc.id, gc.component_name, gcc.performance_target ORDER BY co.co_number, gc.id";
    $stmt = $conn->prepare($coPerfQuery); $stmt->bind_param("s", $class_code); $stmt->execute(); $coPerf=[]; $res=$stmt->get_result(); while($row=$res->fetch_assoc()){ $coPerf[]=$row; } $stmt->close();

    // Course Outcomes
    $stmt = $conn->prepare("SELECT * FROM course_outcomes WHERE course_code = ? ORDER BY co_number"); $stmt->bind_param("s", $class['course_code']); $stmt->execute(); $courseOutcomes=[]; $res=$stmt->get_result(); while($row=$res->fetch_assoc()){ $courseOutcomes[]=$row; } $stmt->close();

    // CO-SO Mappings from co_so_mapping table
    $coSoMappings = [];
    $stmt = $conn->prepare("SELECT csm.co_id, csm.so_number FROM co_so_mapping csm JOIN course_outcomes co ON csm.co_id = co.co_id WHERE co.course_code = ?");
    if ($stmt) { 
        $stmt->bind_param("s", $class['course_code']); 
        $stmt->execute(); 
        $res = $stmt->get_result(); 
        while($row = $res->fetch_assoc()) { 
            $coId = $row['co_id'];
            $soNum = $row['so_number'];
            if (!isset($coSoMappings[$coId])) {
                $coSoMappings[$coId] = [];
            }
            $coSoMappings[$coId][] = 'SO' . $soNum;
        } 
        $stmt->close(); 
    }

    // INC / DROPPED Students
    $incStudents = [];
    $stmt = $conn->prepare("SELECT s.student_id, s.last_name, s.first_name, tg.grade_status, tg.lacking_requirements FROM class_enrollments ce JOIN student s ON ce.student_id=s.student_id JOIN grade_term tg ON tg.class_code=ce.class_code AND tg.student_id=s.student_id WHERE ce.class_code=? AND ce.status='enrolled' AND tg.grade_status IN ('incomplete','dropped') ORDER BY s.last_name, s.first_name");
    if ($stmt) {
        $stmt->bind_param("s", $class_code);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) {
            $lastName = trim($row['last_name'] ?? '');
            $firstName = trim($row['first_name'] ?? '');
            // Decrypt names
            try {
                if (!empty($lastName)) {
                    $lastName = Encryption::decrypt($lastName);
                }
                if (!empty($firstName)) {
                    $firstName = Encryption::decrypt($firstName);
                }
            } catch (Exception $e) {
                // If decryption fails, use as-is
            }
            $row['name'] = $firstName . ', ' . $lastName;
            $incStudents[] = $row;
        }
        $stmt->close();
    }

    // Build Grade Distribution Table
    $gradeRows = '';
    $gradeOrder = ['4.00' => 0, '3.50' => 0, '3.00' => 0, '2.50' => 0, '2.00' => 0, '1.50' => 0, '1.00' => 0, 'IP' => 0, 'INC' => 0, 'R' => 0, 'DRP' => 0, 'FAILED' => 0];
    foreach ($gradeDist as $grade => $count) {
        if (array_key_exists($grade, $gradeOrder)) {
            $gradeOrder[$grade] = $count;
        }
    }
    foreach ($gradeOrder as $grade => $count) {
        $displayGrade = $grade;
        if ($grade === 'IP') $displayGrade = 'IN PROGRESS (IP)';
        if ($grade === 'INC') $displayGrade = 'INCOMPLETE (INC)';
        if ($grade === 'R') $displayGrade = 'REPEAT (R)';
        if ($grade === 'DRP') $displayGrade = 'DROPPED (DR)';
        if ($grade === 'FAILED') $displayGrade = 'FAILED (0.00)';
        $gradeRows .= '<tr><td style="border:1px solid #000;padding:4px">' . $displayGrade . '</td><td style="border:1px solid #000;padding:4px;text-align:center">' . $count . '</td></tr>';
    }

    // Build CO-SO Map Table
    $coMapRows = '';
    $soColumns = ['SO1', 'SO2', 'SO3', 'SO4', 'SO5', 'SO6'];
    // Build header: CO | SO1 | SO2 | SO3 | SO4 | SO5 | SO6 (7 columns total)
    $coMapHeader = '<tr>';
    $coMapHeader .= '<td style="border:1px solid #000;padding:4px;background:#ccc;text-align:center"><strong>CO</strong></td>';
    foreach ($soColumns as $so) {
        $coMapHeader .= '<td style="border:1px solid #000;padding:4px;background:#ccc;text-align:center"><strong>' . $so . '</strong></td>';
    }
    $coMapHeader .= '</tr>';
    
    foreach ($courseOutcomes as $co) {
        $coId = $co['co_id'];
        $coNum = $co['co_number'];
        $coMapRows .= '<tr>';
        $coMapRows .= '<td style="border:1px solid #000;padding:4px;text-align:center">' . $coNum . '</td>';
        // Get mapped SOs for this CO from the co_so_mapping table
        $mappedSOs = isset($coSoMappings[$coId]) ? $coSoMappings[$coId] : [];
        foreach ($soColumns as $so) {
            // Only show "/" if this SO is mapped to this CO
            $mark = in_array($so, $mappedSOs) ? '/' : '';
            $coMapRows .= '<td style="border:1px solid #000;padding:4px;text-align:center">' . $mark . '</td>';
        }
        $coMapRows .= '</tr>';
    }

    // Student Outcomes Text
    $soText = '';
    if ($subject && isset($subject['student_outcomes'])) {
        $soData = json_decode($subject['student_outcomes'], true);
        if (is_array($soData)) {
            foreach ($soData as $idx => $soDesc) {
                $soNum = $idx + 1;
                $soText .= $soNum . '. ' . htmlspecialchars($soDesc) . "\n";
            }
        }
    }

    // Build CO Performance Table for Page 2
    $coPerfRows = '';
    foreach ($courseOutcomes as $co) {
        $coNum = $co['co_number'];
        $coDesc = htmlspecialchars($co['co_description']);
        $coDesc = preg_replace('/^\d+\.\s*/', '', $coDesc);
        $matching = array_filter($coPerf, function($p) use ($coNum) { return $p['co_number'] == $coNum; });
        if (count($matching) > 0) {
            $assessments = array_column($matching, 'assessment_name');
            $targets = array_column($matching, 'performance_target');
            $successRates = array_column($matching, 'success_rate');
            $avgSuccess = count($successRates) > 0 ? array_sum($successRates) / count($successRates) : 0;
            
            $coPerfRows .= '<tr>';
            $coPerfRows .= '<td style="border:1px solid #000;padding:4px;text-align:center">CO' . $coNum . '</td>';
            $coPerfRows .= '<td style="border:1px solid #000;padding:4px">' . $coDesc . '</td>';
            $coPerfRows .= '<td style="border:1px solid #000;padding:4px;text-align:center">' . (count($targets) > 0 ? $targets[0] : 60) . '%</td>';
            $coPerfRows .= '<td style="border:1px solid #000;padding:4px;text-align:center">' . round($avgSuccess) . '%(' . ($matching[0]['students_met_target'] ?? 0) . ')</td>';
            $coPerfRows .= '</tr>';
        }
    }

    // INC/Dropped Students for Page 2
    $incStudentRows = '';
    foreach ($incStudents as $s) {
        $status = ucfirst($s['grade_status']);
        $lacking = $s['lacking_requirements'] ?? ($status === 'Dropped' ? 'Officially Dropped' : '');
        $incStudentRows .= '<tr><td style="border:1px solid #000;padding:4px">' . htmlspecialchars($s['name']) . '</td><td style="border:1px solid #000;padding:4px">' . htmlspecialchars($lacking) . '</td></tr>';
    }

    // Intervention count
    $interventionCount = $metadata['intervention_student_count'] ?? count($incStudents);

    // HTML build
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>CAR - ' . htmlspecialchars($class_code) . '</title>'
        . '<style>@page { size:A4 landscape; margin:15mm;} '
        . 'body{font-family:Calibri,Arial,sans-serif;font-size:10pt;line-height:1.15;margin:0;padding:0;} '
        . '.page{page-break-after:always;padding:10px;} .page:last-child{page-break-after:avoid;}'
        . '.header-container{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}'
        . '.logo-section{display:flex;align-items:center;gap:10px;}'
        . '.header-info{text-align:right;}'
        . '.header-title{font-weight:bold;font-size:11pt;}'
        . 'table{border-collapse:collapse;width:100%;font-size:10pt;} '
        . 'th,td{border:1px solid #000;padding:5px;vertical-align:top;}'
        . '.gray-header{background:#ccc;font-weight:bold;}'
        . '.footer{position:absolute;bottom:10px;right:10px;font-size:9pt;}'
        . '</style></head><body>';

    // PAGE 1
    $html .= '<div class="page">';
    $html .= '<div class="header-container">'
        . '<div class="logo-section">'
        . '<img src="/faculty/assets/images/nu_logo.png" style="width:60px;height:63px">'
        . '<span style="font-weight:bold;font-size:16pt;color:#003087">NU LIPA</span>'
        . '</div>'
        . '<div class="header-info">'
        . '<div class="header-title">COURSE ASSESSMENT REPORT</div>'
        . '<div>AD-FO-01</div>'
        . '<div>OCT 2022</div>'
        . '</div></div>';

    $html .= '<table>'
        . '<tr><td class="gray-header" style="width:25%">ACADEMIC TERM/ SCHOOL YEAR:</td><td class="gray-header" style="width:25%">COURSE CODE:</td><td class="gray-header" colspan="3" style="width:50%">COPIES ISSUED TO:</td></tr>'
        . '<tr><td style="text-align:center"><strong>' . strtoupper($class['term'] ?? '') . ', AY ' . htmlspecialchars($class['academic_year'] ?? '') . '</strong></td><td style="text-align:center"><strong>' . htmlspecialchars($subject['course_code'] ?? '') . '</strong></td><td colspan="3" rowspan="3" style="padding:10px;vertical-align:top"><strong>PROGRAM CHAIR<br>ACADEMIC DIRECTOR</strong></td></tr>'
        . '<tr><td class="gray-header" style="width:25%">SECTION</td><td class="gray-header" style="width:25%">CLASS SIZE</td></tr>'
        . '<tr><td style="text-align:center"><strong>' . htmlspecialchars($class['section'] ?? '') . '</strong></td><td style="text-align:center"><strong>' . $studentCount . '</strong></td></tr>'
        . '<tr><td class="gray-header">COURSE TITLE:</td><td class="gray-header" colspan="4">COURSE DESCRIPTION:</td></tr>'
        . '<tr><td style="text-align:center"><strong>' . htmlspecialchars($subject['course_title'] ?? '') . '</strong></td><td colspan="4" rowspan="5" style="padding:10px;vertical-align:top">' . nl2br(htmlspecialchars($subject['course_desc'] ?? '')) . '</td></tr>'
        . '<tr><td class="gray-header">INSTRUCTOR:</td></tr>'
        . '<tr><td><strong>' . htmlspecialchars($faculty['name'] ?? '') . '</strong></td></tr>'
        . '<tr><td class="gray-header">SIGNATURE:</td></tr>'
        . '<tr><td style="padding:20px;vertical-align:top">&nbsp;</td></tr>'
        . '</table>';

    $html .= '<table style="margin-top:10px"><tr><td style="width:40%" class="gray-header">GRADE DISTRIBUTION:</td><td class="gray-header">CO-SO MAP</td></tr></table>';
    $html .= '<table style="margin-top:0;border-top:none"><tr><td style="width:40%;vertical-align:top;border-top:none">';
    $html .= '<table style="width:100%"><tr><td class="gray-header">GRADE</td><td class="gray-header">NO. OF STUDENTS</td></tr>' . $gradeRows . '</table>';
    $html .= '</td><td style="vertical-align:top;border-top:none">';
    $html .= '<table style="width:100%">' . $coMapHeader . $coMapRows . '</table>';
    $html .= '<div style="margin-top:10px;font-size:9pt;line-height:1.3"><strong>Student Outcomes (SO)</strong><br>';
    foreach (range(1, 6) as $i) {
        $soDesc = '';
        if ($subject && isset($subject['student_outcomes'])) {
            $soData = json_decode($subject['student_outcomes'], true);
            if (is_array($soData) && isset($soData[$i-1])) {
                $soDesc = $soData[$i-1];
            }
        }
        if (empty($soDesc)) {
            $defaultSO = [
                'Analyze a complex computing problem and to apply principles of computing and other relevant disciplines to identify solutions.',
                'Design, implement, and evaluate a computing-based solution to meet a given set of computing requirements in the context of the programs.',
                'Communicate effectively in a variety of professional contexts.',
                'Recognize professional responsibilities and make informed judgements in computing practice based on legal and ethical principles.',
                'Function effectively as a member or leader of a team engaged in activities appropriate to the program\'s discipline.',
                'Apply computer science theory and software development fundamentals to produce computing-based solutions'
            ];
            $soDesc = $defaultSO[$i-1] ?? '';
        }
        $html .= $i . '. ' . htmlspecialchars($soDesc) . '<br>';
    }
    $html .= '</div>';
    $html .= '<div style="margin-top:8px;font-size:9pt;line-height:1.3"><strong>Course Outcomes (CO)</strong><br>';
    foreach ($courseOutcomes as $co) {
        $desc = htmlspecialchars($co['co_description']);
        $desc = preg_replace('/^\d+\.\s*/', '', $desc);
        $html .= $co['co_number'] . '. ' . $desc . '<br>';
    }
    $html .= '</div></td></tr></table>';
    $html .= '<div class="footer">Page 1 of 3</div></div>';

    // PAGE 2
    $html .= '<div class="page">';
    $html .= '<div class="header-container">'
        . '<div class="logo-section">'
        . '<img src="/faculty/assets/images/nu_logo.png" style="width:60px;height:63px">'
        . '<span style="font-weight:bold;font-size:16pt;color:#003087">NU LIPA</span>'
        . '</div>'
        . '<div class="header-info">'
        . '<div class="header-title">COURSE ASSESSMENT REPORT</div>'
        . '<div>AD-FO-01</div>'
        . '<div>OCT 2022</div>'
        . '</div></div>';

    $html .= '<table><tr><td class="gray-header" colspan="4">COURSE LEARNING OUTCOMES ASSESSMENT</td></tr>'
        . '<tr><td class="gray-header">COURSE<br>OUTCOME</td><td class="gray-header">SUMMATIVE ASSESSMENT</td><td class="gray-header">PERFORMANCE TARGET</td><td class="gray-header">SUCCESS RATE<br>(in % and enclose the number in ( ))</td></tr>'
        . $coPerfRows . '</table>';

    $html .= '<table style="margin-top:10px"><tr><td class="gray-header" colspan="2">List of Students with INC</td></tr>'
        . '<tr><td class="gray-header">Name</td><td class="gray-header">Lacking Requirements</td></tr>'
        . ($incStudentRows ? $incStudentRows : '<tr><td colspan="2" style="text-align:center">None</td></tr>')
        . '</table>';

    $html .= '<table style="margin-top:10px"><tr><td class="gray-header" colspan="2">Teaching Strategies Employed <span style="font-weight:normal">(List and give a brief description of each teaching strategy employed in class)</span></td></tr>'
        . '<tr><td colspan="2" style="padding:10px;min-height:80px">' . nl2br(htmlspecialchars($metadata['teaching_strategies'] ?? '')) . '</td></tr></table>';

    $html .= '<table style="margin-top:10px"><tr><td class="gray-header" colspan="2">Intervention or Enrichment Activities Conducted</td><td class="gray-header">No. of Students Involved</td></tr>'
        . '<tr><td colspan="2" style="padding:10px;min-height:60px">' . nl2br(htmlspecialchars($metadata['interventions'] ?? '')) . '</td><td style="text-align:center;vertical-align:top">' . $interventionCount . '</td></tr></table>';

    $html .= '<table style="margin-top:10px"><tr><td class="gray-header" style="width:50%">Problems Encountered <span style="font-weight:normal">(Brief Description)</span></td><td class="gray-header" style="width:50%">Action Taken</td></tr>'
        . '<tr><td style="padding:10px;min-height:80px;vertical-align:top">' . nl2br(htmlspecialchars($metadata['problems_encountered'] ?? '')) . '</td><td style="padding:10px;vertical-align:top">' . nl2br(htmlspecialchars($metadata['actions_taken'] ?? '')) . '</td></tr></table>';

    $html .= '<div class="footer">Page 2 of 3</div></div>';

    // PAGE 3
    $html .= '<div class="page">';
    $html .= '<div class="header-container">'
        . '<div class="logo-section">'
        . '<img src="/faculty/assets/images/nu_logo.png" style="width:60px;height:63px">'
        . '<span style="font-weight:bold;font-size:16pt;color:#003087">NU LIPA</span>'
        . '</div>'
        . '<div class="header-info">'
        . '<div class="header-title">COURSE ASSESSMENT REPORT</div>'
        . '<div>AD-FO-01</div>'
        . '<div>OCT 2022</div>'
        . '</div></div>';

    $html .= '<table><tr><td class="gray-header">PROPOSED ACTIONS FOR COURSE IMPROVEMENT</td></tr>'
        . '<tr><td style="padding:20px;min-height:500px;vertical-align:top">' . nl2br(htmlspecialchars($metadata['proposed_improvements'] ?? '––')) . '</td></tr></table>';

    $html .= '<div class="footer">Page 3 of 3</div></div>';

    $html .= '</body></html>';
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['success'=>true,'html'=>$html,'class_code'=>$class_code], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Exception $e) {
    if (ob_get_level()) { ob_end_clean(); }
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}