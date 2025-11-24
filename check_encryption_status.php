<?php
/**
 * Quick Diagnosis Script - Check Grade Encryption Status
 */
require_once 'config/db.php';

$class_code = 'CCPRGG1L';

// Check grade_term table
echo "=== GRADE ENCRYPTION STATUS FOR $class_code ===\n\n";

$query = "SELECT student_id, is_encrypted, term_grade FROM grade_term WHERE class_code = ? LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "Student ID\t\tIs Encrypted\tTerm Grade (first 20 chars)\n";
echo str_repeat("-", 80) . "\n";

while ($row = $result->fetch_assoc()) {
    $encrypted_flag = $row['is_encrypted'] === null ? 'NULL' : ($row['is_encrypted'] == 1 ? 'YES (1)' : 'NO (0)');
    $term_grade_preview = isset($row['term_grade']) ? substr($row['term_grade'], 0, 20) : 'NULL';
    echo $row['student_id'] . "\t\t" . $encrypted_flag . "\t\t" . $term_grade_preview . "\n";
}

$stmt->close();

// Check visibility_status table
echo "\n\n=== VISIBILITY STATUS FOR $class_code ===\n\n";

$query2 = "SELECT student_id, grade_visibility FROM grade_visibility_status WHERE class_code = ? LIMIT 5";
$stmt2 = $conn->prepare($query2);
$stmt2->bind_param("s", $class_code);
$stmt2->execute();
$result2 = $stmt2->get_result();

echo "Student ID\t\tVisibility Status\n";
echo str_repeat("-", 50) . "\n";

if ($result2->num_rows === 0) {
    echo "No visibility records found - this might be the issue!\n";
} else {
    while ($row = $result2->fetch_assoc()) {
        echo $row['student_id'] . "\t\t" . $row['grade_visibility'] . "\n";
    }
}

$stmt2->close();

// Check encryption status summary
echo "\n\n=== ENCRYPTION STATUS SUMMARY FOR $class_code ===\n\n";

$query3 = "SELECT is_encrypted, COUNT(*) as count FROM grade_term WHERE class_code = ? GROUP BY is_encrypted";
$stmt3 = $conn->prepare($query3);
$stmt3->bind_param("s", $class_code);
$stmt3->execute();
$result3 = $stmt3->get_result();

echo "Is Encrypted\tCount\n";
echo str_repeat("-", 30) . "\n";

while ($row = $result3->fetch_assoc()) {
    $status = $row['is_encrypted'] === null ? 'NULL' : ($row['is_encrypted'] == 1 ? 'YES (1)' : 'NO (0)');
    echo $status . "\t\t" . $row['count'] . "\n";
}

$stmt3->close();

echo "\n✓ Diagnostic complete.\n";
?>
