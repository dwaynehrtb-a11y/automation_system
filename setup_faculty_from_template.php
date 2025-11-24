<?php
require_once 'config/db.php';

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
    // Check if already exists
    $check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $check->bind_param("s", $fac['id']);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        echo "⊘ Faculty {$fac['id']} already exists - skipping\n";
        $skipped++;
        $check->close();
        continue;
    }
    $check->close();

    // Generate default password
    $default_password = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

    // Insert faculty
    $insert = $conn->prepare("INSERT INTO users (id, fullname, email, password, phone, role) VALUES (?, ?, ?, ?, ?, 'faculty')");
    $insert->bind_param("sssss", $fac['id'], $fac['fullname'], $fac['email'], $hashed_password, $fac['phone']);
    
    if ($insert->execute()) {
        echo "✓ Faculty {$fac['id']} ({$fac['fullname']}) imported - Password: {$default_password}\n";
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
?>
