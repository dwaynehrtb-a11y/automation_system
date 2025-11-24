<?php
// Direct database connection without session requirement
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'automation_system';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Setting up faculty from template data...\n";
echo str_repeat("-", 80) . "\n";

// Faculty data from the Excel template
$faculty_data = [
    [
        'id' => '2022-180250',
        'fullname' => 'Dr. Juan Santos',
        'email' => 'juan.santos@university.edu',
        'phone' => '09-555-1234'
    ],
    [
        'id' => '2023-182914',
        'fullname' => 'Dr. Maria Cruz',
        'email' => 'maria.cruz@university.edu',
        'phone' => '09-555-1235'
    ]
];

$imported = 0;
$skipped = 0;

foreach ($faculty_data as $fac) {
    // Check if already exists by employee_id
    $check = $conn->prepare("SELECT id FROM users WHERE employee_id = ?");
    
    if (!$check) {
        echo "✗ Prepare error: " . $conn->error . "\n";
        continue;
    }
    
    $check->bind_param("s", $fac['id']);
    $check->execute();
    $checkResult = $check->get_result();
    
    if ($checkResult->num_rows > 0) {
        $row = $checkResult->fetch_assoc();
        echo "⊘ Faculty with employee_id {$fac['id']} already exists (id={$row['id']}) - skipping\n";
        $skipped++;
        $check->close();
        continue;
    }
    $check->close();

    // Generate default password
    $default_password = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

    // Insert faculty with employee_id mapping
    $insert = $conn->prepare("INSERT INTO users (name, employee_id, email, password, role, account_status) VALUES (?, ?, ?, ?, 'faculty', 'active')");
    
    if (!$insert) {
        echo "✗ Insert prepare error: " . $conn->error . "\n";
        continue;
    }
    
    $insert->bind_param("ssss", $fac['fullname'], $fac['id'], $fac['email'], $hashed_password);
    
    if ($insert->execute()) {
        echo "✓ Faculty {$fac['id']} ({$fac['fullname']}) imported with user ID {$insert->insert_id} - Password: {$default_password}\n";
        $imported++;
    } else {
        echo "✗ Error importing {$fac['id']}: " . $insert->error . "\n";
        $skipped++;
    }
    $insert->close();
}

echo "\n" . str_repeat("-", 80) . "\n";
echo "Result: $imported imported, $skipped skipped\n";
echo "\nYou can now import the class Excel file again!\n";

$conn->close();
?>
