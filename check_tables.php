<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config/db.php';

// Check what tables exist
$tables = ['class', 'subjects', 'classes', 'courses', 'course_outcomes', 'grading_components'];

echo "=== DATABASE TABLE CHECK ===\n\n";

foreach ($tables as $table) {
    $result = $conn->query("SELECT 1 FROM $table LIMIT 1");
    $exists = ($result !== false);
    echo "$table: " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

echo "\n=== CHECKING CLASSES TABLE ===\n";
$result = $conn->query("DESCRIBE classes");
if ($result) {
    echo "Columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "Classes table error: " . $conn->error . "\n";
}

echo "\n=== SAMPLE CLASS DATA ===\n";
$result = $conn->query("SELECT * FROM classes LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    print_r($row);
} else {
    echo "No data or error: " . $conn->error . "\n";
}
?>
