<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/faculty/helpers/grades.php';

$class_code = '24_T2_CCPRGG1L_INF222';

// Get class info
$sql = "SELECT ce.class_code, ce.course_code, c.course_name, c.course_desc, 
               ci.instructor_name, ce.term, ce.academic_year, c.course_code
        FROM class_enrollments ce
        JOIN courses c ON c.course_code = ce.course_code
        JOIN class_info ci ON ci.class_code = ce.class_code
        WHERE ce.class_code = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();
$class = $result->fetch_assoc();
$stmt->close();

if (!$class) {
    die("Class not found");
}

// Get course outcomes
$sql = "SELECT co_number, co_description FROM course_outcomes WHERE course_code = ? ORDER BY co_number";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $class['course_code']);
$stmt->execute();
$result = $stmt->get_result();
$courseOutcomes = [];
while ($row = $result->fetch_assoc()) {
    $courseOutcomes[] = $row;
}
$stmt->close();

// Performance query
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
                COUNT(DISTINCT sfg.student_id) AS total_students
               FROM course_outcomes co
               LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))
               LEFT JOIN grading_components gc ON gc.id = gcc.component_id
               LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
               LEFT JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id
               WHERE co.course_code = ?
                    AND ce.status = 'enrolled'
               GROUP BY co.co_number, co.co_description, gc.id, gcc.id, gcc.performance_target, gcc.max_score
               ORDER BY co.co_number, gc.id";

$stmt = $conn->prepare($coPerfQuery);
$stmt->bind_param("ss", $class_code, $class['course_code']);
$stmt->execute();
$result = $stmt->get_result();
$coPerf = [];
while ($row = $result->fetch_assoc()) {
    $coPerf[] = $row;
}
$stmt->close();

// Aggregation
$coGroups = [];
foreach ($coPerf as $perf) {
    $co_key = $perf['co_number'];
    
    // Normalize assessment names
    $assessment_name = $perf['assessment_name'];
    $assessment_key = strtolower(trim($assessment_name));
    if (strpos($assessment_key, 'quiz') !== false) {
        $assessment_key = 'quiz';
    }
    
    if (!isset($coGroups[$co_key])) {
        $coGroups[$co_key] = [
            'co_number' => $perf['co_number'],
            'co_description' => $perf['co_description'],
            'assessments' => []
        ];
    }
    
    if (!isset($coGroups[$co_key]['assessments'][$assessment_key])) {
        $coGroups[$co_key]['assessments'][$assessment_key] = [
            'name' => $perf['assessment_name'],
            'target' => $perf['performance_target'] ?? 60,
            'met_students' => [],
            'all_students' => [],
            'success_rate' => 0
        ];
    }
    
    $coGroups[$co_key]['assessments'][$assessment_key]['met_students'][] = intval($perf['students_met_target'] ?? 0);
    $coGroups[$co_key]['assessments'][$assessment_key]['all_students'][] = intval($perf['total_students'] ?? 0);
}

// Post-process
foreach ($coGroups as &$coData) {
    foreach ($coData['assessments'] as &$assessment) {
        $assessment['met'] = max($assessment['met_students']);
        $assessment['total'] = max($assessment['all_students']);
        unset($assessment['met_students']);
        unset($assessment['all_students']);
        
        if ($assessment['total'] > 0) {
            $assessment['success_rate'] = round(($assessment['met'] / $assessment['total']) * 100, 2);
        }
    }
}

// Display results
echo "Final Aggregated Groups:\n";
echo str_repeat("=", 80) . "\n";

foreach ($coGroups as $co_key => $coData) {
    echo "\nCO{$coData['co_number']}: {$coData['co_description']}\n";
    echo "  Assessments: " . count($coData['assessments']) . "\n";
    
    foreach ($coData['assessments'] as $akey => $assessment) {
        echo "    - {$assessment['name']}: {$assessment['met']}/{$assessment['total']} = {$assessment['success_rate']}%\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Total COs in coGroups: " . count($coGroups) . "\n";
?>
