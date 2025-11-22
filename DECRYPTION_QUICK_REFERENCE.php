<?php
/**
 * QUICK DECRYPTION REFERENCE - Copy & Paste Code Examples
 * 
 * This file contains ready-to-use code snippets for decrypting data
 * Just copy the relevant section into your code!
 */

// ============================================================================
// EXAMPLE 1: Decrypt Student Data Using StudentModel (RECOMMENDED)
// ============================================================================

/*
<?php
require_once 'includes/StudentModel.php';

$studentModel = new StudentModel($conn);

// Get ONE student (auto-decrypts everything)
$student = $studentModel->getStudentById(
    '2024-001',              // Student ID
    $_SESSION['user_id'],    // Who's viewing
    $_SESSION['role']        // Their role
);

if ($student) {
    echo "Name: " . $student['first_name'] . " " . $student['last_name'];
    echo "Email: " . $student['email'];
    echo "Birthday: " . $student['birthday'];
} else {
    echo "Not found or access denied";
}
?>
*/


// ============================================================================
// EXAMPLE 2: Decrypt Grade Data Using GradesModel (RECOMMENDED)
// ============================================================================

/*
<?php
require_once 'includes/GradesModel.php';

$gradesModel = new GradesModel($conn);

// Get grades for ONE student (auto-decrypts everything)
$grades = $gradesModel->getStudentGrades(
    '2024-001',              // Student ID
    'CS101-2024-A',          // Class code
    $_SESSION['user_id'],    // Who's viewing
    $_SESSION['role']        // Their role
);

if ($grades) {
    echo "Term: " . $grades['term_grade'];
    echo "Midterm: " . $grades['midterm_percentage'] . "%";
    echo "Finals: " . $grades['finals_percentage'] . "%";
}
?>
*/


// ============================================================================
// EXAMPLE 3: Manual Decryption (For Advanced Use)
// ============================================================================

/*
<?php
require_once 'config/encryption.php';

// Step 1: Initialize encryption
Encryption::init();

// Step 2: Get encrypted value from database
$encrypted_value = "base64EncodedStringFromDB";

// Step 3: Decrypt it
$plaintext = Encryption::decrypt($encrypted_value);

// Step 4: Use it
echo $plaintext;

// Or with error handling:
try {
    $plaintext = Encryption::decrypt($encrypted_value);
    echo "Decrypted: " . $plaintext;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
*/


// ============================================================================
// EXAMPLE 4: Bulk Decrypt All Students
// ============================================================================

/*
<?php
require_once 'includes/StudentModel.php';

$studentModel = new StudentModel($conn);

// Get ALL students (auto-decrypts)
$students = $studentModel->getAllStudents(
    [],                      // No filters
    100,                     // Limit
    0,                       // Offset
    $_SESSION['user_id'],    // Who's viewing
    $_SESSION['role']        // Their role
);

foreach ($students as $student) {
    echo $student['student_id'] . " | ";
    echo $student['first_name'] . " | ";
    echo $student['email'] . "\n";
}
?>
*/


// ============================================================================
// EXAMPLE 5: Export Decrypted Data to CSV
// ============================================================================

/*
<?php
require_once 'config/db.php';
require_once 'config/encryption.php';

Encryption::init();

// Query encrypted data
$result = $conn->query("SELECT * FROM students LIMIT 100");

// Create CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="students.csv"');

$csv = fopen('php://output', 'w');

// Header row
fputcsv($csv, ['ID', 'First Name', 'Last Name', 'Email', 'Birthday']);

// Data rows (decrypted)
while ($row = $result->fetch_assoc()) {
    fputcsv($csv, [
        $row['student_id'],
        Encryption::decrypt($row['first_name']),
        Encryption::decrypt($row['last_name']),
        Encryption::decrypt($row['email']),
        Encryption::decrypt($row['birthday'])
    ]);
}

fclose($csv);
?>
*/


// ============================================================================
// EXAMPLE 6: Check if Encryption Key is Loaded
// ============================================================================

/*
<?php
require_once 'config/encryption.php';

try {
    Encryption::init();
    $key = Encryption::getKey();
    echo "✓ Encryption key loaded (" . strlen($key) . " bytes)";
} catch (Exception $e) {
    echo "✗ Error loading encryption key: " . $e->getMessage();
}
?>
*/


// ============================================================================
// EXAMPLE 7: Decrypt Field with Null Handling
// ============================================================================

