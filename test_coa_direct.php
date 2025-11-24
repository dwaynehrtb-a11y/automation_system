<?php
// Direct test of the COA generation logic
session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';

require_once __DIR__ . '/config/db.php';

$class_code = '24_T2_CCPRGG1L_INF222';

// Get course code from class code
$class_parts = explode('_', $class_code);
$course_code = $class_parts[count($class_parts) - 1]; // Last part is course code

// Get class ID
$class_query = "SELECT class_id FROM classes WHERE CONCAT_WS('_', semester, academic_term, course_code, section_code) = ? LIMIT 1";
$class_stmt = $conn->prepare($class_query);
$class_stmt->bind_param("s", $class_code);
$class_stmt->execute();
$class_result = $class_stmt->get_result();
$class = $class_result->fetch_assoc();

if (!$class) {
    echo "Class not found\n";
    exit;
}

$class_id = $class['class_id'];

echo "=== COA GENERATION TEST ===\n";
echo "Class Code: $class_code\n";
echo "Class ID: $class_id\n";
echo "Course Code: $course_code\n\n";

// Get course information
$course_query = "SELECT course_code, title, description FROM courses WHERE course_code = ?";
$course_stmt = $conn->prepare($course_query);
$course_stmt->bind_param("s", $course_code);
$course_stmt->execute();
$course_result = $course_stmt->get_result();
$course_info = $course_result->fetch_assoc() ?? [];

echo "Course Title: " . ($course_info['title'] ?? 'N/A') . "\n";
echo "Course Code: " . ($course_info['course_code'] ?? 'N/A') . "\n\n";

// Get performance data
$performance_query = "SELECT 
    co.co_number,
    co.co_statement,
    gc.component_name,
    gcc.performance_target,
    COUNT(DISTINCT CASE WHEN sfg.raw_score >= (gcc.performance_target / 100 * gc.max_score) THEN sfg.student_id END) as students_met_target,
    COUNT(DISTINCT sfg.student_id) as total_students,
    ROUND(
        COALESCE(
            COUNT(DISTINCT CASE WHEN sfg.raw_score >= (gcc.performance_target / 100 * gc.max_score) THEN sfg.student_id END), 
            0
        ) / 
        NULLIF(COUNT(DISTINCT sfg.student_id), 0) * 100, 
        0
    ) as success_rate
FROM course_outcomes co
LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(co.co_number), '$')
LEFT JOIN grading_components gc ON gcc.grading_component_id = gc.grading_component_id
LEFT JOIN student_flexible_grades sfg ON gcc.grading_component_column_id = sfg.grading_component_column_id
LEFT JOIN class_enrollments ce ON sfg.student_id = ce.student_id AND ce.class_id = ?
WHERE co.course_code = ?
GROUP BY co.co_number, co.co_statement, gc.component_name, gcc.performance_target, gc.max_score
ORDER BY co.co_number, gc.component_name";

$perf_stmt = $conn->prepare($performance_query);
$perf_stmt->bind_param("is", $class_id, $course_code);
$perf_stmt->execute();
$perf_result = $perf_stmt->get_result();

if (!$perf_result) {
    echo "Performance Query Error: " . $conn->error . "\n";
    exit;
}

$coPerf = [];
while ($row = $perf_result->fetch_assoc()) {
    $coPerf[] = $row;
}

echo "Performance Records: " . count($coPerf) . "\n\n";

if (count($coPerf) > 0) {
    echo "Sample Data:\n";
    foreach ($coPerf as $idx => $row) {
        if ($idx < 3) {
            echo "  CO: " . $row['co_number'] . "\n";
            echo "  Assessment: " . $row['component_name'] . "\n";
            echo "  Success Rate: " . $row['success_rate'] . "%\n";
            echo "  Students Met/Total: " . $row['students_met_target'] . "/" . $row['total_students'] . "\n\n";
        }
    }
}

echo "Generating HTML...\n";

// Build HTML (same logic as endpoint)
$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>COA - ' . htmlspecialchars($class_code) . '</title>
<style>
table { border-collapse: collapse; width: 100%; margin-bottom: 8px; border: 1px solid #000; }
td { border: 1px solid #000; padding: 5px 4px; text-align: left; font-size: 10pt; }
.gray-header { background-color: #d3d3d3; font-weight: bold; text-align: center; color: #000; }
</style>
</head>
<body>';

$html .= '<div class="page">';
$html .= '<h2>NU LIPA - Course Outcomes Assessment</h2>';
$html .= '<table><tr><td colspan="2" class="gray-header">COURSE INFORMATION</td></tr>';
$html .= '<tr><td><strong>COURSE CODE:</strong></td><td>' . htmlspecialchars($course_code) . '</td></tr>';
$html .= '<tr><td><strong>TITLE:</strong></td><td>' . htmlspecialchars($course_info['title'] ?? 'N/A') . '</td></tr>';
$html .= '</table>';

$html .= '<p style="margin:12px 0 6px 0; font-weight:bold;">ASSESSMENT PERFORMANCE</p>';
$html .= '<table>';
$html .= '<tr class="gray-header"><td>CO#</td><td>ASSESSMENT</td><td>TARGET %</td><td>SUCCESS %</td></tr>';

foreach ($coPerf as $perf) {
    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($perf['co_number']) . '</td>';
    $html .= '<td>' . htmlspecialchars($perf['component_name'] ?? 'Unknown') . '</td>';
    $html .= '<td>' . $perf['performance_target'] . '%</td>';
    $html .= '<td><strong>' . round($perf['success_rate'], 0) . '%</strong></td>';
    $html .= '</tr>';
}

$html .= '</table></div></body></html>';

echo "\n✓ HTML Generated\n";
echo "HTML Length: " . strlen($html) . " bytes\n";
echo "Contains NU LIPA: " . (strpos($html, 'NU LIPA') ? 'YES' : 'NO') . "\n";
echo "Contains tables: " . (substr_count($html, '<table') > 0 ? 'YES' : 'NO') . "\n";
echo "Data rows: " . substr_count($html, '<tr>') . "\n";

// Save HTML
file_put_contents(__DIR__ . '/test_coa_output.html', $html);
echo "\n✓ HTML saved to test_coa_output.html\n";

echo "\n=== FIRST 500 CHARACTERS ===\n";
echo substr($html, 0, 500) . "\n...\n";
?>
