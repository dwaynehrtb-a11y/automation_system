<?php
session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';

require_once 'config/db.php';

// Direct simulation of endpoint
$_GET['class_code'] = '24_T2_CCPRGG1L_INF222';
$class_code = $_GET['class_code'];
$faculty_id = 114;

// Get class
$stmt = $conn->prepare("SELECT * FROM class WHERE class_code = ?");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$class) {
    echo json_encode(['success' => false, 'message' => 'Class not found']);
    exit;
}

// Get faculty
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get course info
$stmt = $conn->prepare("SELECT course_title, course_desc FROM subjects WHERE course_code = ?");
$stmt->bind_param("s", $class['course_code']);
$stmt->execute();
$course_info = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Get performance data (simplified for test)
$stmt = $conn->prepare("SELECT 
    co.co_number,
    gc.component_name AS assessment_name,
    gcc.performance_target,
    COUNT(DISTINCT sfg.student_id) as students_met_target,
    COUNT(DISTINCT ce.student_id) as total_students,
    ROUND(COUNT(DISTINCT sfg.student_id) * 100 / NULLIF(COUNT(DISTINCT ce.student_id), 0)) as success_rate
FROM course_outcomes co
LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id AND sfg.raw_score >= (gcc.performance_target / 100 * gcc.max_score)
LEFT JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id
WHERE co.course_code = ?
GROUP BY co.co_number, gc.id
LIMIT 5");
$stmt->bind_param("ss", $class_code, $class['course_code']);
$stmt->execute();
$coPerf = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $coPerf[] = $row;
}
$stmt->close();

echo "CLASS: " . $class['course_code'] . "\n";
echo "COURSE: " . ($course_info['course_title'] ?? 'N/A') . "\n";
echo "FACULTY: " . ($faculty['name'] ?? 'N/A') . "\n";
echo "RECORDS: " . count($coPerf) . "\n\n";

// Show first record
if (count($coPerf) > 0) {
    echo "Sample Record:\n";
    print_r($coPerf[0]);
}

// Generate minimal HTML
$html = '<html><body>';
$html .= '<h1>NU LIPA</h1>';
$html .= '<h2>' . htmlspecialchars($class['course_code']) . '</h2>';
$html .= '<table border="1">';
$html .= '<tr><th>CO</th><th>Assessment</th><th>Target</th><th>Success</th></tr>';

foreach ($coPerf as $perf) {
    $html .= '<tr>';
    $html .= '<td>' . $perf['co_number'] . '</td>';
    $html .= '<td>' . $perf['assessment_name'] . '</td>';
    $html .= '<td>' . $perf['performance_target'] . '%</td>';
    $html .= '<td>' . $perf['success_rate'] . '%</td>';
    $html .= '</tr>';
}

$html .= '</table>';
$html .= '</body></html>';

echo "\nHTML Length: " . strlen($html) . " bytes\n";
echo "Contains NU LIPA: " . (strpos($html, 'NU LIPA') ? 'YES' : 'NO') . "\n";
echo "Contains TABLE: " . (strpos($html, '<table') ? 'YES' : 'NO') . "\n";
?>
