<?php
require_once __DIR__ . '/config/db.php';

$class_code = '24_T2_CCPRGG1L_INF222';

$sql = "SELECT course_code FROM class_enrollments WHERE class_code = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();
$class = $result->fetch_assoc();
$stmt->close();

$coPerfQuery = "SELECT 
                co.co_number,
                co.co_description,
                gc.component_name AS assessment_name,
                gcc.performance_target
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

// Aggregation with detailed tracing
$coGroups = [];
foreach ($coPerf as $idx => $perf) {
    $co_key = $perf['co_number'];
    $assessment_key = strtolower(trim($perf['assessment_name']));
    
    echo "Processing row $idx: CO{$co_key} > {$perf['assessment_name']}\n";
    
    if (!isset($coGroups[$co_key])) {
        echo "  -> Creating new coGroup for CO_KEY=$co_key\n";
        $coGroups[$co_key] = [
            'co_number' => $perf['co_number'],
            'co_description' => $perf['co_description'],
            'assessments' => []
        ];
    } else {
        echo "  -> coGroup for CO_KEY=$co_key already exists (CO{$coGroups[$co_key]['co_number']})\n";
    }
    
    if (!isset($coGroups[$co_key]['assessments'][$assessment_key])) {
        echo "  -> Creating new assessment [$assessment_key]\n";
    } else {
        echo "  -> Assessment [$assessment_key] already exists\n";
    }
}

echo "\n\nFinal coGroups:\n";
foreach ($coGroups as $key => $data) {
    echo "[$key] => CO{$data['co_number']}: {$data['co_description']}\n";
}
?>
