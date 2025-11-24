<?php
require_once 'config/db.php';

$class_code = '25_T2_CTAPROJ1_INF223';

echo "<h2>Student Status Analysis for $class_code</h2>";

// Check enrolled students and their statuses
$stmt = $conn->prepare("
    SELECT 
        ce.student_id,
        s.first_name,
        s.last_name,
        s.status as student_status,
        ce.status as enrollment_status
    FROM class_enrollments ce
    LEFT JOIN student s ON ce.student_id = s.student_id
    WHERE ce.class_code = ?
    ORDER BY s.last_name, s.first_name
");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Enrolled Students (from class_enrollments):</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Student ID</th><th>Name</th><th>Student Status</th><th>Enrollment Status</th></tr>";

$total = 0;
$enrolled_total = 0;
$active_total = 0;

while ($row = $result->fetch_assoc()) {
    $total++;
    if ($row['enrollment_status'] === 'enrolled') $enrolled_total++;
    if ($row['student_status'] === 'active') $active_total++;
    
    $status_color = $row['student_status'] === 'active' ? 'green' : 'orange';
    $enroll_color = $row['enrollment_status'] === 'enrolled' ? 'green' : 'red';
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) . "</td>";
    echo "<td style='color: $status_color; font-weight: bold;'>" . htmlspecialchars($row['student_status']) . "</td>";
    echo "<td style='color: $enroll_color; font-weight: bold;'>" . htmlspecialchars($row['enrollment_status']) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><strong>Total in class_enrollments: $total</strong></p>";
echo "<p><strong>With enrollment_status='enrolled': $enrolled_total</strong></p>";
echo "<p><strong>With student_status='active': $active_total</strong></p>";

// Check all students with status = 'active'
echo "<hr>";
echo "<h3>All Active Students in System:</h3>";

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM student WHERE status = 'active'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo "<p>Total active students in database: <strong>" . $row['total'] . "</strong></p>";

// Check what the getAllStudentsForEnrollment query returns
echo "<hr>";
echo "<h3>What getAllStudentsForEnrollment() returns:</h3>";

$stmt = $conn->prepare("
    SELECT 
        s.student_id,
        CONCAT(s.last_name, ', ', s.first_name) as full_name,
        s.status,
        CASE WHEN ce.student_id IS NOT NULL THEN 1 ELSE 0 END as already_enrolled
    FROM student s
    LEFT JOIN class_enrollments ce ON ce.student_id = s.student_id AND ce.class_code = ? AND ce.status = 'enrolled'
    WHERE s.status = 'active'
    GROUP BY s.student_id
    ORDER BY s.last_name, s.first_name
    LIMIT 500
");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Student ID</th><th>Name</th><th>Status</th><th>Already Enrolled?</th></tr>";

$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td>" . ($row['already_enrolled'] ? 'YES' : 'NO') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><strong>Total returned: $count</strong></p>";
