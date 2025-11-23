<?php
echo "=== HOSTINGER DEBUG ===\n";
echo "Current working directory: " . getcwd() . "\n";
echo "Script path: " . __FILE__ . "\n";
echo "Config directory: " . __DIR__ . "\n";

$envFile = __DIR__ . '/../.env';
echo ".env file path: $envFile\n";
echo ".env file exists: " . (file_exists($envFile) ? 'YES' : 'NO') . "\n";

if (file_exists($envFile)) {
    echo ".env file contents:\n";
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        echo "  $line\n";
    }
}

echo "\nEnvironment variables:\n";
echo "APP_ENV: '" . getenv('APP_ENV') . "'\n";
echo "DB_HOST: '" . getenv('DB_HOST') . "'\n";
echo "DB_USER: '" . getenv('DB_USER') . "'\n";
echo "DB_PASS: '" . (getenv('DB_PASS') ? '***SET***' : 'NOT SET') . "'\n";
echo "DB_NAME: '" . getenv('DB_NAME') . "'\n";

echo "\nPHP Version: " . phpversion() . "\n";
echo "Server: " . $_SERVER['SERVER_NAME'] . "\n";
?>