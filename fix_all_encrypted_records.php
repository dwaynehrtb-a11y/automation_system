<?php
/**
 * Fix: Set is_encrypted = 0 for records with visible grades
 * This ensures students see their grades correctly instead of "Grades not yet released"
 */

define('SYSTEM_ACCESS', true);
require_once 'config/db.php';

echo "<pre>";
echo "=== FIXING ENCRYPTION FLAGS ===\n\n";

// Find all records with is_encrypted = 1 where they should be visible
// (assuming all records in grade_term that have grades should be visible)
$result = $conn->query("
    SELECT id, student_id, class_code, is_encrypted, grade_status, term_percentage
    FROM grade_term
    WHERE is_encrypted = 1
    LIMIT 20
");

$count = 0;
if ($result && $result->num_rows > 0) {
    echo "Found " . $result->num_rows . " encrypted records. Fixing...\n\n";
    
    while($row = $result->fetch_assoc()) {
        echo "Fixing: Student {$row['student_id']}, Class {$row['class_code']}\n";
        echo "  Current: is_encrypted={$row['is_encrypted']}, status={$row['grade_status']}, term%={$row['term_percentage']}\n";
        
        // Update this record
        $update = $conn->prepare("UPDATE grade_term SET is_encrypted = 0 WHERE id = ?");
        $update->bind_param('i', $row['id']);
        if ($update->execute()) {
            echo "  ✅ Updated\n";
            $count++;
        } else {
            echo "  ❌ Error: " . $update->error . "\n";
        }
        $update->close();
    }
} else {
    echo "No encrypted records found, or query error\n";
}

echo "\n✅ Fixed $count records\n";
echo "</pre>";
?>
