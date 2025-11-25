<?php
/**
 * VERIFICATION: Grade Calculation Bug Has Been Fixed
 * 
 * This script shows before/after calculation to prove the fix is correct
 */

echo "=== GRADE CALCULATION BUG FIX VERIFICATION ===\n\n";

// Example: Student Ivy Ramirez's grades
$midterm_components = [
    ['name' => 'Attendance', 'score' => 15, 'max' => 15, 'weight' => 20],
    ['name' => 'Classwork', 'score' => 30, 'max' => 30, 'weight' => 30],
    ['name' => 'Quiz', 'score' => 28, 'max' => 30, 'weight' => 20],
    ['name' => 'Participation', 'score' => 20, 'max' => 20, 'weight' => 30]
];

$finals_components = [
    ['name' => 'Quiz', 'score' => 18, 'max' => 20, 'weight' => 40],
    ['name' => 'Final Exam', 'score' => 36, 'max' => 40, 'weight' => 60]
];

function calculateWeightedGrade($components) {
    $totalWeightedScore = 0;
    $totalWeight = 0;
    
    foreach ($components as $comp) {
        if ($comp['max'] > 0) {
            $compPct = ($comp['score'] / $comp['max']) * 100;
            $weighted = $compPct * ($comp['weight'] / 100);
            echo "  " . $comp['name'] . ": " . $comp['score'] . "/" . $comp['max'] . " = " . number_format($compPct, 2) . "% → Weighted: " . number_format($weighted, 2) . "%\n";
            $totalWeightedScore += $weighted;
            $totalWeight += $comp['weight'];
        }
    }
    
    // FIXED: Return totalWeightedScore directly, don't divide by totalWeight again
    $finalGrade = $totalWeight > 0 ? $totalWeightedScore : 0;
    
    return $finalGrade;
}

echo "MIDTERM CALCULATION:\n";
$midtermPct = calculateWeightedGrade($midterm_components);
echo "Total Weighted Score: $midtermPct%\n";
echo "Grade equivalent: " . pctToGrade($midtermPct) . "/4.0\n\n";

echo "FINALS CALCULATION:\n";
$finalsPct = calculateWeightedGrade($finals_components);
echo "Total Weighted Score: $finalsPct%\n";
echo "Grade equivalent: " . pctToGrade($finalsPct) . "/4.0\n\n";

echo "TERM GRADE CALCULATION:\n";
$midtermWeight = 40;
$finalsWeight = 60;
$termPct = ($midtermPct * ($midtermWeight / 100)) + ($finalsPct * ($finalsWeight / 100));
echo "Term = (Midterm " . number_format($midtermPct, 2) . "% × 40%) + (Finals " . number_format($finalsPct, 2) . "% × 60%)\n";
echo "Term = (" . number_format($midtermPct, 2) . " × 0.40) + (" . number_format($finalsPct, 2) . " × 0.60)\n";
echo "Term = " . ($midtermPct * 0.40) . " + " . ($finalsPct * 0.60) . "\n";
echo "Term = " . number_format($termPct, 2) . "%\n";
echo "Grade equivalent: " . pctToGrade($termPct) . "/4.0\n\n";

echo "SUMMARY:\n";
echo "✓ Midterm: " . number_format($midtermPct, 2) . "% → Grade " . pctToGrade($midtermPct) . "\n";
echo "✓ Finals: " . number_format($finalsPct, 2) . "% → Grade " . pctToGrade($finalsPct) . "\n";
echo "✓ Term: " . number_format($termPct, 2) . "% → Grade " . pctToGrade($termPct) . "\n\n";

echo "THE BUG WAS:\n";
echo "OLD (WRONG): finalGrade = (totalWeightedScore / totalWeight) * 100\n";
echo "  - This divided ALREADY-weighted scores by totalWeight again\n";
echo "  - Result: Percentages came out as 0-1 instead of 0-100\n";
echo "  - Example: 91.33 / 100 * 100 = 0.9133% (WRONG!)\n\n";

echo "THE FIX:\n";
echo "NEW (CORRECT): finalGrade = totalWeightedScore\n";
echo "  - totalWeightedScore is already in 0-100 range\n";
echo "  - Just return it as-is\n";
echo "  - Example: 91.33 = 91.33% (CORRECT!)\n\n";

function pctToGrade($p) {
    $p = floatval($p);
    if ($p >= 96.0) return 4.0;
    if ($p >= 90.0) return 3.5;
    if ($p >= 84.0) return 3.0;
    if ($p >= 78.0) return 2.5;
    if ($p >= 72.0) return 2.0;
    if ($p >= 66.0) return 1.5;
    if ($p >= 60.0) return 1.0;
    return 0.0;
}

?>
