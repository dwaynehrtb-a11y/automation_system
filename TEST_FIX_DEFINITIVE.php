<?php
/**
 * DEFINITIVE FIX TEST
 * 
 * This script tests if the grade calculation bug is fixed
 * by simulating the exact calculation that happens in flexible_grading.js
 */

require_once 'config/db.php';

echo "=== GRADE CALCULATION BUG FIX - DEFINITIVE TEST ===\n\n";

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

// Get component configuration for this class
$stmt = $conn->prepare("
    SELECT id, component_name, percentage FROM grade_components 
    WHERE class_code = ? 
    ORDER BY percentage DESC
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

$components = [];
while ($row = $result->fetch_assoc()) {
    $components[$row['id']] = [
        'name' => $row['component_name'],
        'weight' => floatval($row['percentage'])
    ];
}
$stmt->close();

echo "CLASS COMPONENTS:\n";
foreach ($components as $id => $comp) {
    echo "  - {$comp['name']}: {$comp['weight']}%\n";
}
echo "\n";

// Get actual component scores
$stmt = $conn->prepare("
    SELECT 
        component_id,
        score,
        max_score,
        term_type
    FROM grade_component_items 
    WHERE student_id = ? AND class_code = ?
    ORDER BY term_type, component_id
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

$component_scores = [];
while ($row = $result->fetch_assoc()) {
    $term = $row['term_type'];
    if (!isset($component_scores[$term])) {
        $component_scores[$term] = [];
    }
    $component_scores[$term][] = [
        'comp_id' => $row['component_id'],
        'score' => floatval($row['score']),
        'max' => floatval($row['max_score'])
    ];
}
$stmt->close();

// Calculate using CORRECT formula (what should happen with fix)
echo "MANUAL CALCULATION WITH CORRECT FORMULA:\n";
echo "(Simulating: const finalGrade = totalWeightedScore;)\n\n";

$calculations = [];

foreach ($component_scores as $term => $scores) {
    echo strtoupper($term) . ":\n";
    
    $totalWeightedScore = 0;
    $totalWeight = 0;
    
    foreach ($scores as $score_data) {
        $comp_id = $score_data['comp_id'];
        $comp_name = $components[$comp_id]['name'] ?? "Unknown";
        $comp_weight = $components[$comp_id]['weight'] ?? 0;
        
        $score = $score_data['score'];
        $max = $score_data['max'];
        
        // Calculate component percentage
        $compPct = ($max > 0) ? ($score / $max) * 100 : 0;
        
        // Calculate weighted score
        $weighted = $compPct * ($comp_weight / 100);
        
        echo "  $comp_name: $score/$max = " . number_format($compPct, 2) . "% × {$comp_weight}% = " . number_format($weighted, 2) . "\n";
        
        $totalWeightedScore += $weighted;
        $totalWeight += $comp_weight;
    }
    
    // CORRECT FORMULA: Just return totalWeightedScore (it's already weighted)
    $correctResult = $totalWeightedScore;
    
    echo "  CORRECT RESULT: " . number_format($correctResult, 2) . "%\n";
    echo "  (totalWeightedScore = " . number_format($totalWeightedScore, 2) . ", already 0-100%)\n\n";
    
    $calculations[$term] = $correctResult;
}

// Get stored values from database
echo "DATABASE STORED VALUES:\n";
$stmt = $conn->prepare("
    SELECT midterm_percentage, finals_percentage, term_percentage 
    FROM grade_term 
    WHERE student_id = ? AND class_code = ?
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $stored_midterm = floatval($row['midterm_percentage']);
    $stored_finals = floatval($row['finals_percentage']);
    $stored_term = floatval($row['term_percentage']);
    
    echo "  Midterm: " . number_format($stored_midterm, 2) . "%\n";
    echo "  Finals: " . number_format($stored_finals, 2) . "%\n";
    echo "  Term: " . number_format($stored_term, 2) . "%\n\n";
} else {
    echo "  No grades found in database\n\n";
    $stmt->close();
    exit;
}
$stmt->close();

// COMPARISON
echo "=== VERDICT ===\n\n";

$midterm_expected = $calculations['midterm'] ?? null;
$finals_expected = $calculations['finals'] ?? null;

if ($midterm_expected !== null) {
    echo "MIDTERM:\n";
    echo "  Expected (calculated): " . number_format($midterm_expected, 2) . "%\n";
    echo "  Stored in DB: " . number_format($stored_midterm, 2) . "%\n";
    
    $midterm_match = abs($midterm_expected - $stored_midterm) < 0.5;
    echo "  Match: " . ($midterm_match ? "✓ YES (FIX WORKING)" : "✗ NO (FIX NOT WORKING or different data)") . "\n\n";
}

if ($finals_expected !== null) {
    echo "FINALS:\n";
    echo "  Expected (calculated): " . number_format($finals_expected, 2) . "%\n";
    echo "  Stored in DB: " . number_format($stored_finals, 2) . "%\n";
    
    $finals_match = abs($finals_expected - $stored_finals) < 0.5;
    echo "  Match: " . ($finals_match ? "✓ YES (FIX WORKING)" : "✗ NO (FIX NOT WORKING or different data)") . "\n\n";
}

// Final recommendation
echo "=== CONCLUSION ===\n";
if (isset($midterm_match) && isset($finals_match) && $midterm_match && $finals_match) {
    echo "✅ FIX IS WORKING CORRECTLY\n";
    echo "The database values match the calculated values.\n";
    echo "The formula is working as intended.\n";
} else {
    echo "⚠️  VALUES DON'T MATCH\n";
    echo "This could mean:\n";
    echo "  1. Faculty entered different component scores than calculated\n";
    echo "  2. Old JavaScript code was used (browser didn't load fix)\n";
    echo "  3. Direct percentage entry instead of components\n\n";
    echo "ACTION: Have faculty:\n";
    echo "  1. Hard refresh page (Ctrl+Shift+R)\n";
    echo "  2. Re-enter grades\n";
    echo "  3. Save and check database again\n";
}

?>
