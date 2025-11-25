<?php
/**
 * Detailed Analysis: Did the Fix Work?
 */

require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== ANALYZING GRADE CALCULATION ===\n\n";

// Get component data
$stmt = $conn->prepare("
    SELECT 
        gci.id,
        gc.component_id,
        gc.component_name,
        gc.percentage as component_weight,
        gci.score,
        gci.max_score,
        gci.term_type
    FROM grade_component_items gci
    JOIN grade_components gc ON gci.component_id = gc.id
    WHERE gci.student_id = ? AND gci.class_code = ?
    ORDER BY gci.term_type, gc.component_id
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

$components = [];
while ($row = $result->fetch_assoc()) {
    $components[] = $row;
}
$stmt->close();

if (count($components) === 0) {
    echo "❌ No component data found\n";
    exit;
}

// Group by term and calculate
$termData = [];
foreach ($components as $comp) {
    $term = $comp['term_type'];
    if (!isset($termData[$term])) {
        $termData[$term] = ['components' => [], 'total_weight' => 0];
    }
    $termData[$term]['components'][] = $comp;
    $termData[$term]['total_weight'] += $comp['component_weight'];
}

foreach ($termData as $term => $data) {
    echo strtoupper($term) . " CALCULATION:\n";
    
    $totalWeightedScore = 0;
    
    foreach ($data['components'] as $comp) {
        $compPct = ($comp['score'] / $comp['max_score']) * 100;
        $weighted = $compPct * ($comp['component_weight'] / 100);
        
        echo "  {$comp['component_name']}: {$comp['score']}/{$comp['max_score']} = " . number_format($compPct, 2) . "% × {$comp['component_weight']}% = " . number_format($weighted, 2) . "\n";
        
        $totalWeightedScore += $weighted;
    }
    
    $correctResult = $totalWeightedScore;
    echo "\n  ✓ CORRECT (with fix): $" . number_format($correctResult, 2) . "%\n";
    
    // What the old formula would produce
    $oldFormula = ($totalWeightedScore / $data['total_weight']) * 100;
    echo "  ✗ OLD FORMULA: " . number_format($oldFormula, 2) . "%\n";
    
    echo "\n";
}

// Now check what's actually in the database
echo "=== DATABASE VALUES ===\n";
$stmt = $conn->prepare("
    SELECT 
        midterm_percentage,
        finals_percentage,
        term_percentage
    FROM grade_term 
    WHERE student_id = ? AND class_code = ?
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "Midterm in DB: " . $row['midterm_percentage'] . "%\n";
    echo "Finals in DB: " . $row['finals_percentage'] . "%\n";
    echo "Term in DB: " . $row['term_percentage'] . "%\n";
}
$stmt->close();

?>
