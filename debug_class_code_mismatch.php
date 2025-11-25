<?php
/**
 * DEBUG: Class Code Mismatch Investigation
 */
require_once 'config/db.php';

$student_id = '2025-276819';

echo "=== CLASS CODE MISMATCH DEBUG ===\n\n";

// Step 1: Check what we're actually looking for
echo "Step 1: Looking for variations of CCPRGG1L\n\n";

$variations = [
    'CCPRGG1L',
    '25_T2_CCPRGG1L_INF223',
    'CCPRGG1L_INF223',
    'ccprgg1l',
];

foreach ($variations as $code) {
    $stmt = $conn->prepare("
        SELECT ce.class_code, c.course_code, c.section
        FROM class_enrollments ce
        JOIN class c ON ce.class_code = c.class_code
        WHERE ce.student_id = ? AND (ce.class_code = ? OR c.course_code = ?)
    ");
    $stmt->bind_param('sss', $student_id, $code, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "✅ FOUND with code: $code\n";
        echo "   class_code: {$row['class_code']}\n";
        echo "   course_code: {$row['course_code']}\n";
        echo "   section: {$row['section']}\n\n";
    }
    $stmt->close();
}

// Step 2: Get ALL class codes for this student
echo "Step 2: ALL class_code values in database for this student\n";
$stmt = $conn->prepare("
    SELECT ce.class_code, COUNT(*) as count
    FROM class_enrollments ce
    WHERE ce.student_id = ?
    GROUP BY ce.class_code
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo "  • {$row['class_code']}\n";
}
$stmt->close();

// Step 3: Check if enrollment actually exists
echo "\nStep 3: Direct query with exact class codes\n";
$stmt = $conn->prepare("
    SELECT class_code, course_code, section, student_id 
    FROM class_enrollments ce
    WHERE student_id = ?
    LIMIT 5
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();

echo "Enrollments found: " . $result->num_rows . "\n";
while ($row = $result->fetch_assoc()) {
    echo "  Student: {$row['student_id']}\n";
    echo "  Class Code: {$row['class_code']}\n";
    echo "  Course: {$row['course_code']}\n";
    echo "  Section: {$row['section']}\n";
    echo "\n";
}
$stmt->close();

// Step 4: Check if there's data in grade_term for ANY of their classes
echo "Step 4: Grade records for this student\n";
$stmt = $conn->prepare("
    SELECT gt.class_code, gt.student_id, gt.term_grade, gt.is_encrypted
    FROM grade_term gt
    WHERE gt.student_id = ?
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "  ❌ NO grade records for this student\n";
} else {
    echo "  Found " . $result->num_rows . " grade records:\n";
    while ($row = $result->fetch_assoc()) {
        echo "    • Class: {$row['class_code']}\n";
        echo "      Term Grade: {$row['term_grade']}\n";
        echo "      Is Encrypted: {$row['is_encrypted']}\n";
    }
}
$stmt->close();

// Step 5: Show the exact queries the student API would use
echo "\nStep 5: Student API queries\n";
echo "When student dashboard calls get_grades.php for CCPRGG1L:\n";
echo "Query: SELECT * FROM grade_term WHERE student_id = '2025-276819' AND class_code = 'CCPRGG1L'\n";

$stmt = $conn->prepare("SELECT * FROM grade_term WHERE student_id = ? AND class_code = 'CCPRGG1L'");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();
echo "Result rows: " . $result->num_rows . "\n";
$stmt->close();

echo "\n=== DIAGNOSIS ===\n";
echo "The database enrollment and the displayed roster may not match.\n";
echo "Check if class_code format differs between display and storage.\n";

?>
