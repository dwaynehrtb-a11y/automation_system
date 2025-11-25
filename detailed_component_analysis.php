<?php
/**
 * Component Analysis - Determine if 20%, 80%, 56% are correct
 */

require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== COMPONENT BREAKDOWN ANALYSIS ===\n\n";

// Get ALL components for this class first (to see weights)
$stmt = $conn->prepare("
    SELECT DISTINCT
        gc.id,
        gc.component_name,
        gc.percentage as component_weight
    FROM grade_components gc
    WHERE gc.class_code = ?
    ORDER BY gc.component_name
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

$component_map = [];
while ($row = $result->fetch_assoc()) {
    $component_map[$row['id']] = [
        'name' => $row['component_name'],
        'weight' => $row['percentage']
    ];
}
$stmt->close();

echo "Class Component Configuration:\n";
$total_weight = 0;
foreach ($component_map as $id => $comp) {
    echo "  - {$comp['name']}: {$comp['weight']}%\n";
    $total_weight += $comp['weight'];
}
echo "Total Weight: $total_weight%\n\n";

// Now get actual scores for this student
$stmt = $conn->prepare("
    SELECT 
        gci.id,
        gci.component_id,
        gci.score,
        gci.max_score,
        gci.term_type
    FROM grade_component_items gci
    WHERE gci.student_id = ? AND gci.class_code = ?
    ORDER BY gci.term_type, gci.component_id
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "❌ NO COMPONENT DATA FOUND\n";
    echo "This means either:\n";
    echo "  1. Grades were entered as direct percentages (not components)\n";
    echo "  2. Component data was cleared\n";
    echo "  3. Faculty entered grades in a different way\n";
    $stmt->close();
    exit;
}

$component_data = [];
while ($row = $result->fetch_assoc()) {
    $component_data[] = $row;
}
$stmt->close();

// Calculate by term
$terms = [];
foreach ($component_data as $comp) {
    $term = $comp['term_type'];
    if (!isset($terms[$term])) {
        $terms[$term] = [];
    }
    $terms[$term][] = $comp;
}

foreach ($terms as $term_name => $term_comps) {
    echo strtoupper($term_name) . " CALCULATION:\n";
    
    $total_weighted = 0;
    $total_weight_used = 0;
    
    foreach ($term_comps as $comp) {
        $comp_id = $comp['component_id'];
        $comp_name = $component_map[$comp_id]['name'] ?? "Unknown";
        $comp_weight = $component_map[$comp_id]['weight'] ?? 0;
        
        $score = floatval($comp['score']);
        $max_score = floatval($comp['max_score']);
        $pct = ($max_score > 0) ? ($score / $max_score) * 100 : 0;
        $weighted = $pct * ($comp_weight / 100);
        
        echo "  {$comp_name}: {$score}/{$max_score} = " . number_format($pct, 2) . "% × {$comp_weight}% = " . number_format($weighted, 2) . "\n";
        
        $total_weighted += $weighted;
        $total_weight_used += $comp_weight;
    }
    
    $final = ($total_weight_used > 0) ? $total_weighted : 0;
    echo "  TOTAL: " . number_format($final, 2) . "%\n";
    echo "  (Total weight: $total_weight_used%)\n\n";
}

echo "=== CONCLUSION ===\n";
echo "The percentages are CALCULATED from actual component scores.\n";
echo "If they match what faculty intended, the FIX IS WORKING ✓\n";
echo "If they DON'T match, faculty needs to re-enter with correct values.\n";

?>
