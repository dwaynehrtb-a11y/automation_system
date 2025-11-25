<?php
header('Content-Type: application/json');

define('SYSTEM_ACCESS', true);
require_once '../config/db.php';

$response = ['success' => false, 'message' => ''];

try {
    $student_id = '2025-276819';
    $class_code = '25_T2_CCPRGG1L_INF223';
    
    // Check current state
    $check_stmt = $conn->prepare("SELECT id, is_encrypted, grade_status FROM grade_term WHERE student_id = ? AND class_code = ?");
    $check_stmt->bind_param('ss', $student_id, $class_code);
    $check_stmt->execute();
    $current = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if (!$current) {
        $response['message'] = 'No grade record found for this student';
        echo json_encode($response);
        exit;
    }
    
    // Update is_encrypted to 0
    $update_stmt = $conn->prepare("UPDATE grade_term SET is_encrypted = 0 WHERE student_id = ? AND class_code = ?");
    $update_stmt->bind_param('ss', $student_id, $class_code);
    $update_stmt->execute();
    $affected = $update_stmt->affected_rows;
    $update_stmt->close();
    
    if ($affected > 0) {
        $response['success'] = true;
        $response['message'] = "Successfully updated! is_encrypted changed from {$current['is_encrypted']} to 0";
        $response['current_status'] = $current['grade_status'];
    } else {
        $response['message'] = 'No rows were updated';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