/*
<?php
require_once 'config/encryption.php';

Encryption::init();

// Safe decryption - returns null if empty
$value = Encryption::decryptField($encrypted_value);

if ($value === null) {
    echo "Field is empty or not encrypted";
} else {
    echo "Decrypted: " . $value;
}
?>
*/


// ============================================================================
// EXAMPLE 8: Display Student Data in HTML Table
// ============================================================================

/*
<?php
require_once 'includes/StudentModel.php';

$studentModel = new StudentModel($conn);

$students = $studentModel->getAllStudents(
    ['course_code' => 'CS101'],
    50,
    0,
    $_SESSION['user_id'],
    $_SESSION['role']
);
?>

<table border="1">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Birthday</th>
    </tr>
    <?php foreach ($students as $student): ?>
    <tr>
        <td><?= htmlspecialchars($student['student_id']) ?></td>
        <td><?= htmlspecialchars($student['first_name']) ?> <?= htmlspecialchars($student['last_name']) ?></td>
        <td><?= htmlspecialchars($student['email']) ?></td>
        <td><?= htmlspecialchars($student['birthday']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php
*/


// ============================================================================
// EXAMPLE 9: Display Grades with Decryption
// ============================================================================

/*
<?php
require_once 'includes/GradesModel.php';

$gradesModel = new GradesModel($conn);

// Get all grades for a class
$grades = $gradesModel->getClassGrades(
    'CS101-2024-A',
    $_SESSION['user_id'],
    $_SESSION['role']
);
?>

<table border="1">
    <tr>
        <th>Student ID</th>
        <th>Term Grade</th>
        <th>Midterm %</th>
        <th>Finals %</th>
    </tr>
    <?php foreach ($grades as $grade): ?>
    <tr>
        <td><?= htmlspecialchars($grade['student_id']) ?></td>
        <td><?= htmlspecialchars($grade['term_grade']) ?></td>
        <td><?= htmlspecialchars($grade['midterm_percentage']) ?>%</td>
        <td><?= htmlspecialchars($grade['finals_percentage']) ?>%</td>
    </tr>
    <?php endforeach; ?>
</table>

<?php
*/


// ============================================================================
// EXAMPLE 10: Access Control - Only Admin Can Decrypt
// ============================================================================

/*
<?php
require_once 'config/session.php';

// This will redirect to login if not admin
requireAdmin();

// Now you can safely decrypt
require_once 'config/encryption.php';
Encryption::init();

$plaintext = Encryption::decrypt($encrypted_value);
echo $plaintext;
?>
*/


// ============================================================================
// TROUBLESHOOTING TEMPLATE
// ============================================================================

/*
<?php
require_once 'config/db.php';
require_once 'config/encryption.php';

echo "=== ENCRYPTION TROUBLESHOOTING ===\n";

// Check 1: Encryption initialized?
try {
    Encryption::init();
    echo "✓ Encryption initialized\n";
} catch (Exception $e) {
    echo "✗ Initialization failed: " . $e->getMessage() . "\n";
    die();
}

// Check 2: Can access database?
try {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM students");
    $row = $result->fetch_assoc();
    echo "✓ Database connected ({$row['cnt']} students)\n";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    die();
}

// Check 3: Can decrypt sample data?
try {
    $result = $conn->query("SELECT first_name FROM students LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $encrypted = $row['first_name'];
        
        if (empty($encrypted)) {
            echo "✗ No encrypted data to test\n";
        } else {
            $plaintext = Encryption::decrypt($encrypted);
            echo "✓ Decryption successful: " . htmlspecialchars($plaintext) . "\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Decryption failed: " . $e->getMessage() . "\n";
}

echo "=== END TROUBLESHOOTING ===\n";
?>
*/

?>

---

## Summary: Decryption Methods

### Method 1: Use Web Tool (No Code Needed)
```
→ Go to: admin/decrypt_viewer.php
→ Enter student ID or grade ID
→ Click decrypt button
```

### Method 2: Use Model Classes (Recommended for Code)
```php
$studentModel = new StudentModel($conn);
$student = $studentModel->getStudentById('2024-001', $user_id, $user_role);
echo $student['first_name'];  // Already decrypted!
```

### Method 3: Manual Decryption (For Advanced)
```php
Encryption::init();
$plaintext = Encryption::decrypt($encrypted_value);
```

---

**All examples above are copy-paste ready!**
Just uncomment the section you need and use it in your code.
