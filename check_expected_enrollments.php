<?php
require_once 'config/db.php';

$class_code = '25_T2_CTAPROJ1_INF223';

echo "<h2>Expected vs Actual Enrollments for $class_code</h2>";

// Check all students in the system
echo "<h3>All Students (Active + Pending):</h3>";
$stmt = $conn->prepare("
    SELECT student_id, CONCAT(last_name, ', ', first_name) as name, status
    FROM student
    WHERE status IN ('active', 'pending')
    ORDER BY student_id
");
$stmt->execute();
$result = $stmt->get_result();

$all_students = [];
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>#</th><th>Student ID</th><th>Name</th><th>Status</th></tr>";
$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    $all_students[] = $row;
    echo "<tr>";
    echo "<td>$count</td>";
    echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p><strong>Total students in system: $count</strong></p>";

// Check current enrollments
echo "<hr>";
echo "<h3>Currently Enrolled in $class_code:</h3>";
$stmt = $conn->prepare("
    SELECT ce.student_id, CONCAT(s.last_name, ', ', s.first_name) as name, ce.status
    FROM class_enrollments ce
    LEFT JOIN student s ON ce.student_id = s.student_id
    WHERE ce.class_code = ?
    ORDER BY ce.student_id
");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>#</th><th>Student ID</th><th>Name</th><th>Enrollment Status</th></tr>";
$enrolled_count = 0;
$enrolled_ids = [];
while ($row = $result->fetch_assoc()) {
    $enrolled_count++;
    $enrolled_ids[] = $row['student_id'];
    echo "<tr>";
    echo "<td>$enrolled_count</td>";
    echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p><strong>Currently enrolled: $enrolled_count</strong></p>";

// Show missing students
echo "<hr>";
echo "<h3>Students NOT Enrolled (should these be enrolled?):</h3>";
$missing = array_filter($all_students, function($s) use ($enrolled_ids) {
    return !in_array($s['student_id'], $enrolled_ids);
});

if (count($missing) > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Student ID</th><th>Name</th><th>Status</th></tr>";
    foreach ($missing as $student) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($student['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($student['name']) . "</td>";
        echo "<td>" . htmlspecialchars($student['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><strong>Missing: " . count($missing) . " students</strong></p>";
    
    echo "<hr>";
    echo "<h3>Auto-Enroll Missing Students?</h3>";
    echo "<p>If you want to enroll all " . count($missing) . " missing students, click below:</p>";
    echo "<form method='POST' action='bulk_enroll_students.php'>";
    echo "<input type='hidden' name='class_code' value='$class_code'>";
    foreach ($missing as $student) {
        echo "<input type='hidden' name='student_ids[]' value='" . htmlspecialchars($student['student_id']) . "'>";
    }
    echo "<button type='submit' style='padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer;'>";
    echo "Enroll All " . count($missing) . " Students";
    echo "</button>";
    echo "</form>";
} else {
    echo "<p style='color: green;'>✓ All students are already enrolled!</p>";
}
