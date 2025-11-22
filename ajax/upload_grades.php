<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

requireAdmin();
verifyAjaxRequest();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['grades_file']) || $_FILES['grades_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error occurred']);
    exit;
}

$file = $_FILES['grades_file'];

// Validate file type
$validExtensions = ['xlsx', 'xls'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $validExtensions)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file type. Only Excel files (.xlsx, .xls) are allowed.'
    ]);
    exit;
}

// Validate file size (5MB max)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode([
        'success' => false,
        'message' => 'File too large. Maximum size is 5MB.'
    ]);
    exit;
}

$tmpFile = $file['tmp_name'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    $spreadsheet = IOFactory::load($tmpFile);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // Remove header row
    unset($rows[0]);

    $processed = 0;
    $errors = 0;
    $errorMessages = [];

    foreach ($rows as $row) {
        if (count($row) < 19 || empty($row[1])) continue; // Skip incomplete rows

        try {
            // Adjust indices according to your Excel layout
            $student_name       = $row[1];
            $report_score       = $row[2];
            $report_eq          = $row[3];
            $assignment_score   = $row[4];
            $assignment_eq      = $row[5];
            $att_score          = $row[6];
            $att_eq             = $row[7];
            $sw1_score          = $row[8];
            $sw1_eq             = $row[9];
            $sw2_score          = $row[10];
            $sw2_eq             = $row[11];
            $quiz_score         = $row[12];
            $quiz_eq            = $row[13];
            $final_30eq         = $row[14]; // class performance average (AVE)
            $exam_score         = $row[15];
            $exam_eq            = $row[16];
            $raw_periodic_grade = $row[17];
            $final_grade        = $row[18];

            $stmt = $conn->prepare("INSERT INTO finals_grades (
                student_name, report_score, report_eq, assignment_score, assignment_eq,
                att_score, att_eq, sw1_score, sw1_eq, sw2_score, sw2_eq,
                quiz_score, quiz_eq, exam_score, exam_eq,
                final_30eq, raw_periodic_grade, final_grade
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param("sidiidididididdddd",
                $student_name,
                $report_score, $report_eq,
                $assignment_score, $assignment_eq,
                $att_score, $att_eq,
                $sw1_score, $sw1_eq,
                $sw2_score, $sw2_eq,
                $quiz_score, $quiz_eq,
                $exam_score, $exam_eq,
                $final_30eq, $raw_periodic_grade, $final_grade
            );

            if ($stmt->execute()) {
                $processed++;
            } else {
                $errors++;
                $errorMessages[] = "Row $row: Database error";
            }
        } catch (Exception $e) {
            $errors++;
            $errorMessages[] = "Row $row: " . $e->getMessage();
            error_log("Grade upload row error: " . $e->getMessage());
        }
    }
    
    // Commit transaction if no errors
    if ($errors === 0) {
        $conn->commit();
    } else {
        $conn->rollback();
    }

    if ($errors === 0 && $processed > 0) {
        echo json_encode([
            'success' => true, 
            'processed' => $processed,
            'errors' => $errors,
            'message' => "Grades uploaded successfully! Processed: $processed rows"
        ]);
    } else if ($processed > 0 && $errors > 0) {
        echo json_encode([
            'success' => false,
            'processed' => $processed,
            'errors' => $errors,
            'errorDetails' => $errorMessages,
            'message' => "Upload partially failed. Processed: $processed rows, Errors: $errors (Transaction rolled back)"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No grades were uploaded']);
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log("Excel upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error loading Excel file: ' . $e->getMessage()]);
}
?>