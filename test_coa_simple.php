<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';

require_once __DIR__ . '/config/db.php';

$class_code = '24_T2_CCPRGG1L_INF222';
$faculty_id = 114;

echo "Testing COA generation...\n";
echo "Class Code: $class_code\n";
echo "Faculty ID: $faculty_id\n\n";

// Get class info
$stmt = $conn->prepare("SELECT * FROM class WHERE class_code = ?");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$class) {
    echo "ERROR: Class not found\n";
    exit;
}

echo "✓ Class found: ID=" . $class['id'] . "\n";
echo "✓ Course Code: " . $class['course_code'] . "\n";

// Get course info
$stmt = $conn->prepare("SELECT * FROM subjects WHERE course_code = ?");
$stmt->bind_param("s", $class['course_code']);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "✓ Subject Title: " . ($subject['course_title'] ?? 'N/A') . "\n";

// Get faculty name
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "✓ Faculty: " . ($faculty['name'] ?? 'N/A') . "\n\n";

// Get course outcomes
$stmt = $conn->prepare("SELECT co_number FROM course_outcomes WHERE course_code = ?");
$stmt->bind_param("s", $class['course_code']);
$stmt->execute();
$res = $stmt->get_result();
$cos = [];
while ($row = $res->fetch_assoc()) {
    $cos[] = $row['co_number'];
}
$stmt->close();

echo "✓ Course Outcomes: " . implode(", ", $cos) . "\n\n";

// Test HTML generation
$html = '<html><body><h1>NU LIPA</h1><p>COA Report for ' . $class_code . '</p></body></html>';

echo "Generated HTML:\n";
echo "- Length: " . strlen($html) . " bytes\n";
echo "- Contains DOCTYPE: " . (strpos($html, 'DOCTYPE') ? 'YES' : 'NO') . "\n";
echo "- Contains NU LIPA: " . (strpos($html, 'NU LIPA') ? 'YES' : 'NO') . "\n";

$response = json_encode(['success' => true, 'html' => $html, 'class_code' => $class_code]);
echo "\n✓ JSON Response Length: " . strlen($response) . " bytes\n";

file_put_contents(__DIR__ . '/test_coa_final.html', $html);
echo "✓ HTML saved to test_coa_final.html\n";
?>
