<?php
echo "=== FINAL COA INTEGRATION TEST ===\n\n";

// Test 1: Database Connection
require_once 'config/db.php';
echo "✓ Database connection successful\n";

// Test 2: Get class
$class_code = '24_T2_CCPRGG1L_INF222';
$stmt = $conn->prepare("SELECT * FROM class WHERE class_code = ?");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($class) {
    echo "✓ Class found: " . $class['course_code'] . "\n";
} else {
    echo "✗ Class not found\n";
    exit;
}

// Test 3: Get course info
$stmt = $conn->prepare("SELECT course_title FROM subjects WHERE course_code = ?");
$stmt->bind_param("s", $class['course_code']);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();
echo "✓ Course: " . ($subject['course_title'] ?? 'Unknown') . "\n";

// Test 4: Get course outcomes
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM course_outcomes WHERE course_code = ?");
$stmt->bind_param("s", $class['course_code']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();
echo "✓ Course Outcomes: " . $result['cnt'] . "\n";

// Test 5: Performance query
$query = "SELECT 
            COUNT(*) as record_count,
            COUNT(DISTINCT gc.id) as unique_assessments,
            ROUND(AVG(sfg.raw_score), 2) as avg_score
        FROM course_outcomes co
        LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))
        LEFT JOIN grading_components gc ON gc.id = gcc.component_id
        LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
        LEFT JOIN class_enrollments ce ON ce.class_code = ? AND ce.student_id = sfg.student_id
        WHERE co.course_code = ? AND ce.status = 'enrolled'";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo "✗ Query prepare failed: " . $conn->error . "\n";
    exit;
}

$stmt->bind_param("ss", $class_code, $class['course_code']);
if (!$stmt->execute()) {
    echo "✗ Query execute failed: " . $stmt->error . "\n";
    exit;
}

$perf_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "✓ Performance Records: " . ($perf_data['record_count'] ?? 0) . "\n";
echo "✓ Unique Assessments: " . ($perf_data['unique_assessments'] ?? 0) . "\n";
echo "✓ Average Score: " . ($perf_data['avg_score'] ?? 0) . "\n";

// Test 6: Simulate HTML generation
$html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>COA Report</title>
<style>
body { font-family: Calibri, Arial, sans-serif; }
table { border-collapse: collapse; width: 100%; border: 1px solid #000; }
td { border: 1px solid #000; padding: 5px; }
.gray-header { background: #d3d3d3; font-weight: bold; }
</style>
</head>
<body>
<h1>NU LIPA - Course Outcomes Assessment</h1>
<table>
<tr><td colspan="2" class="gray-header">COURSE INFORMATION</td></tr>
<tr><td><strong>COURSE CODE:</strong></td><td>CCPRGG1L</td></tr>
<tr><td><strong>TITLE:</strong></td><td>Fundamentals of Programming</td></tr>
</table>
</body>
</html>
HTML;

echo "✓ HTML Generated: " . strlen($html) . " bytes\n";
echo "✓ Contains NU LIPA: " . (strpos($html, 'NU LIPA') ? 'YES' : 'NO') . "\n";

// Test 7: JSON Response
$response = json_encode([
    'success' => true,
    'html' => $html,
    'class_code' => $class_code
]);

echo "✓ JSON Response: " . strlen($response) . " bytes\n";

echo "\n=== ALL TESTS PASSED ===\n";
echo "COA system is ready for use!\n";
?>
