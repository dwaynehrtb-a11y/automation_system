<?php
session_start();

// Simulate faculty login
$_SESSION['user_id'] = 'FAC-001'; // Replace with actual faculty ID
$_SESSION['role'] = 'faculty';

// Simulate POST request
$_POST['action'] = 'update_lacking_requirements';
$_POST['student_id'] = '2022-126653';
$_POST['class_code'] = '25_T2_CTAPROJ1_INF223';
$_POST['lacking_requirements'] = 'Test lacking requirement from direct script';

echo "Testing update_lacking_requirements endpoint...\n\n";
echo "POST data:\n";
print_r($_POST);
echo "\n";

// Include the actual save_term_grades.php
include 'faculty/ajax/save_term_grades.php';
?>
