<?php
require_once 'config/db.php';

echo "<h2>Checking student_flexible_grades table</h2>";

// Check if status column exists
$result = $conn->query("SHOW COLUMNS FROM student_flexible_grades LIKE 'status'");

if ($result->num_rows > 0) {
    echo "✅ Status column already exists<br>";
} else {
    echo "❌ Status column does NOT exist, adding it...<br>";
    
    // Try to add the column
    $query = "ALTER TABLE student_flexible_grades ADD COLUMN status VARCHAR(50) DEFAULT 'submitted' AFTER grade_value";
    
    if ($conn->query($query)) {
        echo "✅ Successfully added status column<br>";
    } else {
        echo "❌ Failed to add column: " . $conn->error . "<br>";
    }
}

// Show the table structure
echo "<h3>Current Table Structure:</h3>";
$result = $conn->query("DESCRIBE student_flexible_grades");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check the error log
echo "<h3>Recent API Errors:</h3>";
if (file_exists('ajax/update_grade_errors.log')) {
    $lines = file('ajax/update_grade_errors.log');
    $recent = array_slice($lines, -10);
    echo "<pre>" . implode($recent) . "</pre>";
} else {
    echo "No error log found";
}
?>
