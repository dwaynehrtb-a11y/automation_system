<?php
echo "<h1>Environment Variables Test</h1>";
echo "<p>APP_ENV: " . getenv('APP_ENV') . "</p>";
echo "<p>DB_HOST: " . getenv('DB_HOST') . "</p>";
echo "<p>DB_USER: " . getenv('DB_USER') . "</p>";
echo "<p>DB_PASS: " . (getenv('DB_PASS') ? "***" : "NOT SET") . "</p>";
echo "<p>DB_NAME: " . getenv('DB_NAME') . "</p>";

echo "<h2>Config Test</h2>";
require_once 'config/db.php';
echo "<p>Environment detected: " . $environment . "</p>";
echo "<p>Database config: " . json_encode($dbConfig) . "</p>";
?>