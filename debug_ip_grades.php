<?php
require_once 'config/db.php';

// Get the class code from terminal or hardcode for testing
$class_code = 'YOUR_CLASS_CODE'; // Change this

echo "Checking students with 'IP' (In Progress) status for class: $class_code\n\n";

// Get all grade_term for this class
$stmt = $conn->prepare("SELECT tg.student_id, s.student_id, CONCAT(s.last_name, ', ', s.first_name) AS name, tg.grade_status, tg.term_grade FROM class_enrollments ce JOIN student s ON ce.student_id=s.student_id JOIN grade_term tg ON tg.class_code=ce.class_code AND tg.student_id=s.student_id WHERE tg.class_code=? AND ce.status='enrolled' ORDER BY s.last_name, s.first_name");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "All Students & Grades:\n";
echo "========================\n";
while($row = $result->fetch_assoc()) {
    $status = $row['grade_status'];
    $grade = $row['term_grade'];
    
    // Determine display status
    if($status == 'incomplete') $display = "INC";
    elseif($status == 'DRP') $display = "DRP";
    elseif($status == 'repeat') $display = "R";
    elseif($status == 'failed') $display = "FAILED";
    elseif($status == 'passed' && $grade == 4.0) $display = "4.00";
    elseif($status == 'passed' && $grade == 3.5) $display = "3.50";
    elseif($status == 'passed' && $grade == 3.0) $display = "3.00";
    elseif($status == 'passed' && $grade == 2.5) $display = "2.50";
    elseif($status == 'passed' && $grade == 2.0) $display = "2.00";
    elseif($status == 'passed' && $grade == 1.5) $display = "1.50";
    elseif($status == 'passed' && $grade == 1.0) $display = "1.00";
    else $display = "IP (UNEXPECTED)";
    
    echo $row['name'] . " | Status: " . ($status ?: 'NULL') . " | Term Grade: " . ($grade !== null ? $grade : 'NULL') . " | Display: $display\n";
}

$stmt->close();
?>
