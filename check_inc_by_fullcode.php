<?php
require_once 'config/db.php';

// Let's check with the actual full class code format
$student_id = '2022-118764';
$class_code = '24_T2_CCPRGG1L_INF222';

$query = "
    SELECT sfg.id, sfg.student_id, sfg.class_code, sfg.column_id, sfg.grade_value, sfg.status, sfg.updated_at,
           gc.column_name
    FROM student_flexible_grades sfg
    LEFT JOIN grading_columns gc ON sfg.column_id = gc.id
    WHERE sfg.student_id = ? AND sfg.class_code = ?
    ORDER BY sfg.updated_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "Records for student $student_id in class $class_code:\n";
echo "==========================================\n\n";

$inc_count = 0;
while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'inc') $inc_count++;
    
    echo "Column ID: " . $row['column_id'] . "\n";
    echo "Column Name: " . ($row['column_name'] ?: 'Unknown') . "\n";
    echo "Grade Value: " . $row['grade_value'] . "\n";
    echo "Status: " . ($row['status'] ?: 'NULL (submitted)') . "\n";
    echo "Updated At: " . $row['updated_at'] . "\n";
    echo "---\n";
}

echo "\nTotal INC components: " . $inc_count . "\n";

$stmt->close();
?>
