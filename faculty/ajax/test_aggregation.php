<?php
// Simulate the aggregation logic from generate_coa_html.php

$coPerf = [
    // CO1 - Classwork
    ['co_number' => '1', 'co_description' => '1. Recognize modular programming.', 'assessment_name' => 'Classwork', 'performance_target' => 60, 'students_met_target' => 2, 'total_students' => 3],
    ['co_number' => '1', 'co_description' => '1. Recognize modular programming.', 'assessment_name' => 'Classwork', 'performance_target' => 60, 'students_met_target' => 2, 'total_students' => 3],
    ['co_number' => '1', 'co_description' => '1. Recognize modular programming.', 'assessment_name' => 'Classwork', 'performance_target' => 60, 'students_met_target' => 3, 'total_students' => 3],
    // CO2 - Quizzes and Quiz mixed
    ['co_number' => '2', 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quizzes', 'performance_target' => 60, 'students_met_target' => 0, 'total_students' => 2],
    ['co_number' => '2', 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quizzes', 'performance_target' => 60, 'students_met_target' => 1, 'total_students' => 2],
    ['co_number' => '2', 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quizzes', 'performance_target' => 60, 'students_met_target' => 1, 'total_students' => 2],
    ['co_number' => '2', 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quiz', 'performance_target' => 60, 'students_met_target' => 2, 'total_students' => 3],
    ['co_number' => '2', 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quiz', 'performance_target' => 60, 'students_met_target' => 2, 'total_students' => 2],
    ['co_number' => '2', 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quiz', 'performance_target' => 60, 'students_met_target' => 2, 'total_students' => 2],
    ['co_number' => '2', 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quiz', 'performance_target' => 60, 'students_met_target' => 1, 'total_students' => 2],
    ['co_number' => '2', 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quiz', 'performance_target' => 60, 'students_met_target' => 0, 'total_students' => 1],
    // CO3 - Multiple assessments
    ['co_number' => '3', 'co_description' => '3. Implement array data structure and file manipulation.', 'assessment_name' => 'System Prototype', 'performance_target' => 60, 'students_met_target' => 0, 'total_students' => 2],
    ['co_number' => '3', 'co_description' => '3. Implement array data structure and file manipulation.', 'assessment_name' => 'Laboratory Exam', 'performance_target' => 60, 'students_met_target' => 1, 'total_students' => 2],
    ['co_number' => '3', 'co_description' => '3. Implement array data structure and file manipulation.', 'assessment_name' => 'Laboratory Exam', 'performance_target' => 60, 'students_met_target' => 1, 'total_students' => 2],
    ['co_number' => '3', 'co_description' => '3. Implement array data structure and file manipulation.', 'assessment_name' => 'Laboratory Exam', 'performance_target' => 60, 'students_met_target' => 1, 'total_students' => 2],
    ['co_number' => '3', 'co_description' => '3. Implement array data structure and file manipulation.', 'assessment_name' => 'Mock Defense', 'performance_target' => 60, 'students_met_target' => 0, 'total_students' => 2],
    ['co_number' => '3', 'co_description' => '3. Implement array data structure and file manipulation.', 'assessment_name' => 'Lab Works', 'performance_target' => 60, 'students_met_target' => 2, 'total_students' => 3],
];

echo "=== AGGREGATION TEST ===\n";
echo "Input rows: " . count($coPerf) . "\n\n";

$coGroups = [];
foreach ($coPerf as $perf) {
    $co_key = $perf['co_number'];
    
    // Normalize assessment names: combine 'Quiz' and 'Quizzes' into one
    $assessment_name = $perf['assessment_name'];
    $assessment_key = strtolower(trim($assessment_name));
    
    echo "Processing: CO{$co_key}, Assessment: {$assessment_name} -> Key: {$assessment_key}";
    
    // Normalize quiz variations
    if (strpos($assessment_key, 'quiz') !== false) {
        $assessment_key = 'quiz';
        echo " [NORMALIZED TO 'quiz']";
    }
    echo "\n";
    
    if (!isset($coGroups[$co_key])) {
        $coGroups[$co_key] = [
            'co_number' => $perf['co_number'],
            'co_description' => $perf['co_description'],
            'assessments' => []
        ];
    }
    
    if (!isset($coGroups[$co_key]['assessments'][$assessment_key])) {
        $display_name = $assessment_key === 'quiz' ? 'Quiz' : ucfirst($assessment_key);
        
        $coGroups[$co_key]['assessments'][$assessment_key] = [
            'name' => $display_name,
            'target' => $perf['performance_target'] ?? 60,
            'met_students' => [],
            'all_students' => [],
            'success_rate' => 0
        ];
        echo "  -> Created new entry for {$assessment_key} (display: {$display_name})\n";
    } else {
        echo "  -> Using existing entry for {$assessment_key}\n";
    }
    
    $met = intval($perf['students_met_target'] ?? 0);
    $total = intval($perf['total_students'] ?? 0);
    
    $coGroups[$co_key]['assessments'][$assessment_key]['met_students'][] = $met;
    $coGroups[$co_key]['assessments'][$assessment_key]['all_students'][] = $total;
    echo "     Met: [{$met}], Total: [{$total}]\n";
}

echo "\n=== FINAL GROUPS ===\n";
foreach ($coGroups as $co_key => &$coData) {
    echo "CO{$co_key}: " . $coData['co_description'] . "\n";
    echo "  Assessments: " . count($coData['assessments']) . "\n";
    
    foreach ($coData['assessments'] as &$assessment) {
        $assessment['met'] = max($assessment['met_students']);
        $assessment['total'] = max($assessment['all_students']);
        
        if ($assessment['total'] > 0) {
            $assessment['success_rate'] = round(($assessment['met'] / $assessment['total']) * 100, 2);
        } else {
            $assessment['success_rate'] = 0;
        }
        
        echo "    - {$assessment['name']}: {$assessment['met']}/{$assessment['total']} = {$assessment['success_rate']}%\n";
    }
    echo "\n";
}

echo "=== OUTPUT ===\n";
foreach ($coGroups as $coData) {
    $assessments = array_values($coData['assessments']);
    foreach ($assessments as $idx => $assessment) {
        echo "Row {$idx}: CO{$coData['co_number']} | {$assessment['name']} | {$assessment['met']}/{$assessment['total']} | {$assessment['success_rate']}%\n";
    }
}

?>
