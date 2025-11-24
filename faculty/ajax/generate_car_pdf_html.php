<?php
/**
 * Generate CAR as HTML for PDF - Landscape, 3 Pages
 * Uses same structure as generate_car_html.php for consistency
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    // Verify class ownership
    $stmt = $conn->prepare("SELECT * FROM class WHERE class_code = ? AND faculty_id = ? LIMIT 1");
    $stmt->bind_param("si", $class_code, $faculty_id);
    $stmt->execute();
    $class = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$class) {
        throw new Exception('Class not found');
    }

    // Get all required data
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE course_code = ? LIMIT 1");
    $stmt->bind_param("s", $class['course_code']);
    $stmt->execute();
    $subject = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $faculty = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM class_enrollments WHERE class_code = ? AND status = 'enrolled'");
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $studentCount = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Try car_metadata first (from modal), then fallback to car_data (from wizard)
    $metadata = null;

    $stmt = $conn->prepare("SELECT * FROM car_metadata WHERE class_code = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $class_code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $metadata = $result->fetch_assoc();
        }
        $stmt->close();
    }

    // If not found in car_metadata, try car_data table (from wizard)
    if (!$metadata) {
        $stmt = $conn->prepare("
            SELECT 
                teaching_strategies,
                intervention_activities,
                problems_encountered,
                actions_taken,
                proposed_actions,
                status
            FROM car_data 
            WHERE class_id = ? 
            LIMIT 1
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $class['class_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $metadata = $result->fetch_assoc();
            }
            $stmt->close();
        }
    }

    // Normalize field names between car_metadata and car_data
    if ($metadata) {
        // Parse intervention_activities JSON
        if (isset($metadata['intervention_activities']) && !isset($metadata['interventions'])) {
            $activities = json_decode($metadata['intervention_activities'], true);
            if (is_array($activities)) {
                $text = '';
                foreach ($activities as $activity) {
                    if (isset($activity['description'])) {
                        $text .= $activity['description'] . "\n";
                    }
                }
                $metadata['interventions'] = $text;
            }
        }
        
        // Handle proposed_actions
        if (isset($metadata['proposed_actions']) && !isset($metadata['proposed_improvements'])) {
            $metadata['proposed_improvements'] = $metadata['proposed_actions'];
        }
    }

    // Get grade distribution
    $gradeDistQuery = "
    SELECT 
    CASE 
    WHEN tg.grade_status = 'incomplete' THEN 'incomplete'
    WHEN tg.grade_status = 'dropped' THEN 'dropped'
    WHEN tg.grade_status = 'repeat' THEN 'repeat'
    WHEN tg.grade_status = 'failed' THEN 'failed'
    WHEN tg.grade_status = 'passed' AND tg.term_grade = 4.0 THEN '4.00'
    WHEN tg.grade_status = 'passed' AND tg.term_grade = 3.5 THEN '3.50'
    WHEN tg.grade_status = 'passed' AND tg.term_grade = 3.0 THEN '3.00'
    WHEN tg.grade_status = 'passed' AND tg.term_grade = 2.5 THEN '2.50'
    WHEN tg.grade_status = 'passed' AND tg.term_grade = 2.0 THEN '2.00'
    WHEN tg.grade_status = 'passed' AND tg.term_grade = 1.5 THEN '1.50'
    WHEN tg.grade_status = 'passed' AND tg.term_grade = 1.0 THEN '1.00'
    ELSE 'in-progress'
    END as grade,
    COUNT(*) as count
    FROM grade_term tg
    WHERE tg.class_code = ?
    GROUP BY grade
    ORDER BY FIELD(grade, '4.00', '3.50', '3.00', '2.50', '2.00', '1.50', '1.00', 'in-progress', 'incomplete', 'dropped', 'repeat', 'failed')
    ";

    $stmt = $conn->prepare($gradeDistQuery);
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $gradeDist = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $gradeDist[$row['grade']] = $row['count'];
    }
    $stmt->close();

    // Get CO Performance
    $coPerfQuery = "
    SELECT 
    co.co_number as co_number,
    co.co_description as co_description,
    gc.component_name as assessment_name,
    gcc.performance_target,
    COUNT(DISTINCT CASE 
    WHEN (CAST(sfg.raw_score AS DECIMAL(10,2)) / gcc.max_score * 100) >= gcc.performance_target
    THEN ce.student_id 
    END) as students_met_target,
    COUNT(DISTINCT ce.student_id) as total_students,
    IFNULL(ROUND((COUNT(DISTINCT CASE 
    WHEN (CAST(sfg.raw_score AS DECIMAL(10,2)) / gcc.max_score * 100) >= gcc.performance_target
    THEN ce.student_id 
    END) * 100.0 / NULLIF(COUNT(DISTINCT ce.student_id), 0)), 2), 0) as success_rate
    FROM grading_components gc
    JOIN grading_component_columns gcc ON gc.id = gcc.component_id
    JOIN class_enrollments ce ON gc.class_code = ce.class_code AND ce.status = 'enrolled'
    LEFT JOIN student_flexible_grades sfg ON gcc.id = sfg.column_id AND ce.student_id = sfg.student_id
    LEFT JOIN course_outcomes co ON (
    gcc.co_mappings IS NOT NULL 
    AND (JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR))) OR JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR)))
    )
    WHERE gc.class_code = ? 
    AND gcc.is_summative = 'yes' 
    AND co.co_number IS NOT NULL
    GROUP BY co.co_id, co.co_number, co.co_description, gc.id, gc.component_name, gcc.performance_target
    ORDER BY co.co_number, gc.id
    ";

    $stmt = $conn->prepare($coPerfQuery);
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $coPerf = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $coPerf[] = $row;
    }
    $stmt->close();

    // Get Course Outcomes
    $stmt = $conn->prepare("SELECT * FROM course_outcomes WHERE course_code = ? ORDER BY co_number");
    $stmt->bind_param("s", $class['course_code']);
    $stmt->execute();
    $courseOutcomes = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $courseOutcomes[] = $row;
    }
    $stmt->close();

    // Get INC/DRP students
    $stmt = $conn->prepare("
    SELECT s.student_id, CONCAT(s.last_name, ', ', s.first_name) as name, tg.grade_status, tg.lacking_requirements
    FROM class_enrollments ce
    JOIN student s ON ce.student_id = s.student_id
    JOIN grade_term tg ON tg.class_code = ce.class_code AND tg.student_id = s.student_id
    WHERE ce.class_code = ? 
    AND ce.status = 'enrolled'
    AND tg.grade_status IN ('incomplete', 'dropped')
    ORDER BY s.last_name, s.first_name
    ");
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $incStudents = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $incStudents[] = $row;
    }
    $stmt->close();

    // Start building HTML - LANDSCAPE
    $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CAR - ' . htmlspecialchars($class_code) . '</title>
<style>
* {
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    font-size: 8pt;
    line-height: 1.15;
    color: #000;
    background: white;
}

.car-container {
    width: 100%;
    margin: 0;
    padding: 0.3in;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 4px 0;
    font-size: 7.5pt;
}

td, th {
    border: 0.75pt solid #000;
    padding: 3px 4px;
    vertical-align: top;
    line-height: 1.15;
}

th {
    background-color: #C0C0C0;
    font-weight: bold;
    text-align: center;
}

.page-footer {
    margin-top: 10px;
    padding-top: 5px;
    border-top: 0.75pt solid #000;
    font-size: 7pt;
    text-align: right;
}
</style>
</head>
<body>';

    // PAGE 1 - ULTRA COMPACT
    $html .= '<div class="car-container">';

    // Header - TABLE-BASED (mPDF compatible, no flexbox)
    $html .= '<table style="width: 100%; border: none; margin-bottom: 6px;">
    <tr>
        <td style="text-align: left; border: none; padding: 0; vertical-align: middle;">
            <div style="font-size: 11pt; font-weight: bold; color: #003082; letter-spacing: 0.5px;">NU LIPA</div>
        </td>
        <td style="text-align: right; border: none; padding: 0; vertical-align: middle; font-size: 7.5pt; line-height: 1.2;">
            <div style="font-weight: bold; font-size: 8.5pt;">COURSE ASSESSMENT REPORT</div>
            <div>AD-FO-01</div>
            <div>OCT 2022</div>
        </td>
    </tr>
</table>';
    $html .= '<table style="width: 100%; border-collapse: collapse; font-size: 8pt; margin: 5px 0;">
<tr>
    <td style="background-color: #C0C0C0; font-weight: bold; padding: 4px 6px; border: 1px solid #000; font-size: 8pt; width: 18%;">ACADEMIC TERM/ SCHOOL YEAR:</td>
    <td style="background-color: #C0C0C0; font-weight: bold; padding: 4px 6px; border: 1px solid #000; font-size: 8pt; width: 42%;">COURSE CODE:</td>
    <td colspan="3" style="background-color: #C0C0C0; font-weight: bold; padding: 4px 6px; border: 1px solid #000; vertical-align: top; font-size: 8pt; text-align: left; width: 3%;">COPIES ISSUED<br>TO:</td>
</tr>
<tr>
    <td style="text-align: center; padding: 4px 6px; border: 1px solid #000; font-weight: bold; font-size: 8pt;">' . strtoupper($class['term']) . ', AY ' . htmlspecialchars($class['academic_year']) . '</td>
    <td style="text-align: center; padding: 4px 6px; border: 1px solid #000; font-weight: bold; font-size: 8pt;">' . strtoupper(htmlspecialchars($class['course_code'])) . '</td>
    <td colspan="3" rowspan="3" style="text-align: left; padding: 4px 6px; border: 1px solid #000; vertical-align: top; font-size: 8pt; line-height: 1.3;">PROGRAM<br>CHAIR<br>ACADEMIC<br>DIRECTOR</td>
</tr>
<tr>
    <td style="background-color: #C0C0C0; font-weight: bold; padding: 4px 6px; border: 1px solid #000; font-size: 8pt; width: 9%;">SECTION</td>
    <td style="background-color: #C0C0C0; font-weight: bold; padding: 4px 6px; border: 1px solid #000; font-size: 8pt; width: 9%;">CLASS SIZE</td>
</tr>
<tr>
    <td style="text-align: center; padding: 4px 6px; border: 1px solid #000; font-weight: bold; font-size: 8pt;">' . htmlspecialchars($class['section']) . '</td>
    <td style="text-align: center; padding: 4px 6px; border: 1px solid #000; font-weight: bold; font-size: 8pt;">' . $studentCount . '</td>
</tr>
<tr>
    <td style="background-color: #C0C0C0; font-weight: bold; padding: 4px 6px; border: 1px solid #000; font-size: 8pt;">COURSE TITLE:</td>
    <td colspan="4" style="background-color: #C0C0C0; font-weight: bold; padding: 4px 6px; border: 1px solid #000; font-size: 8pt;">COURSE DESCRIPTION:</td>
</tr>
<tr>
    <td style="text-align: center; padding: 2px 4px; border: 1px solid #000; vertical-align: middle; font-weight: bold; font-size: 8pt; line-height: 1.1;">' . htmlspecialchars($subject['course_title'] ?? '') . '</td>
    <td colspan="4" rowspan="4" style="padding: 6px; border: 1px solid #000; font-size: 8pt; line-height: 1.25; vertical-align: top;">' . htmlspecialchars($subject['course_desc'] ?? '') . '</td>
</tr>
<tr>
    <td style="background-color: #C0C0C0; font-weight: bold; padding: 4px 6px; border: 1px solid #000; font-size: 8pt;">INSTRUCTOR:</td>
</tr>
<tr>
    <td style="text-align: center; padding: 4px 6px; border: 1px solid #000; font-weight: bold; font-size: 8pt;">' . strtoupper(htmlspecialchars($faculty['name'])) . '</td>
</tr>
<tr>
    <td style="background-color: #C0C0C0; font-weight: bold; padding: 4px 6px; border: 1px solid #000; font-size: 8pt;">SIGNATURE:</td>
</tr>
<tr>
    <td style="padding: 20px 6px; border: 1px solid #000; vertical-align: top;">&nbsp;</td>
</tr>
<tr>
</tr>
</table>';

    // GRADE DISTRIBUTION AND CO-SO MAP IN ONE ROW - ULTRA COMPACT
    $html .= '<table>
    <tr>
    <td style="width: 30%; background-color: #D9D9D9; font-weight: bold;">GRADE DISTRIBUTION:</td>
    <td style="width: 70%; background-color: #D9D9D9; font-weight: bold;">CO-SO MAP</td>
    </tr>
    <tr>
    <td style="width: 30%; vertical-align: top;">
    <table class="grade-dist-table" style="width: 100%; border-collapse: collapse; margin: 0;">
    <tr>
    <th style="width: 60%; background-color: #D9D9D9; font-weight: bold;">GRADE</th>
    <th style="width: 40%; background-color: #D9D9D9; font-weight: bold; text-align: center;">NO. OF STU.</th>
    </tr>';

    $gradeLabels = ['4.00', '3.50', '3.00', '2.50', '2.00', '1.50', '1.00', 'in-progress', 'incomplete', 'repeat', 'dropped', 'failed'];
    $gradeFullLabels = ['4.00', '3.50', '3.00', '2.50', '2.00', '1.50', '1.00', 'IN PROGRESS', 'INC', 'REPEAT', 'DROPPED', 'FAILED'];

    foreach ($gradeLabels as $idx => $key) {
        $label = $gradeFullLabels[$idx];
        $count = $gradeDist[$key] ?? 0;
        $html .= '<tr><td>' . $label . '</td><td style="text-align: center;">' . $count . '</td></tr>';
    }

    $html .= '            </table>
    </td>
    <td style="width: 70%; vertical-align: top;">
    <table class="coso-table" style="width: 100%; border-collapse: collapse; margin: 0;">
    <tr>
    <th style="background-color: #D9D9D9; font-weight: bold; width: 8%;">CO</th>
    <th style="background-color: #D9D9D9; font-weight: bold; width: 8%;">SO1</th>
    <th style="background-color: #D9D9D9; font-weight: bold; width: 8%;">SO2</th>
    <th style="background-color: #D9D9D9; font-weight: bold; width: 8%;">SO3</th>
    <th style="background-color: #D9D9D9; font-weight: bold; width: 8%;">SO4</th>
    <th style="background-color: #D9D9D9; font-weight: bold; width: 8%;">SO5</th>
    <th style="background-color: #D9D9D9; font-weight: bold; width: 8%;">SO6</th>
    </tr>';

    // Get CO-SO mappings from database
    $soMappingQuery = "
    SELECT co.co_number, GROUP_CONCAT(DISTINCT csm.so_number ORDER BY csm.so_number) as mapped_sos
    FROM course_outcomes co
    LEFT JOIN co_so_mapping csm ON co.co_id = csm.co_id
    WHERE co.course_code = ?
    GROUP BY co.co_id, co.co_number
    ORDER BY co.co_number
    ";

    $stmt = $conn->prepare($soMappingQuery);
    $stmt->bind_param("s", $class['course_code']);
    $stmt->execute();
    $soMappingResult = $stmt->get_result();
    $soMappingData = [];
    while ($row = $soMappingResult->fetch_assoc()) {
        if ($row['mapped_sos']) {
            $soMappingData[$row['co_number']] = explode(',', $row['mapped_sos']);
        }
    }
    $stmt->close();

    // Build CO-SO map table with database values
    $coNumbers = array_keys($soMappingData);
    sort($coNumbers);

    for ($i = 0; $i < count($coNumbers); $i++) {
        $coNum = $coNumbers[$i];
        $html .= '<tr><td style="text-align: center; font-weight: bold;">CO' . $coNum . '</td>';
        $mappedSOs = $soMappingData[$coNum] ?? [];
        for ($j = 1; $j <= 6; $j++) {
            $html .= '<td style="text-align: center;">' . (in_array($j, $mappedSOs) ? '✓' : '') . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '            </table>
    <!-- OUTCOMES SECTION - INSIDE RIGHT COLUMN -->
    <strong>Student Outcomes (SO):</strong><br>';

    $soDescriptions = [
        '1. Analyze complex computing problems and to apply principles of computing and other relevant disciplines to identify solutions.',
        '2. Design, implement, and evaluate a computing-based solution to meet a given set of computing requirements in the context of the programs.',
        '3. Communicate effectively in a variety of professional contexts.',
        '4. Recognize professional responsibilities and make informed judgements in computing practice based on legal and ethical principles.',
        '5. Function effectively as a member or leader of a team engaged in activities appropriate to the program\'s discipline.',
        '6. Apply computer science theory and software development fundamentals to produce computing-based solutions'
    ];

    foreach ($soDescriptions as $so) {
        $html .= '<div style="margin-bottom: 1px; line-height: 1.1; font-size: 8pt;">' . $so . '</div>';
    }

    $html .= '<br><strong style="font-size: 7.5pt;">Course Outcomes (CO):</strong><br>';

    foreach ($courseOutcomes as $idx => $co) {
        $html .= '<div style="margin-bottom: 1px; line-height: 1.1; font-size: 8pt;">' . htmlspecialchars($co['co_description']) . '</div>';
    }

    $html .= '    </td>
    </tr>
    </table>';

    $html .= '<div class="page-footer">Page 1 of 3</div>
    </div>';

    // PAGE BREAK - Use mPDF native pagebreak
    $html .= '<pagebreak />';

    // PAGE 2 - FORMATTED VERSION
    $html .= '<div class="car-container">
    <table style="width: 100%; border: none; margin-bottom: 8px;">
    <tr>
        <td style="text-align: left; border: none; padding: 0; vertical-align: top;">
            <div style="font-size: 12pt; font-weight: bold; color: #003082;">NU LIPA</div>
        </td>
        <td style="text-align: right; border: none; padding: 0; vertical-align: top; font-size: 8pt; line-height: 1.2;">
            <div style="font-weight: bold; font-size: 9pt;">COURSE ASSESSMENT REPORT</div>
            <div>AD-FO-01</div>
            <div>OCT 2022</div>
        </td>
    </tr>
</table>';

    // Course Learning Outcomes Assessment
    $html .= '<table>
    <tr>
    <td colspan="4" style="background-color: #BFBFBF; font-weight: bold; text-align: center; font-size: 9pt; padding: 4px;">COURSE LEARNING OUTCOMES ASSESSMENT</td>
    </tr>
    <tr>
    <th style="width: 15%; background-color: #D9D9D9; font-weight: bold; text-align: center; font-size: 8pt; padding: 4px;">COURSE<br>OUTCOME</th>
    <th style="width: 30%; background-color: #D9D9D9; font-weight: bold; text-align: center; border: 0.75pt solid #000; font-size: 8pt; padding: 4px;">SUMMATIVE ASSESSMENT</th>
    <th style="width: 20%; background-color: #D9D9D9; font-weight: bold; text-align: center; border: 0.75pt solid #000; font-size: 8pt; padding: 4px;">PERFORMANCE TARGET</th>
    <th style="width: 35%; background-color: #D9D9D9; font-weight: bold; text-align: center; border: 0.75pt solid #000; font-size: 8pt; padding: 4px;">SUCCESS RATE<br>(in % and enclose the number in [ ])</th>
    </tr>';

    foreach ($coPerf as $co) {
        $html .= '<tr>
    <td style="text-align: center; font-weight: bold; font-size: 8pt; padding: 3px;">' . 'CO' . $co['co_number'] . '</td>
    <td style="font-size: 8pt; padding: 3px;">' . htmlspecialchars($co['assessment_name']) . '</td>
    <td style="text-align: center; font-size: 8pt; padding: 3px;">' . $co['performance_target'] . '%</td>
    <td style="text-align: center; font-size: 8pt; padding: 3px;">' . round($co['success_rate']) . '% [' . $co['students_met_target'] . ']</td>
    </tr>';
    }

    $html .= '</table>';

    // List of Students with INC
    $html .= '<table>
    <tr>
    <td colspan="2" style="background-color: #D9D9D9; font-weight: bold; font-size: 8pt; padding: 4px;">List of Students with INC</td>
    </tr>
    <tr>
    <th style="width: 50%; background-color: #D9D9D9; font-weight: bold; text-align: center; font-size: 8pt; padding: 4px;">Name</th>
    <th style="width: 50%; background-color: #D9D9D9; font-weight: bold; text-align: center; font-size: 8pt; padding: 4px;">Lacking Requirements</th>
    </tr>';

    if (!empty($incStudents)) {
        foreach ($incStudents as $student) {
            $html .= '<tr>
        <td style="font-weight: bold; font-size: 8pt; padding: 3px;">' . htmlspecialchars($student['name']) . '</td>
        <td style="font-size: 8pt; padding: 3px;">' . htmlspecialchars($student['lacking_requirements'] ?: ($student['grade_status'] === 'dropped' ? 'Officially Dropped' : 'N/A')) . '</td>
        </tr>';
        }
    } else {
        $html .= '<tr><td colspan="2" style="text-align: center; font-style: italic; font-size: 8pt; padding: 3px;">No students with INC</td></tr>';
    }

    $html .= '</table>';

    // Teaching Strategies
    $html .= '<table>
    <tr>
    <td style="background-color: #D9D9D9; font-weight: bold; font-size: 8pt; padding: 4px;"><strong>Teaching Strategies Employed</strong> (List and give a brief description of each teaching strategy employed in class)</td>
    </tr>
    <tr>
    <td style="padding: 6px; font-size: 8pt;">';

    if (!empty($metadata['teaching_strategies'])) {
        $strategies = explode("\n", $metadata['teaching_strategies']);
        $count = 1;
        foreach ($strategies as $strategy) {
            $strategy = trim($strategy);
            if (!empty($strategy)) {
                // Try to split strategy name and description if there's a dash or colon
                $parts = preg_split('/[–—-]/', $strategy, 2);
                if (count($parts) > 1) {
                    $strategyName = trim($parts[0]);
                    $strategyDesc = trim($parts[1]);
                    $html .= '<div style="margin-bottom: 4px; line-height: 1.2;"><strong>' . $count . '. ' . htmlspecialchars($strategyName) . '</strong> – ' . htmlspecialchars($strategyDesc) . '</div>';
                } else {
                    $html .= '<div style="margin-bottom: 4px; line-height: 1.2;"><strong>' . $count . '.</strong> ' . htmlspecialchars($strategy) . '</div>';
                }
                $count++;
            }
        }
    } else {
        $html .= '<div style="font-style: italic;">No strategies recorded.</div>';
    }

    $html .= '    </td>
    </tr>
    </table>';

    // Intervention Activities
    $html .= '<table>
    <tr>
    <th style="width: 70%; background-color: #D9D9D9; font-weight: bold; font-size: 8pt; padding: 4px;">Intervention or Enrichment Activities Conducted</th>
    <th style="width: 30%; background-color: #D9D9D9; font-weight: bold; text-align: center; font-size: 8pt; padding: 4px;">No. of Students Involved</th>
    </tr>';

    if (!empty($metadata['interventions'])) {
        $html .= '<tr>
    <td style="font-size: 8pt; padding: 3px;">' . htmlspecialchars($metadata['interventions']) . '</td>
    <td style="text-align: center; font-size: 8pt; padding: 3px;">' . ($metadata['intervention_student_count'] ?? 0) . '</td>
    </tr>';
    } else {
        $html .= '<tr><td colspan="2" style="text-align: center; font-style: italic; font-size: 8pt; padding: 3px;">None</td></tr>';
    }

    $html .= '</table>';

    // Problems & Actions
    $html .= '<table>
    <tr>
    <th style="width: 50%; background-color: #D9D9D9; font-weight: bold; font-size: 8pt; padding: 4px;">Problems Encountered <span style="font-weight: normal;">(Brief Description)</span></th>
    <th style="width: 50%; background-color: #D9D9D9; font-weight: bold; font-size: 8pt; padding: 4px;">Action Taken</th>
    </tr>
    <tr>
    <td style="font-size: 8pt; padding: 6px; vertical-align: top;">' . htmlspecialchars($metadata['problems_encountered'] ?? 'None') . '</td>
    <td style="font-size: 8pt; padding: 6px; vertical-align: top;">';

    // Format Action Taken with bullets if it contains line breaks
    if (!empty($metadata['actions_taken'])) {
        $actions = explode("\n", $metadata['actions_taken']);
        foreach ($actions as $action) {
            $action = trim($action);
            if (!empty($action)) {
                // Remove existing bullets/dashes if any
                $action = preg_replace('/^[-•►]\s*/', '', $action);
                $html .= '<div style="margin-bottom: 3px;">► ' . htmlspecialchars($action) . '</div>';
            }
        }
    } else {
        $html .= 'None';
    }

    $html .= '    </td>
    </tr>
    </table>';

    $html .= '<div class="page-footer">Page 2 of 3</div>
    </div>';

    // PAGE BREAK - Use mPDF native pagebreak
    $html .= '<pagebreak />';

    // PAGE 3 - PROPOSED IMPROVEMENTS
    $html .= '<div class="car-container">
    <table style="width: 100%; border: none; margin-bottom: 8px;">
    <tr>
        <td style="text-align: left; border: none; padding: 0; vertical-align: top;">
            <div style="font-size: 12pt; font-weight: bold; color: #003082;">NU LIPA</div>
        </td>
        <td style="text-align: right; border: none; padding: 0; vertical-align: top; font-size: 8pt; line-height: 1.2;">
            <div style="font-weight: bold; font-size: 9pt;">COURSE ASSESSMENT REPORT</div>
            <div>AD-FO-01</div>
            <div>OCT 2022</div>
        </td>
    </tr>
</table>';

    $html .= '<table>
    <tr>
    <td style="background-color: #D9D9D9; font-weight: bold; font-size: 9pt; padding: 4px;">PROPOSED ACTIONS FOR COURSE IMPROVEMENT</td>
    </tr>
    <tr>
    <td style="padding: 8px; font-size: 8pt; line-height: 1.3; vertical-align: top;">';

    if (!empty($metadata['proposed_improvements'])) {
        $improvements = explode("\n", $metadata['proposed_improvements']);
        foreach ($improvements as $improvement) {
            $improvement = trim($improvement);
            if (!empty($improvement)) {
                $html .= '<div style="margin-bottom: 4px;">' . htmlspecialchars($improvement) . '</div>';
            }
        }
    } else {
        $html .= '<div style="font-style: italic;">No improvements proposed.</div>';
    }

    $html .= '        </td>
    </tr>
    </table>';

    $html .= '<div class="page-footer">Page 3 of 3</div>
    </div>';

    $html .= '
</body>
</html>';

    echo json_encode([
        'success' => true,
        'html' => $html,
        'class_code' => $class_code
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
