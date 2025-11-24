<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die("Connection error: " . $conn->connect_error);

$classCode = '25_T2_CTAPROJ1_INF223';

echo "=== Test final grade distribution query ===\n";
$query = "
SELECT ce.student_id,
    ROUND(
        (
            COALESCE(AVG(CASE WHEN gc.term_type='midterm' THEN (COALESCE(sfg.raw_score,0)/gcc.max_score*100) ELSE NULL END), 0)
            * (40.0 / 100.0)
        ) +
        (
            COALESCE(AVG(CASE WHEN gc.term_type='finals' THEN (COALESCE(sfg.raw_score,0)/gcc.max_score*100) ELSE NULL END), 0)
            * (60.0 / 100.0)
        )
    , 2) as term_percentage
FROM class_enrollments ce
LEFT JOIN grading_components gc ON gc.class_code = ce.class_code
LEFT JOIN grading_component_columns gcc ON gc.id = gcc.component_id
LEFT JOIN student_flexible_grades sfg ON gcc.id = sfg.column_id AND ce.student_id = sfg.student_id
WHERE ce.class_code = '$classCode' AND ce.status = 'enrolled'
GROUP BY ce.student_id";

$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);
echo "Rows: " . $result->num_rows . "\n";

$gradeDist = ['4.00' => 0, '3.50' => 0, '3.00' => 0, '2.50' => 0, '2.00' => 0, '1.50' => 0, '1.00' => 0, 'INC' => 0, 'DRP' => 0, 'R' => 0, 'FAILED' => 0, 'IP' => 0];
while($row = $result->fetch_assoc()) { 
    $termPct = floatval($row['term_percentage']);
    
    echo "Student: {$row['student_id']}, Term%: $termPct -> ";
    
    // Use exact conversion ladder from toGrade() in flexible_grading.js
    if ($termPct < 60) {
        $gradeDist['FAILED']++;
        echo "FAILED\n";
    } elseif ($termPct < 66) {
        $gradeDist['1.00']++;
        echo "1.00\n";
    } elseif ($termPct < 72) {
        $gradeDist['1.50']++;
        echo "1.50\n";
    } elseif ($termPct < 78) {
        $gradeDist['2.00']++;
        echo "2.00\n";
    } elseif ($termPct < 84) {
        $gradeDist['2.50']++;
        echo "2.50\n";
    } elseif ($termPct < 90) {
        $gradeDist['3.00']++;
        echo "3.00\n";
    } elseif ($termPct < 96) {
        $gradeDist['3.50']++;
        echo "3.50\n";
    } else {
        $gradeDist['4.00']++;
        echo "4.00\n";
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
