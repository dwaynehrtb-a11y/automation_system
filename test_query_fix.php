<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/encryption.php';

// Simulate the query
$class_code = '24_T2_CCPRGG1L_INF222';

// Get class
$stmt = $conn->prepare("SELECT * FROM class WHERE class_code = ?");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$class) {
    echo "Class not found\n";
    exit;
}

echo "Class found\n";
echo "Course code: " . $class['course_code'] . "\n\n";

// Test the performance query with correct column names
$query = "SELECT 
            co.co_number,
            gc.component_name AS assessment_name,
            gcc.performance_target,
            COUNT(DISTINCT CASE 
                WHEN sfg.raw_score >= (gcc.performance_target / 100 * gcc.max_score) 
                THEN sfg.student_id 
            END) AS students_met_target,
            COUNT(DISTINCT sfg.student_id) AS total_students
        FROM course_outcomes co
        LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))
        LEFT JOIN grading_components gc ON gc.id = gcc.component_id
        LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
        LEFT JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id
        WHERE co.course_code = ?
             AND ce.status = 'enrolled'
        GROUP BY co.co_number, gc.id, gcc.id
        LIMIT 10";

echo "Testing query...\n";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo "ERROR: " . $conn->error . "\n";
    exit;
}

$stmt->bind_param("ss", $class_code, $class['course_code']);

if (!$stmt->execute()) {
    echo "EXECUTION ERROR: " . $stmt->error . "\n";
    exit;
}

$result = $stmt->get_result();
$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    echo "CO " . $row['co_number'] . ": " . $row['assessment_name'] . " - Success: " . $row['total_students'] . " students\n";
}

echo "\n✓ Query successful! Found $count records\n";
$stmt->close();
?>
