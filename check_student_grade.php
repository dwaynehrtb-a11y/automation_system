<?php
require_once 'config/db.php';

$student_id = '2022-118764';

$query = "SELECT s.student_id, s.first_name, s.last_name, 
                 tg.class_code, c.course_code, sub.course_title, 
                 tg.midterm_percentage, tg.finals_percentage, 
                 tg.term_grade, tg.grade_status 
          FROM student s 
          LEFT JOIN grade_term tg ON s.student_id = tg.student_id 
          LEFT JOIN class c ON tg.class_code = c.class_code 
          LEFT JOIN subjects sub ON c.course_code = sub.course_code 
          WHERE s.student_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

echo "=== Student Grade Information ===\n\n";

if ($result->num_rows > 0) {
    $student_info = null;
    $grades = [];
    
    while ($row = $result->fetch_assoc()) {
        if (!$student_info) {
            $student_info = $row;
        }
        if ($row['class_code']) {
            $grades[] = $row;
        }
    }
    
    if ($student_info) {
        echo "Student: " . $student_info['first_name'] . " " . $student_info['last_name'] . "\n";
        echo "ID: " . $student_info['student_id'] . "\n";
        echo "\nClasses & Grades:\n";
        echo str_repeat("-", 100) . "\n";
        
        if (count($grades) > 0) {
            foreach ($grades as $grade) {
                echo "Class: " . $grade['class_code'] . " - " . $grade['course_title'] . "\n";
                echo "  Midterm: " . ($grade['midterm_percentage'] ? $grade['midterm_percentage'] : 'N/A') . "%\n";
                echo "  Finals: " . ($grade['finals_percentage'] ? $grade['finals_percentage'] : 'N/A') . "%\n";
                echo "  Term Grade: " . ($grade['term_grade'] ? $grade['term_grade'] : 'N/A') . "\n";
                echo "  Status: " . ($grade['grade_status'] ? $grade['grade_status'] : 'N/A') . "\n";
                echo "\n";
            }
        } else {
            echo "No grades found for this student.\n";
        }
    }
} else {
    echo "Student not found.\n";
}

$stmt->close();
$conn->close();
?>
