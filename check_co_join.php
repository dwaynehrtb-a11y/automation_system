<?php
require_once __DIR__ . '/config/db.php';

$class_code = '24_T2_CCPRGG1L_INF222';

// Get course code
$sql = "SELECT course_code FROM class_enrollments WHERE class_code = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();
$class = $result->fetch_assoc();
$stmt->close();

// Check what's being joined
$sql = "SELECT DISTINCT co.co_number, COUNT(*) cnt
        FROM course_outcomes co
        LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))
        WHERE co.course_code = ?
        GROUP BY co.co_number";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $class['course_code']);
$stmt->execute();
$result = $stmt->get_result();

echo "COs with component mappings:\n";
while ($row = $result->fetch_assoc()) {
    echo "  CO{$row['co_number']}: {$row['cnt']} mapped columns\n";
}
$stmt->close();
?>
