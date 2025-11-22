<?php
$conn = new mysqli("localhost", "root", "", "automation_system");
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
echo "✅ Connected successfully to automation_system";
?>
