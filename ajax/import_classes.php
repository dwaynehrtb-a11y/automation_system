<?php
define('SYSTEM_ACCESS', true);
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

try {
    // Check if user is admin
    requireAdmin();
    
    // Verify AJAX request manually
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

    $spreadsheet = IOFactory::load($file['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    if (count($rows) < 2) {
        throw new Exception('No data found in Excel file');
    }

    // Start transaction
    $conn->begin_transaction();

    // Skip header row
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        
        if (count($row) < 7) continue;
        
        $section = trim($row[0] ?? '');
        $academic_year = trim($row[1] ?? '');
        $term = trim($row[2] ?? '');
        $course_code = trim($row[3] ?? '');
        $day = trim($row[4] ?? '');
        $time = trim($row[5] ?? '');
        $room = trim($row[6] ?? '');
        $faculty_id = isset($row[7]) ? intval($row[7]) : 0;

        if (empty($section) || empty($course_code)) {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Missing section or course code";
            continue;
        }

        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM class WHERE course_code = ? AND section = ? AND academic_year = ? AND term = ?");
        $check_stmt->bind_param("ssss", $course_code, $section, $academic_year, $term);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();

        if ($check_result['count'] > 0) {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Class already exists";
            continue;
        }

        $insert_stmt = $conn->prepare("INSERT INTO class (course_code, section, academic_year, term, day, time, room, faculty_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$insert_stmt) {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Database prepare failed";
            continue;
        }

        $insert_stmt->bind_param("sssssssi", $course_code, $section, $academic_year, $term, $day, $time, $room, $faculty_id);
        
        if ($insert_stmt->execute()) {
            $imported++;
        } else {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Database error - " . $insert_stmt->error;
        }
    }

    // Commit transaction
    $conn->commit();

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
    
    error_log("Import Classes Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Import failed: ' . $e->getMessage()
    ]);
}
?>
