<?php
/**
 * QUICK FIX - Manual Grade Decryption
 * This script directly decrypts all grades for a class without going through the UI
 */
define('SYSTEM_ACCESS', true);
require_once 'config/db.php';
require_once 'config/encryption.php';

$class_code = 'CCPRGG1L';

echo "=== QUICK FIX: DIRECT GRADE DECRYPTION ===\n\n";

// Step 1: Check current status
echo "Step 1: Checking current encryption status...\n";
$stmt = $conn->prepare("SELECT COUNT(*) as encrypted FROM grade_term WHERE class_code = ? AND is_encrypted = 1");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$encrypted_count = $result['encrypted'];
$stmt->close();

echo "  Found $encrypted_count encrypted grades\n";

if ($encrypted_count === 0) {
    echo "  ✅ All grades are already decrypted!\n";
    exit;
}

// Step 2: Initialize encryption
echo "\nStep 2: Initializing encryption engine...\n";
Encryption::init();
echo "  ✅ Encryption engine ready\n";

// Step 3: Decrypt all grades
echo "\nStep 3: Decrypting $encrypted_count grade records...\n";

$stmt = $conn->prepare("
    SELECT id, term_grade, midterm_percentage, finals_percentage, term_percentage 
    FROM grade_term 
    WHERE class_code = ? AND is_encrypted = 1
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

$fields = ['term_grade', 'midterm_percentage', 'finals_percentage', 'term_percentage'];
$success = 0;
$failed = 0;
$errors = [];

$conn->begin_transaction();

while ($row = $result->fetch_assoc()) {
    try {
        $dec = [];
        foreach ($fields as $f) {
            $val = $row[$f];
            if ($val !== null && $val !== '') {
                try {
                    $dec[$f] = Encryption::decrypt($val);
                } catch (Exception $e) {
                    $dec[$f] = $val;
                }
            } else {
                $dec[$f] = $val;
            }
        }
        
        $upd = $conn->prepare("UPDATE grade_term SET term_grade=?, midterm_percentage=?, finals_percentage=?, term_percentage=?, is_encrypted=0 WHERE id=?");
        $upd->bind_param('ssssi', $dec['term_grade'], $dec['midterm_percentage'], $dec['finals_percentage'], $dec['term_percentage'], $row['id']);
        
        if ($upd->execute()) {
            $success++;
        } else {
            $failed++;
            $errors[] = $upd->error;
        }
        $upd->close();
        
    } catch (Exception $e) {
        $failed++;
        $errors[] = $e->getMessage();
    }
}

$stmt->close();

try {
    $conn->commit();
    echo "  ✅ Database transaction committed\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "  ❌ Database commit failed: " . $e->getMessage() . "\n";
    exit;
}

echo "  - Successfully decrypted: $success\n";
echo "  - Failed: $failed\n";

if (!empty($errors)) {
    echo "  Errors: " . implode(", ", $errors) . "\n";
}

// Step 4: Update visibility status
echo "\nStep 4: Updating visibility status records...\n";
$stmt = $conn->prepare("SELECT DISTINCT student_id FROM class_enrollments WHERE class_code = ?");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

$vis_count = 0;
while ($row = $result->fetch_assoc()) {
    $student_id = $row['student_id'];
    $faculty_id = 1;
    
    $vis = $conn->prepare("
        INSERT INTO grade_visibility_status (student_id, class_code, grade_visibility, changed_by) 
        VALUES (?, ?, 'visible', ?) 
        ON DUPLICATE KEY UPDATE grade_visibility='visible', changed_by=?, visibility_changed_at=NOW()
    ");
    $vis->bind_param('ssii', $student_id, $class_code, $faculty_id, $faculty_id);
    $vis->execute();
    $vis->close();
    $vis_count++;
}
$stmt->close();
echo "  ✅ Updated $vis_count student visibility records\n";

// Step 5: Verify
echo "\nStep 5: Verifying decryption...\n";
$stmt = $conn->prepare("SELECT COUNT(*) as encrypted FROM grade_term WHERE class_code = ? AND is_encrypted = 1");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$remaining = $result['encrypted'];
$stmt->close();

if ($remaining === 0) {
    echo "  ✅ All grades are now DECRYPTED!\n";
} else {
    echo "  ❌ Still have $remaining encrypted grades\n";
}

echo "\n=== FIX COMPLETE ===\n";
echo "\nNext steps:\n";
echo "1. Students and faculty should hard-refresh their browsers (Ctrl+Shift+R)\n";
echo "2. Students should now see their grades in the class cards\n";
echo "3. Faculty can verify in the Summary tab\n";

?>
