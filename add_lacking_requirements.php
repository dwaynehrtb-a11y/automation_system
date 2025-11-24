<?php
require_once 'config/db.php';

// Update lacking requirements for Hayasaka, Shermuz
$student_id = '2022-171253';
$class_code = '25_T2_CTAPROJ1_INF223';
$lacking_requirements = 'Missing final project submission and presentation';

$stmt = $conn->prepare("
    UPDATE grade_term 
    SET lacking_requirements = ? 
    WHERE student_id = ? AND class_code = ?
");

$stmt->bind_param("sss", $lacking_requirements, $student_id, $class_code);

if ($stmt->execute()) {
    echo "✅ Successfully updated lacking requirements for student $student_id\n";
    echo "Lacking Requirements: $lacking_requirements\n\n";
    
    // Verify the update
    $verify = $conn->prepare("SELECT student_id, grade_status, lacking_requirements FROM grade_term WHERE student_id = ? AND class_code = ?");
    $verify->bind_param("ss", $student_id, $class_code);
    $verify->execute();
    $result = $verify->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo "Verification:\n";
        echo "Student ID: " . $row['student_id'] . "\n";
        echo "Grade Status: " . $row['grade_status'] . "\n";
        echo "Lacking Requirements: " . $row['lacking_requirements'] . "\n";
    }
    
    $verify->close();
} else {
    echo "❌ Error: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>
