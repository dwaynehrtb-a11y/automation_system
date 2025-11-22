<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

$student_id = '2022-118764';
$class_code = 'CCPRGG1L';

echo "<h2>Debug: Grading Components for CCPRGG1L</h2>";

// Check grading components
$query = "SELECT * FROM grading_components WHERE class_code = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Grading Components:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Component Name</th><th>Term Type</th><th>Percentage Weight</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['component_name'] . "</td>";
    echo "<td>" . $row['term_type'] . "</td>";
    echo "<td>" . $row['percentage'] . "%</td>";
    echo "</tr>";
}
echo "</table>";
$stmt->close();

// Check student flexible grades
echo "<h3>Student Flexible Grades for " . $student_id . ":</h3>";
$query = "
    SELECT g.id, g.raw_score, g.class_code, gcc.component_id, gc.component_name, gc.term_type, gc.percentage as component_weight, gcc.max_score
    FROM student_flexible_grades g
    JOIN grading_component_columns gcc ON g.column_id = gcc.id
    JOIN grading_components gc ON gcc.component_id = gc.id
    WHERE g.class_code = ? AND g.student_id = ?
    ORDER BY gc.term_type, gc.component_name
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $class_code, $student_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Component</th><th>Term</th><th>Weight</th><th>Raw Score</th><th>Max Score</th></tr>";
$total_earned_midterm = 0;
$total_possible_midterm = 0;
$total_earned_finals = 0;
$total_possible_finals = 0;

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['component_name'] . "</td>";
    echo "<td>" . $row['term_type'] . "</td>";
    echo "<td>" . $row['component_weight'] . "%</td>";
    echo "<td>" . $row['raw_score'] . "</td>";
    echo "<td>" . $row['max_score'] . "</td>";
    echo "</tr>";
    
    if ($row['term_type'] === 'midterm') {
        $total_earned_midterm += floatval($row['raw_score'] ?? 0);
        $total_possible_midterm += floatval($row['max_score'] ?? 0);
    } else {
        $total_earned_finals += floatval($row['raw_score'] ?? 0);
        $total_possible_finals += floatval($row['max_score'] ?? 0);
    }
}
echo "</table>";
$stmt->close();

echo "<h3>Summary:</h3>";
echo "<p><strong>Midterm:</strong> " . $total_earned_midterm . " / " . $total_possible_midterm;
if ($total_possible_midterm > 0) {
    $midterm_pct = ($total_earned_midterm / $total_possible_midterm) * 100;
    echo " = " . round($midterm_pct, 2) . "%";
}
echo "</p>";

echo "<p><strong>Finals:</strong> " . $total_earned_finals . " / " . $total_possible_finals;
if ($total_possible_finals > 0) {
    $finals_pct = ($total_earned_finals / $total_possible_finals) * 100;
    echo " = " . round($finals_pct, 2) . "%";
}
echo "</p>";
?>
