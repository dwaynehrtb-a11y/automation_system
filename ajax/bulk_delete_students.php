<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['student_ids']) || !is_array($data['student_ids']) || empty($data['student_ids'])) {
    echo json_encode(['success' => false, 'message' => 'No students selected']);
    exit;
}

$student_ids = $data['student_ids'];
$deleted_count = 0;
$errors = [];

try {
    $conn->begin_transaction();
    
    foreach ($student_ids as $student_id) {
        $student_id = trim($student_id);
        
        if (empty($student_id)) {
            continue;
        }
        
        try {
            // Delete from class_enrollments first (foreign key)
            $stmt = $conn->prepare("DELETE FROM class_enrollments WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            
            // Delete from grade_term (if exists)
            $stmt = $conn->prepare("DELETE FROM grade_term WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            
            // Delete from student table
            $stmt = $conn->prepare("DELETE FROM student WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $deleted_count++;
                } else {
                    $errors[] = "Student $student_id not found";
                }
            } else {
                $errors[] = "Failed to delete student $student_id";
            }
        } catch (Exception $e) {
            $errors[] = "Error deleting student $student_id: " . $e->getMessage();
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'deleted' => $deleted_count,
        'errors' => $errors,
        'message' => "Successfully deleted $deleted_count student(s)"
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
