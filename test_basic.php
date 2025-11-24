<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Starting test...\n";

// Test basic PHP
echo "PHP Version: " . phpversion() . "\n";

// Try to include config
echo "Testing config/db.php include...\n";
try {
    if (file_exists(__DIR__ . '/config/db.php')) {
        echo "File exists\n";
        require_once __DIR__ . '/config/db.php';
        echo "Included successfully\n";
        
        // Check connection
        if (isset($conn)) {
            echo "Connection object exists\n";
            echo "Connection error: " . ($conn->connect_error ?? 'None') . "\n";
        } else {
            echo "ERROR: Connection object not set\n";
        }
    } else {
        echo "ERROR: config/db.php not found\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "\nDone\n";
?>
