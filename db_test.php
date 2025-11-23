<?php
echo "<h1>Database Connection Test</h1>";

// First check environment variables
echo "<h2>Environment Variables</h2>";
echo "<p>APP_ENV: " . getenv('APP_ENV') . "</p>";
echo "<p>DB_HOST: " . getenv('DB_HOST') . "</p>";
echo "<p>DB_USER: " . getenv('DB_USER') . "</p>";
echo "<p>DB_PASS: " . (getenv('DB_PASS') ? "***" : "NOT SET") . "</p>";
echo "<p>DB_NAME: " . getenv('DB_NAME') . "</p>";

// Load database config
require_once 'config/db.php';

echo "<h2>Database Configuration</h2>";
echo "<p>Environment detected: " . $environment . "</p>";
echo "<p>Host: " . $dbConfig['host'] . "</p>";
echo "<p>User: " . $dbConfig['user'] . "</p>";
echo "<p>Database: " . $dbConfig['db'] . "</p>";
echo "<p>Debug: " . ($dbConfig['debug'] ? 'true' : 'false') . "</p>";

echo "<h2>Connection Test</h2>";

try {
    // Test database connection
    $testQuery = "SELECT 1 as test, VERSION() as version";
    $stmt = executeQuery($conn, $testQuery);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    echo "<p style='color: green;'>✅ Database Connection Successful!</p>";
    echo "<p>Test query result: " . $row['test'] . "</p>";
    echo "<p>MySQL Version: " . $row['version'] . "</p>";
    echo "<p>Connected to database: " . $dbConfig['db'] . "</p>";

    // Test a simple table query
    $tables = getSafeRecords($conn, 'information_schema.tables', "table_schema = ? AND table_name LIKE 'user%'", [$dbConfig['db']], 'table_name', 'table_name', 5);
    echo "<p>Found " . count($tables) . " user-related tables</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database Connection Failed</p>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Error Code: " . $conn->connect_errno . "</p>";
    echo "<p>Error Details: " . $conn->connect_error . "</p>";
    echo "<p>Environment: " . $environment . "</p>";
    echo "<p>Config Debug: <pre>" . json_encode($dbConfig, JSON_PRETTY_PRINT) . "</pre></p>";
}
?>