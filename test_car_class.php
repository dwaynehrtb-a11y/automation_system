<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

$classCode = '25_T2_CTAPROJ1_INF223';

echo "=== All students and their grades for this class ===\n";
$query = "SELECT student_id, term_grade, grade_status FROM grade_term WHERE class_code='$classCode' ORDER BY term_grade DESC";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Total records: " . $result->num_rows . "\n";
while($row = $result->fetch_assoc()) { 
    echo json_encode($row) . "\n"; 
}

echo "\n=== Grade distribution test ===\n";
$query = "SELECT ce.student_id,
    CASE 
        WHEN gt.term_grade IS NULL OR gt.term_grade = '' THEN 0.0
        ELSE CAST(gt.term_grade AS DECIMAL(5,2))
    END as calculated_grade,
    gt.grade_status
FROM class_enrollments ce
LEFT JOIN grade_term gt ON gt.student_id=ce.student_id AND gt.class_code=ce.class_code
WHERE ce.class_code='$classCode' AND ce.status='enrolled'
ORDER BY ce.student_id";

$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Rows: " . $result->num_rows . "\n";

$gradeDist = ['4.00' => 0, '3.50' => 0, '3.00' => 0, '2.50' => 0, '2.00' => 0, '1.50' => 0, '1.00' => 0, 'INC' => 0, 'DRP' => 0, 'R' => 0, 'FAILED' => 0, 'IP' => 0];
while($row = $result->fetch_assoc()) { 
    $grade = floatval($row['calculated_grade']);
    $status = $row['grade_status'] ?? '';
    
    echo "Student: {$row['student_id']}, Grade: $grade, Status: $status -> ";
    
    // Handle special statuses
    if ($status === 'incomplete') {
        $gradeDist['INC']++;
        echo "INC\n";
    } elseif ($status === 'dropped') {
        $gradeDist['DRP']++;
        echo "DRP\n";
    } elseif ($status === 'repeat' || $status === 'for-repeat') {
        $gradeDist['R']++;
        echo "R\n";
    } elseif ($grade <= 0.0 || $status === 'failed') {
        $gradeDist['FAILED']++;
        echo "FAILED\n";
    } elseif ($grade >= 3.75 || (int)$grade === 4) {
        $gradeDist['4.00']++;
        echo "4.00\n";
    } elseif ($grade >= 3.25 || $grade === 3.5) {
        $gradeDist['3.50']++;
        echo "3.50\n";
    } elseif ($grade >= 2.75 || (int)$grade === 3) {
        $gradeDist['3.00']++;
        echo "3.00\n";
    } elseif ($grade >= 2.25 || $grade === 2.5) {
        $gradeDist['2.50']++;
        echo "2.50\n";
    } elseif ($grade >= 1.75 || (int)$grade === 2) {
        $gradeDist['2.00']++;
        echo "2.00\n";
    } elseif ($grade >= 1.25 || $grade === 1.5) {
        $gradeDist['1.50']++;
        echo "1.50\n";
    } elseif ($grade >= 0.75 || (int)$grade === 1) {
        $gradeDist['1.00']++;
        echo "1.00\n";
    } else {
        $gradeDist['IP']++;
        echo "IP\n";
    }
}

echo "\n=== Final Distribution ===\n";
foreach($gradeDist as $g => $count) {
    if ($count > 0) {
        echo "$g: $count\n";
    }
}

$conn->close();
?>
