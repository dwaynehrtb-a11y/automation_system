<?php
require_once 'config/db.php';

// Check if status column exists
$result = $conn->query("SHOW COLUMNS FROM student_flexible_grades LIKE 'status'");

if ($result && $result->num_rows > 0) {
    echo "✅ Status column already exists\n";
    exit(0);
}

echo "Status column does NOT exist. Adding it...\n";

// Add the status column if it doesn't exist
$sql = "ALTER TABLE student_flexible_grades ADD COLUMN status VARCHAR(50) DEFAULT 'submitted' AFTER grade_value";

if ($conn->query($sql)) {
    echo "✅ Successfully added status column\n";
    exit(0);
} else {
    echo "❌ Error adding status column: " . $conn->error . "\n";
    exit(1);
}
?>
