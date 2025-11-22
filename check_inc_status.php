<?php
require_once 'config/db.php';

// Check the student record we marked as INC
$student_id = '2022-118764';  // Mayo Suwail
$class_code = 'CCPRGG1L';

$query = "
    SELECT sfg.id, sfg.student_id, sfg.class_code, sfg.column_id, sfg.grade_value, sfg.status, sfg.updated_at
    FROM student_flexible_grades sfg
    WHERE sfg.student_id = ? AND sfg.class_code = ?
    ORDER BY sfg.updated_at DESC
    LIMIT 10
";

$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "Records for student $student_id in class $class_code:\n\n";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . "\n";
        echo "Column ID: " . $row['column_id'] . "\n";
        echo "Grade Value: " . $row['grade_value'] . "\n";
        echo "Status: " . ($row['status'] ?: 'NULL') . "\n";
        echo "Updated At: " . $row['updated_at'] . "\n";
        echo "---\n";
    }
} else {
    echo "No records found\n";
}

$stmt->close();
?>
