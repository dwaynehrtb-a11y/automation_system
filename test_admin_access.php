<?php
// Test admin access and session
session_start();

require_once 'config/db.php';

echo "=== ADMIN DASHBOARD ACCESS TEST ===\n\n";

// Check 1: Session status
echo "1. Session Status:\n";
if (isset($_SESSION['user_id'])) {
    echo "   ✓ Logged in as: " . $_SESSION['name'] . " (ID: " . $_SESSION['user_id'] . ")\n";
    echo "   ✓ Role: " . $_SESSION['role'] . "\n";
    echo "   ✓ Login time: " . date('Y-m-d H:i:s', $_SESSION['login_time']) . "\n";
    echo "   ✓ Last activity: " . date('Y-m-d H:i:s', $_SESSION['last_activity']) . "\n";
    
    // Check if admin
    if ($_SESSION['role'] !== 'admin') {
        echo "   ✗ ERROR: User role is '{$_SESSION['role']}', not 'admin'\n";
        echo "   ✗ You need an admin account to access admin_dashboard.php\n";
    } else {
        echo "   ✓ User has admin privileges\n";
    }
} else {
    echo "   ✗ NOT LOGGED IN\n";
    echo "   ✗ Please log in first at auth/login.php\n";
}

// Check 2: Admin users in database
echo "\n2. Admin Users in Database:\n";
$result = $conn->query("SELECT id, name, email, role FROM users WHERE role = 'admin'");
if ($result) {
    $count = $result->num_rows;
    echo "   Found: $count admin user(s)\n";
    if ($count > 0) {
        while ($user = $result->fetch_assoc()) {
            echo "   - {$user['name']} ({$user['email']})\n";
        }
    }
} else {
    echo "   ✗ Database error: " . $conn->error . "\n";
}

// Check 3: Database connection
echo "\n3. Database Connection:\n";
if ($conn->connect_error) {
    echo "   ✗ Connection failed: " . $conn->connect_error . "\n";
} else {
    echo "   ✓ Connected to: " . $conn->server_info . "\n";
}

// Check 4: Test direct access
echo "\n4. Testing Direct Access to admin_dashboard.php:\n";
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        echo "   ✓ YOU CAN ACCESS: admin_dashboard.php\n";
    } else {
        echo "   ✗ YOU CANNOT ACCESS: Your role '{$_SESSION['role']}' is not admin\n";
        echo "   ✗ SOLUTION: Log in with an admin account\n";
    }
} else {
    echo "   ✗ YOU CANNOT ACCESS: Not logged in\n";
    echo "   ✗ SOLUTION: Log in at auth/login.php first\n";
}

echo "\n=== END TEST ===\n";
?>
