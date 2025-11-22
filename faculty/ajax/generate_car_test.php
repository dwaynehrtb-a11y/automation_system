<?php
/**
 * Test: Generate CAR - MINIMAL version to debug
 */

// === ERROR HANDLING ===
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

// === LOAD DEPENDENCIES ===
require_once '../../config/db.php';
require_once '../../config/session.php';

// === SET HEADERS ===
header('Content-Type: application/json');

// === SESSION ===
session_start();

try {
    ob_clean();
    
    // === AUTH ===
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
        throw new Exception('Unauthorized');
    }
    
    $faculty_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $class_code = $input['class_code'] ?? '';
    
    if (empty($class_code)) {
        throw new Exception('Class code required');
    }
    
    // === VERIFY CLASS ===
    $stmt = $conn->prepare("SELECT * FROM class WHERE class_code = ? AND faculty_id = ? LIMIT 1");
    $stmt->bind_param("si", $class_code, $faculty_id);
    $stmt->execute();
    $class = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$class) {
        throw new Exception('Class not found');
    }
    
    // === GET BASIC INFO ===
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM class_enrollments WHERE class_code = ? AND status = 'enrolled'");
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $studentCount = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    
    // === TEST: Just return data (no document generation yet) ===
    echo json_encode([
        'success' => true,
        'message' => 'Test successful - ready for document generation',
        'class_code' => $class_code,
        'class_id' => $class['class_id'],
        'course_code' => $class['course_code'],
        'student_count' => $studentCount
    ]);
    exit;
    
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}
?>