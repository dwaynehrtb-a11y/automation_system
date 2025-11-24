<?php
require_once __DIR__ . '/config/db.php';

// Simulate the aggregation logic
$test_data = [
    ['co_number' => 2, 'assessment_name' => 'Quizzes', 'students_met' => 0, 'total' => 2],
    ['co_number' => 2, 'assessment_name' => 'Quizzes', 'students_met' => 1, 'total' => 2],
    ['co_number' => 2, 'assessment_name' => 'Quizzes', 'students_met' => 1, 'total' => 2],
    ['co_number' => 2, 'assessment_name' => 'Quiz', 'students_met' => 2, 'total' => 3],
    ['co_number' => 2, 'assessment_name' => 'Quiz', 'students_met' => 2, 'total' => 2],
    ['co_number' => 2, 'assessment_name' => 'Quiz', 'students_met' => 2, 'total' => 2],
];

$coGroups = [];
foreach ($test_data as $perf) {
    $co_key = $perf['co_number'];
    
    // Normalize assessment names
    $assessment_name = $perf['assessment_name'];
    $assessment_key = strtolower(trim($assessment_name));
    if (strpos($assessment_key, 'quiz') !== false) {
        $assessment_key = 'quiz';
    }
    
    if (!isset($coGroups[$co_key])) {
        $coGroups[$co_key] = [
            'co_number' => $co_key,
            'assessments' => []
        ];
    }
    
    if (!isset($coGroups[$co_key]['assessments'][$assessment_key])) {
        $coGroups[$co_key]['assessments'][$assessment_key] = [
            'name' => $perf['assessment_name'],
            'met_students' => [],
            'all_students' => []
        ];
    }
    
    $coGroups[$co_key]['assessments'][$assessment_key]['met_students'][] = $perf['students_met'];
    $coGroups[$co_key]['assessments'][$assessment_key]['all_students'][] = $perf['total'];
}

// Show results
echo "Aggregation Results:\n";
foreach ($coGroups as $co_key => $coData) {
    echo "\nCO" . $coData['co_number'] . ":\n";
    foreach ($coData['assessments'] as $key => $assessment) {
        $met = max($assessment['met_students']);
        $total = max($assessment['all_students']);
        $pct = $total > 0 ? round(($met / $total) * 100, 2) : 0;
        echo "  $key: Display Name='{$assessment['name']}', Met=$met, Total=$total, %=$pct\n";
        echo "    met_students: " . implode(", ", $assessment['met_students']) . "\n";
        echo "    all_students: " . implode(", ", $assessment['all_students']) . "\n";
    }
}
?>
