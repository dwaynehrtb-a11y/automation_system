<?php
require_once 'config/db.php';

$query = 'SELECT gt.student_id, gt.component_id, gt.term_type, gt.percentage, gt.raw_score, gt.calculated_score, gc.component_name
          FROM grade_term gt
          INNER JOIN grading_components gc ON gt.component_id = gc.id
          WHERE gt.term_type = "midterm"
          ORDER BY gt.student_id, gt.component_id
          LIMIT 20';

$result = $conn->query($query);
if ($result) {
    echo "Grade term data (midterm):\n";
    while ($row = $result->fetch_assoc()) {
        echo "Student {$row['student_id']} - {$row['component_name']}: {$row['percentage']}% (raw: {$row['raw_score']}, calc: {$row['calculated_score']})\n";
    }
} else {
    echo 'Query failed: ' . $conn->error;
}

$query2 = 'SELECT DISTINCT gcc.component_id, gc.component_name, COUNT(g.id) as grade_count
          FROM grading_component_columns gcc
          INNER JOIN grading_components gc ON gcc.component_id = gc.id
          LEFT JOIN student_flexible_grades g ON gcc.id = g.column_id
          GROUP BY gcc.component_id, gc.component_name
          ORDER BY grade_count DESC
          LIMIT 10';

$result2 = $conn->query($query2);
if ($result2) {
    echo "\nComponents with grade counts:\n";
    while ($row = $result2->fetch_assoc()) {
        echo "Component ID {$row['component_id']}: {$row['component_name']} ({$row['grade_count']} grades)\n";
    }
} else {
    echo 'Query failed: ' . $conn->error;
}

$conn->close();
?>