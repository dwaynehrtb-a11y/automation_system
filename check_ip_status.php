<?php
require_once 'config/db.php';

echo "=== GRADE STATUS VALUES ===\n";
$result = $conn->query('SELECT DISTINCT grade_status FROM grade_term ORDER BY grade_status');
while($row = $result->fetch_assoc()) {
    echo "- " . ($row['grade_status'] ?: '[NULL]') . "\n";
}

echo "\n=== TERM GRADES WITH IP STATUS ===\n";
$result = $conn->query("
    SELECT tg.student_id, s.last_name, s.first_name, tg.class_code, tg.grade_status, tg.term_grade, 
           CASE 
               WHEN tg.grade_status='incomplete' THEN 'INC'
               WHEN tg.grade_status='dropped' THEN 'DRP'
               WHEN tg.grade_status='repeat' THEN 'R'
               WHEN tg.grade_status='failed' THEN 'FAILED'
               WHEN tg.grade_status='passed' AND tg.term_grade=4.0 THEN '4.00'
               WHEN tg.grade_status='passed' AND tg.term_grade=3.5 THEN '3.50'
               WHEN tg.grade_status='passed' AND tg.term_grade=3.0 THEN '3.00'
               WHEN tg.grade_status='passed' AND tg.term_grade=2.5 THEN '2.50'
               WHEN tg.grade_status='passed' AND tg.term_grade=2.0 THEN '2.00'
               WHEN tg.grade_status='passed' AND tg.term_grade=1.5 THEN '1.50'
               WHEN tg.grade_status='passed' AND tg.term_grade=1.0 THEN '1.00'
               ELSE 'IP'
           END AS display_grade
    FROM grade_term tg
    JOIN student s ON tg.student_id = s.student_id
    HAVING display_grade = 'IP'
");

if($result->num_rows == 0) {
    echo "No students with IP status found.\n";
} else {
    while($row = $result->fetch_assoc()) {
        echo "Student: " . $row['last_name'] . ", " . $row['first_name'] . "\n";
        echo "  Class: " . $row['class_code'] . "\n";
        echo "  Grade Status: " . ($row['grade_status'] ?: '[NULL]') . "\n";
        echo "  Term Grade: " . ($row['term_grade'] !== null ? $row['term_grade'] : '[NULL]') . "\n";
        echo "\n";
    }
}
?>
