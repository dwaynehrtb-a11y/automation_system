<?php
echo "Checking .env file:\n";
$envFile = __DIR__ . '/.env';
echo "Env file path: $envFile\n";
echo "File exists: " . (file_exists($envFile) ? 'YES' : 'NO') . "\n";

if (file_exists($envFile)) {
    echo "File contents:\n";
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        echo "  $line\n";
    }
}

echo "\nBefore loading config:\n";
echo 'APP_ENV: ' . getenv('APP_ENV') . "\n";
echo 'DB_HOST: ' . getenv('DB_HOST') . "\n";
echo 'DB_USER: ' . getenv('DB_USER') . "\n";
echo 'DB_NAME: ' . getenv('DB_NAME') . "\n\n";

require_once 'config/db.php';

echo "After loading config:\n";
echo 'Environment: ' . $environment . "\n";
echo 'getenv(APP_ENV): ' . getenv('APP_ENV') . "\n";
echo 'Config: ' . json_encode($dbConfig, JSON_PRETTY_PRINT) . "\n";
?>