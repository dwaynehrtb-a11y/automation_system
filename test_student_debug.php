<?php
require_once 'config/db.php';

echo "=== DEBUGGING STUDENT SEARCH ===\n\n";

// Get all students
echo "1. All students in database:\n";
$result = $conn->query("SELECT student_id, first_name, last_name FROM students");
if ($result) {
    if ($result->num_rows === 0) {
        echo "   ⚠️  No students found in database\n";
    } else {
        while ($row = $result->fetch_assoc()) {
            echo "   - " . $row['student_id'] . " | " . $row['first_name'] . " " . $row['last_name'] . "\n";
        }
    }
} else {
    echo "   ❌ Query error: " . $conn->error . "\n";
}

echo "\n2. Database tables:\n";
$tables_result = $conn->query("SHOW TABLES");
while ($table = $tables_result->fetch_row()) {
    echo "   - " . $table[0] . "\n";
}

echo "\n3. Students table structure:\n";
$columns = $conn->query("DESCRIBE students");
if ($columns) {
    while ($col = $columns->fetch_assoc()) {
        echo "   - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} else {
    echo "   ❌ Error: " . $conn->error . "\n";
}

echo "\n4. Sample student record (with encryption info):\n";
$sample = $conn->query("SELECT * FROM students LIMIT 1");
if ($sample && $sample->num_rows > 0) {
    $row = $sample->fetch_assoc();
    echo "   Student ID: " . $row['student_id'] . "\n";
    echo "   First Name (encrypted): " . substr($row['first_name'], 0, 30) . "...\n";
    echo "   Last Name (encrypted): " . substr($row['last_name'], 0, 30) . "...\n";
    echo "   Email (encrypted): " . substr($row['email'], 0, 30) . "...\n";
} else {
    echo "   ❌ No students found\n";
}

echo "\n=== What to try in decrypt tool ===\n";
$first_student = $conn->query("SELECT student_id FROM students LIMIT 1");
if ($first_student && $first_student->num_rows > 0) {
    $row = $first_student->fetch_assoc();
    echo "Use this Student ID: " . $row['student_id'] . "\n";
} else {
    echo "⚠️  No students in database to test\n";
}
?>
