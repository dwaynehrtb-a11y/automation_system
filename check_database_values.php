<?php
/**
 * Check what's actually in the database
 */

require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== DATABASE GRADE VALUES ===\n\n";

$stmt = $conn->prepare("
    SELECT 
        id,
        term_grade,
        midterm_percentage,
        finals_percentage,
        term_percentage,
        is_encrypted,
        created_at,
        updated_at
    FROM grade_term 
    WHERE student_id = ? AND class_code = ?
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Grade record found (ID: {$row['id']}):\n";
    echo "  Midterm: " . $row['midterm_percentage'] . "%\n";
    echo "  Finals: " . $row['finals_percentage'] . "%\n";
    echo "  Term: " . $row['term_percentage'] . "%\n";
    echo "  Term Grade: " . $row['term_grade'] . "\n";
    echo "  Encrypted: " . $row['is_encrypted'] . "\n";
    echo "  Created: " . $row['created_at'] . "\n";
    echo "  Updated: " . $row['updated_at'] . "\n\n";
    
    echo "ANALYSIS:\n";
    if ($row['midterm_percentage'] < 1 && $row['midterm_percentage'] > 0) {
        echo "❌ BUG CONFIRMED: Midterm stored as decimal (0-1 range)\n";
        echo "   This means the OLD buggy formula was used!\n";
    } else if ($row['midterm_percentage'] >= 20) {
        echo "⚠️  Different values than expected (20%, 80%, 56%)\n";
        echo "   Faculty may have entered different component scores\n";
    }
} else {
    echo "No grade record found (should have been cleared)\n";
}
$stmt->close();

echo "\n=== CHECKING COMPONENT DATA ===\n";

$stmt = $conn->prepare("
    SELECT 
        gci.id,
        gci.component_id,
        gc.component_name,
        gci.score,
        gci.max_score,
        gci.term_type
    FROM grade_component_items gci
    JOIN grade_components gc ON gci.component_id = gc.id
    WHERE gci.student_id = ? AND gci.class_code = ?
    ORDER BY gci.term_type, gc.component_name
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "\nComponent scores found:\n";
    $current_term = '';
    while ($row = $result->fetch_assoc()) {
        if ($row['term_type'] !== $current_term) {
            $current_term = $row['term_type'];
            echo "\n" . strtoupper($current_term) . ":\n";
        }
        $pct = ($row['score'] / $row['max_score']) * 100;
        echo "  {$row['component_name']}: {$row['score']}/{$row['max_score']} = " . number_format($pct, 2) . "%\n";
    }
} else {
    echo "No component data found.\n";
}
$stmt->close();

?>
