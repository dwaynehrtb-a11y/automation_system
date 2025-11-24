<?php
// Diagnose exact state of coGroups array
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once '../../../automation_system/config/Database.php';
require_once '../../../automation_system/config/logger.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
    $user_id = 114;
    $class_code = '24_T2_CCPRGG1L_INF222';
    
    // Get all course outcomes
    $cosQuery = "SELECT co_number, co_description FROM course_outcomes WHERE course_code = 'CCPRGG1L' ORDER BY co_number";
    $cosResult = $conn->query($cosQuery);
    $cos = [];
    while ($row = $cosResult->fetch_assoc()) {
        $cos[] = $row;
    }
    
    // Get performance data (exact same query as in generate_coa_html.php)
    $coPerfQuery = "SELECT 
        co.co_number, co.co_description,
        gc.component_name AS assessment_name,
        gcc.performance_target, gcc.max_score,
        COUNT(DISTINCT CASE WHEN sfg.raw_score >= gcc.performance_target THEN sfg.student_id END) AS students_met_target,
        COUNT(DISTINCT sfg.student_id) AS total_students,
        ROUND(COUNT(DISTINCT CASE WHEN sfg.raw_score >= gcc.performance_target THEN sfg.student_id END) / COUNT(DISTINCT sfg.student_id) * 100, 2) AS success_rate
    FROM course_outcomes co
    LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))
    LEFT JOIN grading_components gc ON gc.id = gcc.component_id
    LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
    LEFT JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id
    WHERE co.course_code = ? AND ce.status = 'enrolled'
    GROUP BY co.co_number, co.co_description, gc.id, gcc.id, gcc.performance_target, gcc.max_score
    ORDER BY co.co_number, gc.id";
    
    $stmt = $conn->prepare($coPerfQuery);
    $stmt->bind_param('ss', $class_code, 'CCPRGG1L');
    $stmt->execute();
    $coPerf = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "=== RAW QUERY DATA ===\n";
    echo "Total rows: " . count($coPerf) . "\n";
    foreach ($coPerf as $idx => $perf) {
        echo "Row $idx: CO{$perf['co_number']} | {$perf['assessment_name']}\n";
    }
    
    // Now simulate aggregation
    echo "\n=== AGGREGATION PROCESS ===\n";
    $coGroups = [];
    foreach ($coPerf as $perf) {
        $co_key = $perf['co_number'];
        echo "Processing CO{$co_key} | Assessment: {$perf['assessment_name']}\n";
        
        $assessment_name = $perf['assessment_name'];
        $assessment_key = strtolower(trim($assessment_name));
        if (strpos($assessment_key, 'quiz') !== false) {
            $assessment_key = 'quiz';
        }
        
        if (!isset($coGroups[$co_key])) {
            echo "  -> Creating CO{$co_key} entry\n";
            $coGroups[$co_key] = [
                'co_number' => $perf['co_number'],
                'co_description' => $perf['co_description'],
                'assessments' => []
            ];
        }
        
        if (!isset($coGroups[$co_key]['assessments'][$assessment_key])) {
            $display_name = $assessment_key === 'quiz' ? 'Quiz' : ucfirst($assessment_key);
            echo "  -> Creating assessment '{$assessment_key}' (display: '{$display_name}')\n";
            $coGroups[$co_key]['assessments'][$assessment_key] = [
                'name' => $display_name,
                'target' => $perf['performance_target'] ?? 60,
                'met_students' => [],
                'all_students' => [],
                'success_rate' => 0
            ];
        }
        
        $met = intval($perf['students_met_target'] ?? 0);
        $total = intval($perf['total_students'] ?? 0);
        $coGroups[$co_key]['assessments'][$assessment_key]['met_students'][] = $met;
        $coGroups[$co_key]['assessments'][$assessment_key]['all_students'][] = $total;
    }
    
    echo "\n=== FINAL COGROUPS ARRAY ===\n";
    echo "Keys: " . implode(', ', array_keys($coGroups)) . "\n";
    echo "Count: " . count($coGroups) . "\n";
    
    foreach ($coGroups as $key => $coData) {
        echo "Key=$key (is_numeric=" . (is_numeric($key) ? 'YES' : 'NO') . ", type=" . gettype($key) . ")\n";
        echo "  co_number={$coData['co_number']}\n";
        echo "  assessments count=" . count($coData['assessments']) . "\n";
        foreach ($coData['assessments'] as $akey => $aval) {
            echo "    Assessment key='$akey' | name='{$aval['name']}'\n";
        }
    }
    
    echo "\n=== ITERATION TEST ===\n";
    $iteration_count = 0;
    foreach ($coGroups as $coData) {
        $iteration_count++;
        echo "Iteration $iteration_count: CO{$coData['co_number']} with {$coData['co_description']}\n";
    }
    echo "Total iterations: $iteration_count\n";
    
    echo "\n=== ARRAY STRUCTURE DUMP ===\n";
    var_dump($coGroups);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
