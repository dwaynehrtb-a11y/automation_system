<?php
/**
 * DEBUG: Generate CAR Document - Show actual errors
 */

// === ERROR HANDLING ===
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Capture any output
ob_start();

// === LOAD DEPENDENCIES ===
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../vendor/autoload.php';

// === USE STATEMENTS (MUST be at file level, outside try) ===
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\IOFactory;

// === SET HEADERS BEFORE TRY ===
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// === START SESSION ===
session_start();

try {
    // Clear output buffer
    ob_clean();
    
    // Authentication check
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $faculty_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $class_code = $input['class_code'] ?? '';
    
    if (empty($class_code)) {
        echo json_encode(['success' => false, 'message' => 'Class code required']);
        exit;
    }
    
    // Test database connection
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'All checks passed',
        'class_code' => $class_code,
        'faculty_id' => $faculty_id
    ]);
    
} catch (Throwable $e) {
    // Catch ALL errors including parse errors
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>