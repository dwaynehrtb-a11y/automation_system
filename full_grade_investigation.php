<?php
/**
 * FULL INVESTIGATION: Why Faculty Shows 74.17% But Database Has 23.33%
 */
require_once 'config/db.php';

$student_id = '2025-276819';
$class_code_short = 'CCPRGG1L';
$class_code_full = '25_T2_CCPRGG1L_INF223';

echo "=== GRADE VALUE INVESTIGATION ===\n\n";

// Step 1: Find correct class code
echo "Step 1: Find the actual class_code value\n";
$stmt = $conn->prepare("
    SELECT DISTINCT class_code, course_code, section
    FROM class
    WHERE course_code = ? AND section = 'INF223'
    ORDER BY class_code DESC
");
$stmt->bind_param('s', $class_code_short);
$stmt->execute();
$result = $stmt->get_result();

$correct_class_code = null;
while ($row = $result->fetch_assoc()) {
    echo "  Found: {$row['class_code']}\n";
    if ($correct_class_code === null) {
        $correct_class_code = $row['class_code'];
    }
}
$stmt->close();

if ($correct_class_code === null) {
    echo "  ❌ Could not find class\n";
    exit;
}

echo "\nUsing class_code: $correct_class_code\n";

// Step 2: Check grade_term record
echo "\nStep 2: Check grade_term record for this student\n";
$stmt = $conn->prepare("
    SELECT 
        id,
        midterm_percentage,
        finals_percentage,
        term_percentage,
        term_grade,
        is_encrypted,
        created_at,
        updated_at
    FROM grade_term
    WHERE student_id = ? AND class_code = ?
");
$stmt->bind_param('ss', $student_id, $correct_class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "  ❌ NO RECORD\n";
    exit;
}

$grade_record = $result->fetch_assoc();
$stmt->close();

echo "  ✓ Record ID: {$grade_record['id']}\n";
echo "  Created: {$grade_record['created_at']}\n";
echo "  Updated: {$grade_record['updated_at']}\n";
echo "  Midterm %: {$grade_record['midterm_percentage']}\n";
echo "  Finals %: {$grade_record['finals_percentage']}\n";
echo "  Term %: {$grade_record['term_percentage']}\n";
echo "  Term Grade: {$grade_record['term_grade']}\n";
echo "  Is Encrypted: {$grade_record['is_encrypted']}\n";

// Step 3: Check if there are multiple records
echo "\nStep 3: Check if there are MULTIPLE grade_term records\n";
$stmt = $conn->prepare("
    SELECT class_code, midterm_percentage, finals_percentage, term_grade, updated_at
    FROM grade_term
    WHERE student_id = ?
    ORDER BY updated_at DESC
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();

echo "  Total records: " . $result->num_rows . "\n";
$i = 1;
while ($row = $result->fetch_assoc()) {
    echo "\n  Record $i: {$row['class_code']}\n";
    echo "    Midterm: {$row['midterm_percentage']}%\n";
    echo "    Finals: {$row['finals_percentage']}%\n";
    echo "    Term Grade: {$row['term_grade']}\n";
    echo "    Updated: {$row['updated_at']}\n";
    $i++;
}
$stmt->close();

// Step 4: Check grade components
echo "\nStep 4: Grade components for this student\n";
$stmt = $conn->prepare("
    SELECT 
        gc.id,
        gc.component_name,
        gc.category,
        gc.weight,
        COUNT(gci.id) as item_count,
        AVG(CASE WHEN gci.max_score > 0 THEN (gci.score / gci.max_score * 100) ELSE 0 END) as avg_percentage
    FROM grade_components gc
    LEFT JOIN grade_component_items gci ON gc.id = gci.component_id AND gci.student_id = ?
    WHERE gc.class_code = ?
    GROUP BY gc.id
    ORDER BY gc.category, gc.component_name
");
$stmt->bind_param('ss', $student_id, $correct_class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "  No component records\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['component_name']} ({$row['weight']}%): {$row['avg_percentage']}%\n";
    }
}
$stmt->close();

// Step 5: Manual calculation
echo "\nStep 5: Manual calculation of what term grade should be\n";
$midterm_pct = floatval($grade_record['midterm_percentage'] ?? 0);
$finals_pct = floatval($grade_record['finals_percentage'] ?? 0);

// Assuming 40% midterm, 60% finals
$calculated_term = ($midterm_pct * 0.40) + ($finals_pct * 0.60);

echo "  Database midterm %: $midterm_pct\n";
echo "  Database finals %: $finals_pct\n";
echo "  Calculation: ($midterm_pct * 0.40) + ($finals_pct * 0.60) = $calculated_term\n";
echo "  Database term %: {$grade_record['term_percentage']}\n";
echo "  Database term grade: {$grade_record['term_grade']}\n";

if (abs($calculated_term - floatval($grade_record['term_percentage'])) > 0.01) {
    echo "  ⚠️ MISMATCH: Calculated $calculated_term but DB has {$grade_record['term_percentage']}\n";
}

?>
