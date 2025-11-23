<?php
require_once 'config/db.php';

try {
    // Test database connection
    $testQuery = "SELECT 1 as test";
    $stmt = executeQuery($conn, $testQuery);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    echo "<h1>✅ Database Connection Successful!</h1>";
    echo "<p>Test query result: " . $row['test'] . "</p>";
    echo "<p>Connected to database: " . $dbConfig['db'] . "</p>";
    echo "<p>Environment: " . $environment . "</p>";

    // Test a simple table query
    $tables = getSafeRecords($conn, 'information_schema.tables', "table_schema = ? AND table_name LIKE 'user%'", [$dbConfig['db']], 'table_name', 'table_name', 5);
    echo "<p>Found " . count($tables) . " user-related tables</p>";

} catch (Exception $e) {
    echo "<h1>❌ Database Connection Failed</h1>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Environment: " . $environment . "</p>";
    echo "<p>Config: " . json_encode($dbConfig, JSON_PRETTY_PRINT) . "</p>";
}
?>