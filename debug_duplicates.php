<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

$student_id = '2022-118764';

// Check grade_term table directly
echo "<h2>Grade Term Records for Student: " . htmlspecialchars($student_id) . "</h2>";
$result = $conn->query("SELECT * FROM grade_term WHERE student_id = '$student_id' AND grade_status = 'incomplete'");
echo "Total grade_term rows: " . $result->num_rows . "<br>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>class_code</th><th>midterm_pct</th><th>finals_pct</th><th>grade_status</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['class_code'] . "</td>";
    echo "<td>" . $row['midterm_percentage'] . "</td>";
    echo "<td>" . $row['finals_percentage'] . "</td>";
    echo "<td>" . $row['grade_status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check with the JOIN query
echo "<h2>With JOIN to class table</h2>";
$query = "
    SELECT 
        gt.class_code,
        c.course_code,
        c.term,
        gt.grade_status,
        COUNT(*) as duplicate_count
    FROM grade_term gt
    INNER JOIN class c ON gt.class_code = c.class_code
    WHERE gt.student_id = ?
    AND gt.grade_status = 'incomplete'
    GROUP BY gt.class_code, c.course_code, c.term, gt.grade_status
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

echo "Total grouped rows: " . $result->num_rows . "<br>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>class_code</th><th>course_code</th><th>term</th><th>grade_status</th><th>duplicate_count</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['class_code'] . "</td>";
    echo "<td>" . $row['course_code'] . "</td>";
    echo "<td>" . $row['term'] . "</td>";
    echo "<td>" . $row['grade_status'] . "</td>";
    echo "<td><strong>" . $row['duplicate_count'] . "</strong></td>";
    echo "</tr>";
}
echo "</table>";

// Check class table for potential duplicates
echo "<h2>Class records for CCPRGG1L</h2>";
$result = $conn->query("SELECT class_id, class_code, course_code, term, academic_year FROM class WHERE class_code = 'CCPRGG1L'");
echo "Total class rows: " . $result->num_rows . "<br>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>class_id</th><th>class_code</th><th>course_code</th><th>term</th><th>academic_year</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['class_id'] . "</td>";
    echo "<td>" . $row['class_code'] . "</td>";
    echo "<td>" . $row['course_code'] . "</td>";
    echo "<td>" . $row['term'] . "</td>";
    echo "<td>" . $row['academic_year'] . "</td>";
    echo "</tr>";
}
echo "</table>";

?>
