<?php
require_once 'config/db.php';

// Get Denzil's faculty ID
$stmt = $conn->prepare("SELECT id FROM users WHERE name LIKE '%Denzil%' AND role = 'faculty'");
$stmt->execute();
$result = $stmt->get_result();
$faculty = $result->fetch_assoc();
$faculty_id = $faculty['id'];

echo "<h2>Faculty Dashboard Student Count Analysis</h2>";
echo "<p>Faculty ID: $faculty_id</p>";

$academic_year = '25';
$term = 'T2';

// Run the exact query from dashboard
$query = "
    SELECT 
        MIN(c.class_id) as class_id,
        c.class_code,
        c.section,
        c.course_code,
        c.academic_year,
        c.term,
        GROUP_CONCAT(
            DISTINCT CONCAT(c.day, ' ', c.time) 
            ORDER BY FIELD(c.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
            c.time SEPARATOR '\n'
        ) as schedule_display,
        c.room,
        s.course_title,
        s.units,
        COUNT(DISTINCT ce.student_id) as student_count
    FROM class c
    LEFT JOIN subjects s ON c.course_code = s.course_code
    LEFT JOIN class_enrollments ce ON c.class_code = ce.class_code AND ce.status = 'enrolled'
    WHERE c.faculty_id = ? AND c.academic_year = ? AND c.term = ?
    GROUP BY c.class_code, c.section, c.course_code, c.academic_year, c.term, c.room, s.course_title, s.units
    ORDER BY c.course_code, c.section
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $faculty_id, $academic_year, $term);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Class Code</th><th>Course Title</th><th>Student Count (from query)</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['class_code']) . "</td>";
    echo "<td>" . htmlspecialchars($row['course_title']) . "</td>";
    echo "<td><strong>" . $row['student_count'] . "</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h3>Individual Enrollment Counts:</h3>";

// For each class, count enrollments directly
$stmt = $conn->prepare("
    SELECT c.class_code, s.course_title, COUNT(ce.student_id) as actual_count
    FROM class c
    LEFT JOIN subjects s ON c.course_code = s.course_code
    LEFT JOIN class_enrollments ce ON c.class_code = ce.class_code AND ce.status = 'enrolled'
    WHERE c.faculty_id = ? AND c.academic_year = ? AND c.term = ?
    GROUP BY c.class_code, s.course_title
");
$stmt->bind_param("iss", $faculty_id, $academic_year, $term);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Class Code</th><th>Course Title</th><th>Actual Enrolled Count</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['class_code']) . "</td>";
    echo "<td>" . htmlspecialchars($row['course_title']) . "</td>";
    echo "<td><strong style='color: green;'>" . $row['actual_count'] . "</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h3>Checking for duplicate class records:</h3>";

$stmt = $conn->prepare("
    SELECT class_code, COUNT(*) as record_count
    FROM class
    WHERE faculty_id = ? AND academic_year = ? AND term = ?
    GROUP BY class_code
    HAVING record_count > 1
");
$stmt->bind_param("iss", $faculty_id, $academic_year, $term);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Class Code</th><th>Number of Records</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['class_code']) . "</td>";
        echo "<td style='color: red;'><strong>" . $row['record_count'] . "</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>✓ No duplicate class records found</p>";
}
