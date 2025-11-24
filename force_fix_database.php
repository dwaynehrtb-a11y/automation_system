<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Manual Database Update - Force Fix</h2>";
echo "<pre>";

echo "Finding CCPRGG1L class for student 2022-126653...\n";

// First find the exact class code
$find_class = "SELECT c.class_code FROM class_enrollments ce JOIN class c ON ce.class_code = c.class_code WHERE ce.student_id='2022-126653' AND c.course_code LIKE '%CCPRGG%' LIMIT 1";
$result = $conn->query($find_class);

if ($result->num_rows === 0) {
    echo "❌ No CCPRGG class found!\n";
    exit;
}

$class = $result->fetch_assoc();
$class_code = $class['class_code'];

echo "Found: $class_code\n\n";

// Get current values
echo "Current database values:\n";
$current = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' AND class_code=?";
$stmt = $conn->prepare($current);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$curr_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "  Midterm: " . $curr_row['midterm_percentage'] . "%\n";
echo "  Finals: " . $curr_row['finals_percentage'] . "%\n";
echo "  Term %: " . $curr_row['term_percentage'] . "%\n";
echo "  Grade: " . $curr_row['term_grade'] . "\n\n";

// Set the correct values directly
$new_midterm = 62.03;
$new_finals = 100.00;
$new_term = 84.81;
$new_grade = '3.0';

echo "Updating to correct values:\n";
echo "  Midterm: $new_midterm%\n";
echo "  Finals: $new_finals%\n";
echo "  Term %: $new_term%\n";
echo "  Grade: $new_grade\n\n";

// Execute update
$update = "UPDATE grade_term SET midterm_percentage=?, finals_percentage=?, term_percentage=?, term_grade=? WHERE student_id='2022-126653' AND class_code=?";
$stmt = $conn->prepare($update);

if (!$stmt) {
    echo "❌ Prepare failed: " . $conn->error . "\n";
    exit;
}

$stmt->bind_param("dddss", $new_midterm, $new_finals, $new_term, $new_grade, $class_code);

if ($stmt->execute()) {
    echo "✅ Update executed\n";
    echo "Rows affected: " . $stmt->affected_rows . "\n\n";
} else {
    echo "❌ Execute failed: " . $stmt->error . "\n";
    exit;
}

$stmt->close();

// Verify update
echo "Verifying update:\n";
$verify = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' AND class_code=?";
$stmt = $conn->prepare($verify);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$verify_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "  Midterm: " . $verify_row['midterm_percentage'] . "%\n";
echo "  Finals: " . $verify_row['finals_percentage'] . "%\n";
echo "  Term %: " . $verify_row['term_percentage'] . "%\n";
echo "  Grade: " . $verify_row['term_grade'] . "\n\n";

if ($verify_row['midterm_percentage'] == 62.03 && $verify_row['term_percentage'] == 84.81 && $verify_row['term_grade'] == '3.0') {
    echo "✅ ✅ ✅ DATABASE SUCCESSFULLY UPDATED!\n";
} else {
    echo "❌ Verification failed\n";
}

$conn->close();
?>
