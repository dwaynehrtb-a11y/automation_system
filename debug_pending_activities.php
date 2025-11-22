<?php
// Debug: Check what data is being returned from the API
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

// Simulate the query
$student_id = $_SESSION['student_id'] ?? '2022-118764';

$query = "
    SELECT 
        gt.class_code,
        c.course_code,
        s.course_title,
        c.term,
        gt.midterm_percentage,
        gt.finals_percentage,
        gt.term_percentage,
        gt.grade_status,
        u.name as faculty_name
    FROM grade_term gt
    INNER JOIN class c ON gt.class_code = c.class_code
    LEFT JOIN subjects s ON c.course_code = s.course_code
    LEFT JOIN users u ON c.faculty_id = u.id
    WHERE gt.student_id = ? AND gt.grade_status = 'incomplete'
    ORDER BY c.academic_year DESC, c.term DESC, c.course_code
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo "Prepare error: " . $conn->error;
    exit;
}

$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Pending Activities Query Results</h2>";
echo "<p>Student ID: " . htmlspecialchars($student_id) . "</p>";
echo "<p>Total rows: " . $result->num_rows . "</p>";
echo "<table border='1'><tr><th>class_code</th><th>course_code</th><th>term</th><th>grade_status</th><th>midterm_pct</th><th>finals_pct</th></tr>";

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['class_code']) . "</td>";
    echo "<td>" . htmlspecialchars($row['course_code']) . "</td>";
    echo "<td>" . htmlspecialchars($row['term']) . "</td>";
    echo "<td>" . htmlspecialchars($row['grade_status']) . "</td>";
    echo "<td>" . htmlspecialchars($row['midterm_percentage']) . "</td>";
    echo "<td>" . htmlspecialchars($row['finals_percentage']) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<pre>Raw Data: " . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";

$stmt->close();
?>
