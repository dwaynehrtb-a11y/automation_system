<?php
require_once 'config/db.php';

$course_code = 'CCDATRCL';

echo "<h2>Course Outcomes for $course_code</h2>";

$stmt = $conn->prepare("SELECT co_id, co_number, co_description FROM course_outcomes WHERE course_code = ? ORDER BY co_number");
$stmt->bind_param("s", $course_code);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>CO ID</th><th>CO Number</th><th>Description</th></tr>";

$count = 0;
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['co_id']) . "</td>";
    echo "<td>CO" . htmlspecialchars($row['co_number']) . "</td>";
    echo "<td>" . htmlspecialchars($row['co_description']) . "</td>";
    echo "</tr>";
    $count++;
}

echo "</table>";
echo "<p><strong>Total outcomes found: $count</strong></p>";

if ($count == 0) {
    echo "<p style='color: red;'>❌ No course outcomes found for $course_code</p>";
    echo "<p>Checking all course codes with outcomes:</p>";
    
    $stmt = $conn->prepare("SELECT DISTINCT course_code FROM course_outcomes ORDER BY course_code");
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . htmlspecialchars($row['course_code']) . "</li>";
    }
    echo "</ul>";
}
