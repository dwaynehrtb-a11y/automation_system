<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
if ($conn->connect_error) die('Connect failed: ' . $conn->connect_error);

echo "All Quiz components for those students:\n";
$sql = "
    SELECT 
        sfg.student_id, 
        gcc.column_name,
        gc.component_name,
        gcc.max_score,
        sfg.raw_score 
    FROM student_flexible_grades sfg
    INNER JOIN grading_component_columns gcc ON sfg.column_id = gcc.id
    INNER JOIN grading_components gc ON gcc.component_id = gc.id
    WHERE gc.component_name = 'Quiz' 
    AND sfg.student_id IN ('2022-118764', '2022-171253', '2022-182121')
    ORDER BY gc.id, sfg.student_id, gcc.order_index
    LIMIT 30
";
$result = $conn->query($sql);
if (!$result) {
    die("Query error: " . $conn->error);
}

while ($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

$conn->close();
?>
