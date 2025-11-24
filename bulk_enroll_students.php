<?php
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

$class_code = $_POST['class_code'] ?? '';
$student_ids = $_POST['student_ids'] ?? [];

if (empty($class_code) || empty($student_ids)) {
    die('Missing required data');
}

echo "<h2>Bulk Enrollment Results</h2>";
echo "<p>Class: <strong>$class_code</strong></p>";

$success_count = 0;
$error_count = 0;

foreach ($student_ids as $student_id) {
    // Check if already enrolled
    $check = $conn->prepare("SELECT id FROM class_enrollments WHERE class_code = ? AND student_id = ?");
    $check->bind_param("ss", $class_code, $student_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p style='color: orange;'>⚠️ $student_id - Already enrolled (updated status to 'enrolled')</p>";
        // Update status if exists
        $update = $conn->prepare("UPDATE class_enrollments SET status = 'enrolled' WHERE class_code = ? AND student_id = ?");
        $update->bind_param("ss", $class_code, $student_id);
        $update->execute();
        $success_count++;
    } else {
        // Insert new enrollment
        $insert = $conn->prepare("INSERT INTO class_enrollments (class_code, student_id, status, enrolled_at) VALUES (?, ?, 'enrolled', NOW())");
        $insert->bind_param("ss", $class_code, $student_id);
        
        if ($insert->execute()) {
            echo "<p style='color: green;'>✓ $student_id - Successfully enrolled</p>";
            $success_count++;
        } else {
            echo "<p style='color: red;'>✗ $student_id - Error: " . $insert->error . "</p>";
            $error_count++;
        }
    }
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p><strong style='color: green;'>✓ Success: $success_count</strong></p>";
echo "<p><strong style='color: red;'>✗ Errors: $error_count</strong></p>";

echo "<br>";
echo "<a href='check_expected_enrollments.php' style='padding: 10px 20px; background: #6b7280; color: white; text-decoration: none; border-radius: 6px;'>← Back</a>";
echo " ";
echo "<a href='dashboards/faculty_dashboard.php' style='padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px;'>Go to Faculty Dashboard →</a>";
