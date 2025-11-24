<?php
require_once '../../config/db.php';

$sys_proto_col_id = 138;

// Direct check
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM student_flexible_grades WHERE column_id = ?");
$stmt->bind_param('i', $sys_proto_col_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo "Total grades for column 138: " . $result['cnt'] . "\n";

if ($result['cnt'] > 0) {
    echo "\nGrades exist:\n";
    $stmt = $conn->prepare("
        SELECT sfg.student_id, sfg.raw_score, s.name
        FROM student_flexible_grades sfg
        JOIN students s ON s.id = sfg.student_id
        WHERE sfg.column_id = 138
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        echo "- Student {$row['student_id']} ({$row['name']}): {$row['raw_score']}\n";
    }
}

// Check the class_enrollments for this class
echo "\n=== Students enrolled in 24_T2_CCPRGG1L_INF222 ===\n";
$class_code = '24_T2_CCPRGG1L_INF222';

$stmt = $conn->prepare("
    SELECT ce.student_id, s.name, ce.status
    FROM class_enrollments ce
    JOIN students s ON s.id = ce.student_id
    WHERE ce.class_code = ? AND ce.status = 'enrolled'
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "Found " . $result->num_rows . " enrolled students:\n";
while ($row = $result->fetch_assoc()) {
    echo "- Student {$row['student_id']}: {$row['name']}\n";
}
?>
