<?php
require_once 'config/db.php';

// Check if status column exists
$result = $conn->query("SHOW COLUMNS FROM student_flexible_grades LIKE 'status'");

if ($result->num_rows === 0) {
    // Column doesn't exist, add it
    $query = "ALTER TABLE student_flexible_grades ADD COLUMN status VARCHAR(50) DEFAULT 'submitted' AFTER grade_value";
    
    if ($conn->query($query)) {
        echo "✅ SUCCESS: Added 'status' column to student_flexible_grades<br>";
    } else {
        echo "❌ ERROR: " . $conn->error;
    }
} else {
    echo "✅ 'status' column already exists<br>";
}

// Show current structure
echo "<h3>Current student_flexible_grades Structure:</h3>";
$result = $conn->query('DESCRIBE student_flexible_grades');
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
