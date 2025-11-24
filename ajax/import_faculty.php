<?php
define('SYSTEM_ACCESS', true);
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

if (!isset($_FILES['file'])) {
    exit(json_encode(['success' => false, 'message' => 'No file uploaded']));
}

try {
    require_once __DIR__ . '/../vendor/autoload.php';

    $file = $_FILES['file']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    $imported = 0;
    $skipped = 0;
    $errors = [];

    // Start transaction
    $conn->begin_transaction();

    // Process rows starting from row 3 (skip headers in row 2)
    for ($i = 2; $i < count($rows); $i++) {
        $row = $rows[$i];
        
        // Skip empty rows
        if (empty($row[0])) {
            continue;
        }

        // Extract data from Excel columns
        $employee_id = trim((string)$row[0]); // A: Employee ID
        $fullname = trim((string)$row[1]); // B: Full Name
        $email = trim((string)$row[2]); // C: Email
        $phone = isset($row[3]) ? trim((string)$row[3]) : ''; // D: Phone
        $department = isset($row[4]) ? trim((string)$row[4]) : ''; // E: Department

        // Validation
        if (empty($employee_id)) {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Employee ID is required";
            continue;
        }

        if (empty($fullname)) {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Full Name is required";
            continue;
        }

        if (empty($email)) {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Email is required";
            continue;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Invalid email format";
            continue;
        }

        // Check if employee_id already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'faculty'");
        $check_stmt->bind_param("s", $employee_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($check_result) {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Faculty with ID " . $employee_id . " already exists";
            continue;
        }

        // Check if email already exists
        $email_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $email_check->bind_param("s", $email);
        $email_check->execute();
        $email_result = $email_check->get_result()->fetch_assoc();
        $email_check->close();

        if ($email_result) {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Email " . $email . " already registered";
            continue;
        }

        // Generate default password (8 random characters)
        $default_password = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

        // Insert into users table
        $insert_stmt = $conn->prepare(
            "INSERT INTO users (id, fullname, email, password, phone, role) VALUES (?, ?, ?, ?, ?, 'faculty')"
        );

        if (!$insert_stmt) {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Database prepare failed";
            continue;
        }

        $insert_stmt->bind_param("sssss", $employee_id, $fullname, $email, $hashed_password, $phone);

        if ($insert_stmt->execute()) {
            // Store credentials for notification
            $_SESSION['faculty_credentials_' . $employee_id] = [
                'fullname' => $fullname,
                'email' => $email,
                'password' => $default_password,
                'employee_id' => $employee_id
            ];
            $imported++;
        } else {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Database error - " . $insert_stmt->error;
        }

        $insert_stmt->close();
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
        'message' => "Successfully imported " . $imported . " faculty member(s)"
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        try {
            $conn->rollback();
        } catch (Exception $rollback_error) {
            error_log("Rollback error: " . $rollback_error->getMessage());
        }
    }
    
    error_log("Import Faculty Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Import failed: ' . $e->getMessage()
    ]);
}
?>
