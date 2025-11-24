<?php
require_once 'config/db.php';

$class_code = '25_T2_CTAPROJ1_INF223';

echo "<h2>Student Count Debug for $class_code</h2>";

// Check class_enrollments
$stmt = $conn->prepare("
    SELECT student_id, status 
    FROM class_enrollments 
    WHERE class_code = ?
");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Enrollments in class_enrollments table:</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Student ID</th><th>Status</th></tr>";

$enrolled_count = 0;
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "</tr>";
    if ($row['status'] === 'enrolled') {
        $enrolled_count++;
    }
}
echo "</table>";
echo "<p><strong>Total enrolled: $enrolled_count</strong></p>";

// Check the actual query used in dashboard
echo "<h3>Query from dashboard:</h3>";
$stmt = $conn->prepare("
    SELECT 
        c.class_code,
        COUNT(DISTINCT ce.student_id) as student_count
    FROM class c
    LEFT JOIN class_enrollments ce ON c.class_code = ce.class_code AND ce.status = 'enrolled'
    WHERE c.class_code = ?
    GROUP BY c.class_code
");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo "<p>student_count from query: " . ($row['student_count'] ?? 'NULL') . "</p>";

// Check if there are multiple class records
echo "<h3>Class table records:</h3>";
$stmt = $conn->prepare("SELECT class_id, class_code, day, time, room FROM class WHERE class_code = ?");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Class ID</th><th>Class Code</th><th>Day</th><th>Time</th><th>Room</th></tr>";
$class_count = 0;
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['class_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['class_code']) . "</td>";
    echo "<td>" . htmlspecialchars($row['day']) . "</td>";
    echo "<td>" . htmlspecialchars($row['time']) . "</td>";
    echo "<td>" . htmlspecialchars($row['room']) . "</td>";
    echo "</tr>";
    $class_count++;
}
echo "</table>";
echo "<p><strong>Total class records: $class_count</strong></p>";
