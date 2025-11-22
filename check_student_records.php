<?php
require_once 'config/db.php';

// Check how many records exist for this student
$student_id = '2022-118764';
$class_code = 'CCPRGG1L';

// First, let's see if the student exists
$query1 = "SELECT COUNT(*) as count FROM student_flexible_grades WHERE student_id = ?";
$stmt1 = $conn->prepare($query1);
$stmt1->bind_param('s', $student_id);
$stmt1->execute();
$result1 = $stmt1->get_result();
$row1 = $result1->fetch_assoc();
echo "Total flexible grades for student $student_id: " . $row1['count'] . "\n\n";

// Check if they have grades in that specific class
$query2 = "SELECT COUNT(*) as count FROM student_flexible_grades WHERE student_id = ? AND class_code = ?";
$stmt2 = $conn->prepare($query2);
$stmt2->bind_param('ss', $student_id, $class_code);
$stmt2->execute();
$result2 = $stmt2->get_result();
$row2 = $result2->fetch_assoc();
echo "Flexible grades for $student_id in $class_code: " . $row2['count'] . "\n\n";

// Show all records with recent updates
$query3 = "
    SELECT sfg.id, sfg.student_id, sfg.class_code, sfg.column_id, sfg.status, sfg.updated_at
    FROM student_flexible_grades sfg
    ORDER BY sfg.updated_at DESC
    LIMIT 5
";
$result3 = $conn->query($query3);
echo "Most recently updated flexible grades:\n";
while ($row = $result3->fetch_assoc()) {
    echo "Student: " . $row['student_id'] . ", Class: " . $row['class_code'] . ", Column: " . $row['column_id'] . ", Status: " . ($row['status'] ?: 'NULL') . ", Updated: " . $row['updated_at'] . "\n";
}

$stmt1->close();
$stmt2->close();
?>
