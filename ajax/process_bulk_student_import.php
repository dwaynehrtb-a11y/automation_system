<?php
/**
 * Bulk Student Import Processor - Excel Version
 * Handles Excel file upload and creates multiple student accounts
 */

define('SYSTEM_ACCESS', true);
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/email_helper.php';
require_once '../config/encryption.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

try {
    // Check if user is admin
    requireAdmin();
    
    // Verify AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        http_response_code(403);
        throw new Exception('Invalid request - not an AJAX call');
    }

    // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken ?? '')) {
        http_response_code(403);
        throw new Exception('CSRF token validation failed');
    }

    // Check if file was uploaded
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['csv_file'];

    // Validate file type
    $validExtensions = ['xlsx', 'xls'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExtension, $validExtensions)) {
        throw new Exception('Invalid file type. Only Excel files (.xlsx, .xls) are allowed.');
    }

    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }

    $imported = 0;
    $skipped = 0;
    $errors = [];

    // Load Excel file
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    // Start from row 3 (skip warning row 1 and header row 2)
    if ($highestRow < 3) {
        throw new Exception('Excel file is empty or missing data rows');
    }

    // Start transaction
    $conn->begin_transaction();

    // Process each row
    for ($row = 3; $row <= $highestRow; $row++) {
        // Read row data
        $student_id = trim($sheet->getCell('A' . $row)->getValue());
        $last_name = trim($sheet->getCell('B' . $row)->getValue());
        $first_name = trim($sheet->getCell('C' . $row)->getValue());
        $middle_initial = trim($sheet->getCell('D' . $row)->getValue());
        $email = trim($sheet->getCell('E' . $row)->getValue());
        $birthday = $sheet->getCell('F' . $row)->getValue();
        $status = trim($sheet->getCell('G' . $row)->getValue());

        // Skip empty rows
        if (empty($student_id) && empty($email)) {
            continue;
        }

        // Validate required fields
        if (empty($student_id) || empty($last_name) || empty($first_name) || empty($email)) {
            $skipped++;
            $errors[] = "Row $row: Missing required fields (Student ID, Last Name, First Name, or Email)";
            continue;
        }

        // Validate student ID format (YYYY-XXXXXX)
        if (!preg_match('/^\d{4}-\d{6}$/', $student_id)) {
            $skipped++;
            $errors[] = "Row $row: Invalid Student ID format '$student_id' (must be YYYY-XXXXXX, e.g., 2024-123456)";
            continue;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skipped++;
            $errors[] = "Row $row: Invalid email format '$email'";
            continue;
        }

        // Set default status if empty
        if (empty($status)) {
            $status = 'active';
        }

        // Validate status
        $valid_statuses = ['active', 'inactive', 'graduated', 'transferred', 'suspended', 'pending'];
        if (!in_array(strtolower($status), $valid_statuses)) {
            $skipped++;
            $errors[] = "Row $row: Invalid status '$status' (must be: active, inactive, graduated, transferred, suspended, or pending)";
            continue;
        }

        // Format birthday if it's an Excel date serial number
        if (is_numeric($birthday)) {
            $birthday = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($birthday)->format('Y-m-d');
        } elseif (!empty($birthday)) {
            // Try to parse the date
            $date = date_create($birthday);
            if ($date) {
                $birthday = date_format($date, 'Y-m-d');
            } else {
                $skipped++;
                $errors[] = "Row $row: Invalid birthday format '$birthday' (must be YYYY-MM-DD)";
                continue;
            }
        } else {
            $birthday = null;
        }

        // Check if student ID already exists in student table
        $check_id = $conn->prepare("SELECT COUNT(*) as count FROM student WHERE student_id = ?");
        $check_id->bind_param('s', $student_id);
        $check_id->execute();
        $id_result = $check_id->get_result()->fetch_assoc();

        if ($id_result['count'] > 0) {
            $skipped++;
            $errors[] = "Row $row: Student ID '$student_id' already exists";
            continue;
        }

        // Check if ID exists in faculty (users table)
        $check_faculty = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE employee_id = ? AND role = 'faculty'");
        $check_faculty->bind_param('s', $student_id);
        $check_faculty->execute();
        $faculty_result = $check_faculty->get_result()->fetch_assoc();

        if ($faculty_result['count'] > 0) {
            $skipped++;
            $errors[] = "Row $row: ID '$student_id' is already used by a faculty member";
            continue;
        }

        // Check if email already exists in student table
        $check_email = $conn->prepare("SELECT COUNT(*) as count FROM student WHERE email = ?");
        $check_email->bind_param('s', $email);
        $check_email->execute();
        $email_result = $check_email->get_result()->fetch_assoc();

        if ($email_result['count'] > 0) {
            $skipped++;
            $errors[] = "Row $row: Email '$email' already exists";
            continue;
        }

        // Check if email exists in users table (faculty/admin)
        $check_users_email = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
        $check_users_email->bind_param('s', $email);
        $check_users_email->execute();
        $users_email_result = $check_users_email->get_result()->fetch_assoc();

        if ($users_email_result['count'] > 0) {
            $skipped++;
            $errors[] = "Row $row: Email '$email' is already used by a faculty or admin account";
            continue;
        }

        // Generate temporary password
        $temporary_password = generateRandomPassword(12);
        $hashed_password = password_hash($temporary_password, PASSWORD_DEFAULT);

        // Initialize encryption
        Encryption::init();

        // Encrypt sensitive data
        $encrypted_first_name = !empty($first_name) ? Encryption::encrypt($first_name) : '';
        $encrypted_last_name = !empty($last_name) ? Encryption::encrypt($last_name) : '';
        $encrypted_email = !empty($email) ? Encryption::encrypt($email) : '';
        $encrypted_birthday = !empty($birthday) ? Encryption::encrypt($birthday) : '';

        // Insert student record
        $insert_query = "INSERT INTO student (
            last_name, 
            first_name, 
            middle_initial, 
            student_id, 
            email, 
            birthday, 
            status,
            password,
            must_change_password,
            account_status,
            enrollment_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW())";

        $insert_stmt = $conn->prepare($insert_query);

        if (!$insert_stmt) {
            $skipped++;
            $errors[] = "Row $row: Database prepare failed - " . $conn->error;
            continue;
        }

        $middle_initial_value = !empty($middle_initial) ? $middle_initial : null;

        $insert_stmt->bind_param("ssssssss",
            $encrypted_last_name,
            $encrypted_first_name,
            $middle_initial_value,
            $student_id,
            $encrypted_email,
            $encrypted_birthday,
            $status,              
            $hashed_password
        );

        if ($insert_stmt->execute()) {
            // Build full name
            $full_name = $first_name . ' ' . ($middle_initial ? $middle_initial . '. ' : '') . $last_name;
            
            // Count as imported immediately
            $imported++;

            // Try sending email immediately (with timeout)
            try {
                $email_result = sendStudentAccountCreationEmail(
                    $email, 
                    $full_name, 
                    $student_id, 
                    $temporary_password
                );
                
                if ($email_result['success']) {
                    error_log("Email sent successfully to {$email}");
                } else {
                    error_log("Email sending failed for {$email}: " . $email_result['message']);
                }
            } catch (Exception $e) {
                error_log("Email exception for {$email}: " . $e->getMessage());
            }
        } else {
            $skipped++;
            $errors[] = "Row $row: Database insert failed - " . $insert_stmt->error;
        }
    }

    // Commit transaction 
    $conn->commit();

    // Log audit action
    $details = "Bulk imported $imported student(s) from Excel";
    if ($skipped > 0) {
        $details .= " with $skipped error(s)";
    }

    try {
        $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, username, action, details, timestamp, ip_address) 
                                    VALUES (?, ?, 'BULK_IMPORT_STUDENTS', ?, NOW(), ?)");
        if ($log_stmt) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $log_stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['name'], $details, $ip_address);
            $log_stmt->execute();
        }
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        try {
            $conn->rollback();
        } catch (Exception $rollback_error) {
            error_log("Rollback error: " . $rollback_error->getMessage());
        }
    }
    
    error_log("CSV Import Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Import failed: ' . $e->getMessage()
    ]);
}
?>
