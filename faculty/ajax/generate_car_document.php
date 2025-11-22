<?php
/**
 * Generate CAR - Now uses HTML Preview + PDF (not Word)
 * This file is deprecated - kept for backward compatibility
 * Redirects to the new flow
 */

session_start();
require_once '../../config/db.php';
require_once '../../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$class_code = $input['class_code'] ?? '';

if (empty($class_code)) {
    echo json_encode(['success' => false, 'message' => 'Class code required']);
    exit;
}

// This endpoint is now deprecated
// The new flow is:
// 1. User fills wizard → calls car_handler.php to save
// 2. Click "Complete" → calls CARManager.completeWizard()
// 3. Shows HTML preview via generate_car_html.php
// 4. User clicks "Download as PDF" → calls download_car_pdf.php

echo json_encode([
    'success' => false,
    'message' => 'This endpoint is deprecated. Use the new CAR flow instead.',
    'message_type' => 'deprecated'
]);