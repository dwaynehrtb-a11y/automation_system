<?php
require_once 'config/db.php';

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'class'");
if ($result->num_rows > 0) {
    echo "✓ Table 'class' exists\n\n";
    
    // Get row count
    $count_result = $conn->query("SELECT COUNT(*) as cnt FROM class");
    $count_row = $count_result->fetch_assoc();
    echo "Total rows in class table: " . $count_row['cnt'] . "\n\n";
    
    if ($count_row['cnt'] > 0) {
        echo "Sample data:\n";
        echo str_repeat("-", 100) . "\n";
        $sample = $conn->query("SELECT * FROM class LIMIT 3");
        
        if ($sample) {
            while ($row = $sample->fetch_assoc()) {
                print_r($row);
                echo "\n";
            }
        } else {
            echo "Query error: " . $conn->error . "\n";
        }
    }
} else {
    echo "✗ Table 'class' does NOT exist\n";
}

// List all tables
echo "\n\nAll tables in database:\n";
$tables = $conn->query("SHOW TABLES");
while ($t = $tables->fetch_row()) {
    echo "- " . $t[0] . "\n";
}
?>
