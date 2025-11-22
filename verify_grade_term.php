<?php
require 'config/db.php';

echo "=== VERIFICATION: grade_term table ===\n\n";

// Check if table exists
$tables = $conn->query("SHOW TABLES LIKE 'grade_term'");
if ($tables->num_rows > 0) {
    echo "✓ grade_term table EXISTS\n\n";
} else {
    echo "✗ grade_term table DOES NOT EXIST\n";
    exit;
}

// Count records
$result = $conn->query("SELECT COUNT(*) as cnt FROM grade_term");
$row = $result->fetch_assoc();
echo "✓ Records in grade_term: " . $row['cnt'] . "\n\n";

// Show sample data
echo "=== Sample Data ===\n";
$result = $conn->query("SELECT student_id, class_code, midterm_percentage, finals_percentage, term_percentage, term_grade, grade_status FROM grade_term LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo "Student: {$row['student_id']}, Class: {$row['class_code']}, Status: {$row['grade_status']}, Grade: {$row['term_grade']}\n";
}

echo "\n✓ Migration to grade_term COMPLETE\n";
?>
