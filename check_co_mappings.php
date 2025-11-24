<?php
require_once __DIR__ . '/config/db.php';

$course_code = 'CCPRGG1L';

// Check all components and their CO mappings
$sql = "SELECT 
    gc.id,
    gc.component_name,
    gcc.co_mappings,
    COUNT(DISTINCT gcc.id) as num_columns
FROM grading_components gc
LEFT JOIN grading_component_columns gcc ON gcc.component_id = gc.id
WHERE gc.course_code = ?
GROUP BY gc.id, gc.component_name, gcc.co_mappings
ORDER BY gc.component_name, gcc.co_mappings";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $course_code);
$stmt->execute();
$result = $stmt->get_result();

echo "Component CO Mappings for $course_code:\n";
echo str_repeat("-", 80) . "\n";

$prev_component = null;
while ($row = $result->fetch_assoc()) {
    $component = $row['component_name'];
    $cos = json_decode($row['co_mappings'], true);
    $cos_str = is_array($cos) ? implode(", ", $cos) : 'NULL';
    
    if ($component !== $prev_component) {
        echo "\n$component:\n";
        $prev_component = $component;
    }
    echo "  COs: [$cos_str] | Columns: {$row['num_columns']}\n";
}

$stmt->close();

echo "\n" . str_repeat("-", 80) . "\n";
echo "Summary by CO:\n";

$sql = "SELECT 
    CAST(JSON_UNQUOTE(JSON_EXTRACT(gcc.co_mappings, '$[0]')) AS UNSIGNED) as co_num,
    gc.component_name,
    COUNT(DISTINCT gcc.id) as col_count
FROM grading_component_columns gcc
JOIN grading_components gc ON gc.id = gcc.component_id
WHERE gc.course_code = ?
GROUP BY co_num, gc.id, gc.component_name
ORDER BY co_num, gc.component_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $course_code);
$stmt->execute();
$result = $stmt->get_result();

$current_co = null;
while ($row = $result->fetch_assoc()) {
    $co = $row['co_num'];
    if ($co !== $current_co) {
        echo "\nCO$co:\n";
        $current_co = $co;
    }
    echo "  - {$row['component_name']} ({$row['col_count']} columns)\n";
}
$stmt->close();
?>
