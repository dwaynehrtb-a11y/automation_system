<?php
require_once '../../config/db.php';

$class_code = '24_T2_CCPRGG1L_INF222';
$course_code = 'CCPRGG1L';

// Check if there are duplicate CO mappings
echo "=== Checking for duplicate CO entries ===\n";
$query = "SELECT co_number, co_description, COUNT(*) as cnt FROM course_outcomes WHERE course_code = ? GROUP BY co_number, co_description";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $course_code);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    if ($row['cnt'] > 1) {
        echo "DUPLICATE CO{$row['co_number']}: {$row['cnt']} entries\n";
    } else {
        echo "CO{$row['co_number']}: OK (1 entry)\n";
    }
}
$stmt->close();

echo "\n=== All COs in database ===\n";
$query = "SELECT co_id, co_number, co_description FROM course_outcomes WHERE course_code = ? ORDER BY co_id";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $course_code);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    echo "ID {$row['co_id']}: CO{$row['co_number']} - {$row['co_description']}\n";
}
$stmt->close();
?>
