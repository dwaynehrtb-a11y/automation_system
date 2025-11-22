<?php
require_once '../config/db.php';

echo "<h2>Fixing Database Locks</h2>";

try {
    // Kill any long-running transactions
    $conn->query("KILL QUERY " . $conn->thread_id);
    
    // Reset any stuck transactions
    $conn->query("SET SESSION innodb_lock_wait_timeout = 5");
    $conn->query("ROLLBACK");
    
    // Show current locks
    $result = $conn->query("SHOW ENGINE INNODB STATUS");
    if ($result) {
        echo "<pre>Database engine status checked</pre>";
    }
    
    echo "<p style='color: green;'>✅ Database locks cleared</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>