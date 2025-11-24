<?php
require_once __DIR__ . '/config/db.php';

// Simulate the query results
$test_results = [
    ['co_number' => 1, 'co_description' => '1. Recognize modular programming.', 'assessment_name' => 'Classwork', 'performance_target' => 60, 'students_met_target' => 3, 'total_students' => 3],
    ['co_number' => 1, 'co_description' => '1. Recognize modular programming.', 'assessment_name' => 'Classwork', 'performance_target' => 60, 'students_met_target' => 2, 'total_students' => 3],
    ['co_number' => 1, 'co_description' => '1. Recognize modular programming.', 'assessment_name' => 'Classwork', 'performance_target' => 60, 'students_met_target' => 3, 'total_students' => 3],
    ['co_number' => 2, 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quizzes', 'performance_target' => 60, 'students_met_target' => 0, 'total_students' => 2],
    ['co_number' => 2, 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quizzes', 'performance_target' => 60, 'students_met_target' => 1, 'total_students' => 2],
    ['co_number' => 2, 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quizzes', 'performance_target' => 60, 'students_met_target' => 1, 'total_students' => 2],
    ['co_number' => 2, 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quiz', 'performance_target' => 60, 'students_met_target' => 2, 'total_students' => 3],
    ['co_number' => 2, 'co_description' => '2. Apply different control structures', 'assessment_name' => 'Quiz', 'performance_target' => 60, 'students_met_target' => 2, 'total_students' => 2],
];

// Aggregation logic from generate_coa_html.php
$coGroups = [];
foreach ($test_results as $perf) {
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
        } else {
            $assessment['success_rate'] = 0;
        }
    }
}

// Display debug info
echo "Aggregated CO Groups:\n";
echo str_repeat("-", 80) . "\n";

foreach ($coGroups as $co_key => $coData) {
    echo "\nCO_KEY=$co_key:\n";
    echo "  CO{$coData['co_number']}: {$coData['co_description']}\n";
    echo "  Assessments: " . count($coData['assessments']) . "\n";
    
    foreach ($coData['assessments'] as $akey => $assessment) {
        echo "    [$akey] Display='{$assessment['name']}', Met={$assessment['met']}, Total={$assessment['total']}, %={$assessment['success_rate']}\n";
    }
}
?>
