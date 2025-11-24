<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

$classCode = '24_T1_CTAPROJ1_INF221';

echo "=== Enrollments for this class ===\n";
$query = "SELECT COUNT(*) as cnt FROM class_enrollments WHERE class_code='$classCode' AND status='enrolled'";
$result = $conn->query($query);
$row = $result->fetch_assoc();
echo "Enrolled students: " . $row['cnt'] . "\n";

echo "\n=== Test the grade distribution query ===\n";
$query = "SELECT ce.student_id,
    ROUND(
        (COALESCE(AVG(CASE WHEN sfg.raw_score IS NOT NULL THEN (COALESCE(sfg.raw_score,0)/gcc.max_score*100) END), 0)) / 25
    , 2) as calculated_grade
FROM class_enrollments ce
LEFT JOIN grading_components gc ON gc.class_code=ce.class_code
LEFT JOIN grading_component_columns gcc ON gc.id=gcc.component_id
LEFT JOIN student_flexible_grades sfg ON gcc.id=sfg.column_id AND ce.student_id=sfg.student_id
WHERE ce.class_code='$classCode' AND ce.status='enrolled'
GROUP BY ce.student_id";

$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Rows returned: " . $result->num_rows . "\n";
$grades = array();
while($row = $result->fetch_assoc()) { 
    $grade = floatval($row['calculated_grade']);
    $grades[] = $grade;
    echo json_encode($row) . "\n"; 
}

echo "\n=== Grade Distribution ===\n";
$gradeDist = ['4.00' => 0, '3.50' => 0, '3.00' => 0, '2.50' => 0, '2.00' => 0, '1.50' => 0, '1.00' => 0, 'IP' => 0, 'FAILED' => 0];
foreach($grades as $grade) {
    if ($grade <= 0.0) {
        $gradeDist['FAILED']++;
    } elseif ($grade >= 3.75) {
        $gradeDist['4.00']++;
    } elseif ($grade >= 3.25) {
        $gradeDist['3.50']++;
    } elseif ($grade >= 2.75) {
        $gradeDist['3.00']++;
    } elseif ($grade >= 2.25) {
        $gradeDist['2.50']++;
    } elseif ($grade >= 1.75) {
        $gradeDist['2.00']++;
    } elseif ($grade >= 1.25) {
        $gradeDist['1.50']++;
    } elseif ($grade >= 0.75) {
        $gradeDist['1.00']++;
    } else {
        $gradeDist['IP']++;
    }
}

foreach($gradeDist as $g => $count) {
    echo "$g: $count\n";
}

$conn->close();
?>
