<?php
// Direct query approach instead
session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';

require_once '../../config/db.php';

$class_code = '24_T2_CCPRGG1L_INF222';

// Query the data directly
$coPerfQuery = "SELECT 
    co.co_number, co.co_description,
    gc.component_name AS assessment_name,
    COUNT(DISTINCT CASE WHEN sfg.raw_score >= gcc.performance_target THEN sfg.student_id END) AS students_met_target,
    COUNT(DISTINCT sfg.student_id) AS total_students
FROM course_outcomes co
LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
LEFT JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id
WHERE co.course_code = 'CCPRGG1L' AND ce.status = 'enrolled'
GROUP BY co.co_number, co.co_description, gc.id, gcc.id, gcc.performance_target, gcc.max_score
ORDER BY co.co_number, gc.id";

$stmt = $conn->prepare($coPerfQuery);
$stmt->bind_param('s', $class_code);
$stmt->execute();
$coPerf = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "=== RAW QUERY DATA ===\n";
echo "Total rows: " . count($coPerf) . "\n";

// Count by CO
$coCounts = [];
foreach ($coPerf as $row) {
    $co = $row['co_number'];
    $coCounts[$co] = ($coCounts[$co] ?? 0) + 1;
}

echo "Distribution:\n";
foreach ($coCounts as $co => $count) {
    echo "  CO$co: $count rows\n";
}

// Simulate aggregation
$coGroups = [];
foreach ($coPerf as $perf) {
    $co_key = $perf['co_number'];
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
        $display_name = $assessment_key === 'quiz' ? 'Quiz' : ucfirst($assessment_key);
        $coGroups[$co_key]['assessments'][$assessment_key] = [
            'name' => $display_name,
            'count' => 0
        ];
    }
    
    $coGroups[$co_key]['assessments'][$assessment_key]['count']++;
}

// Verify unset was applied (this simulates what generate_coa_html.php should do)
unset($coData, $assessment);  // This is what was added

echo "\n=== AGGREGATED DATA ===\n";
echo "Array keys: " . implode(', ', array_keys($coGroups)) . "\n";
foreach ($coGroups as $key => $coData) {
    echo "Key $key -> CO" . $coData['co_number'] . " with " . count($coData['assessments']) . " assessments\n";
    foreach ($coData['assessments'] as $aname => $adata) {
        echo "  - {$adata['name']}\n";
    }
}

echo "\n=== VERIFICATION ===\n";
$co2Count = count($coGroups[2]['assessments'] ?? []);
$co3Count = count($coGroups[3]['assessments'] ?? []);

echo "CO2 assessments: $co2Count (expected: 1)\n";
echo "CO3 assessments: $co3Count (expected: 4)\n";
echo "CO3 has 'Lab works': " . (isset($coGroups[3]['assessments']['lab works']) ? 'YES' : 'NO') . "\n";

echo "\n=== RESULT ===\n";
if ($co2Count === 1 && $co3Count === 4) {
    echo "✅ BUG FIX VERIFIED!\n";
} else {
    echo "❌ Issue remains\n";
}
?>
