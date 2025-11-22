<?php
/**
 * FIX MISSING STUDENT GRADES
 * Adds missing grade records for student 2022-171253 (Hayasaka)
 * Columns 177-180 (cw 3, cw 4, cw 5, cw 6)
 */

// Database connection
require_once 'config/db.php';

try {
    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['error' => $conn->connect_error]));
    }

    // Student info
    $student_id = '2022-171253';
    $class_code = 'INF222';  // Adjust if needed
    
    // Missing columns and their scores (based on what should exist)
    $missing_grades = [
        177 => 10.00,  // cw 3
        178 => 10.00,  // cw 4
        179 => 10.00,  // cw 5
        180 => 10.00   // cw 6
    ];

    echo "🔧 Fixing missing grades for student $student_id...\n\n";

    // Check what already exists
    echo "📋 Current grades for $student_id:\n";
    $checkStmt = $conn->prepare("
        SELECT column_id, raw_score 
        FROM student_flexible_grades 
        WHERE student_id_number = ? 
        AND column_id IN (175, 176, 177, 178, 179, 180)
        ORDER BY column_id
    ");
    $checkStmt->bind_param('s', $student_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    $existing = [];
    while ($row = $result->fetch_assoc()) {
        echo "  ✓ Column {$row['column_id']}: {$row['raw_score']}\n";
        $existing[$row['column_id']] = true;
    }
    $checkStmt->close();

    echo "\n📌 Missing columns that will be added:\n";
    foreach ($missing_grades as $col_id => $score) {
        if (!isset($existing[$col_id])) {
            echo "  + Column $col_id: $score\n";
        }
    }

    // Insert missing grades
    echo "\n⏳ Inserting missing grades...\n";
    $insertStmt = $conn->prepare("
        INSERT INTO student_flexible_grades 
        (student_id_number, column_id, raw_score, class_code, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE raw_score = VALUES(raw_score), updated_at = NOW()
    ");

    $inserted = 0;
    foreach ($missing_grades as $col_id => $score) {
        $insertStmt->bind_param('sids', $student_id, $col_id, $score, $class_code);
        if ($insertStmt->execute()) {
            if ($conn->affected_rows > 0) {
                echo "  ✅ Column $col_id: Inserted/Updated $score\n";
                $inserted++;
            }
        } else {
            echo "  ❌ Column $col_id: Error - " . $insertStmt->error . "\n";
        }
    }
    $insertStmt->close();

    // Verify the fix
    echo "\n✅ Verification - Updated grades for $student_id:\n";
    $verifyStmt = $conn->prepare("
        SELECT column_id, raw_score 
        FROM student_flexible_grades 
        WHERE student_id_number = ? 
        AND column_id IN (175, 176, 177, 178, 179, 180)
        ORDER BY column_id
    ");
    $verifyStmt->bind_param('s', $student_id);
    $verifyStmt->execute();
    $result = $verifyStmt->get_result();
    
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        echo "  ✓ Column {$row['column_id']}: {$row['raw_score']}\n";
        $count++;
    }
    $verifyStmt->close();

    echo "\n✨ COMPLETE: $count total grades now exist for $student_id\n";
    echo "\n🎯 Next: Hard refresh (Ctrl+Shift+F5) and reload the grading page.\n";

    $conn->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
