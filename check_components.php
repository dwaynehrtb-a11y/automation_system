<?php
require_once __DIR__ . '/config/db.php';

// Get course code from class
$class_code = '24_T2_CCPRGG1L_INF222';
$sql = "SELECT course_code FROM class_enrollments WHERE class_code = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$course_code = $row['course_code'];
$stmt->close();

echo "Course Code: $course_code\n\n";

// Get all grading components for this course
$sql = "SELECT DISTINCT component_name FROM grading_components 
        WHERE course_code = ? 
        ORDER BY component_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $course_code);
$stmt->execute();
$result = $stmt->get_result();

echo "Grading Components:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - " . $row['component_name'] . "\n";
}
$stmt->close();

// Check CO mappings for Quizzes vs Quiz
$sql = "SELECT gc.component_name, gcc.co_mappings
        FROM grading_components gc
        JOIN grading_component_columns gcc ON gcc.component_id = gc.id
        WHERE gc.course_code = ? AND gc.component_name IN ('Quizzes', 'Quiz')
        ORDER BY gc.component_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $course_code);
$stmt->execute();
$result = $stmt->get_result();

echo "\nCO Mappings:\n";
while ($row = $result->fetch_assoc()) {
    $cos = json_decode($row['co_mappings']);
    echo "  " . $row['component_name'] . " -> COs: " . implode(", ", $cos) . "\n";
}
$stmt->close();
?>
