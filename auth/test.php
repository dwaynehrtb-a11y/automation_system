<?php
echo "<h1>Test Page</h1>";
echo "PHP is working: YES<br>";
echo "Current time: " . date('Y-m-d H:i:s') . "<br>";
echo "Current folder: " . __DIR__ . "<br>";
echo "Login file exists: " . (file_exists('login.php') ? 'YES' : 'NO') . "<br>";
?>