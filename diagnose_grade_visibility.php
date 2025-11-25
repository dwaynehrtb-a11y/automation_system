<?php
/**
 * Diagnostic Tool - Check Why Students See Locked Grades Despite Faculty Showing Them
 */
require_once 'config/db.php';

$class_code = 'CCPRGG1L';
$student_id = '2025-276819';

echo "<h1>🔍 Grade Visibility Diagnosis</h1>";
echo "<hr>";

echo "<h2>1. Database State for Class: $class_code</h2>";

// Check grade_term encryption status
$stmt = $conn->prepare("
    SELECT 
        student_id, 
        is_encrypted,
        SUBSTR(term_grade, 1, 30) as term_grade_preview,
        grade_status
    FROM grade_term 
    WHERE class_code = ?
    LIMIT 5
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Grade Encryption Status:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Student ID</th><th>is_encrypted</th><th>grade_status</th><th>term_grade (preview)</th></tr>";
while ($row = $result->fetch_assoc()) {
    $encrypted_status = ($row['is_encrypted'] == 1) ? "🔒 ENCRYPTED (1)" : "🔓 DECRYPTED (0)";
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
    echo "<td>" . $encrypted_status . "</td>";
    echo "<td>" . htmlspecialchars($row['grade_status']) . "</td>";
    echo "<td>" . htmlspecialchars($row['term_grade_preview']) . "</td>";
    echo "</tr>";
}
echo "</table>";
$stmt->close();

echo "<hr>";
echo "<h2>2. Specific Student: $student_id in Class $class_code</h2>";

$stmt = $conn->prepare("
    SELECT 
        student_id,
        is_encrypted,
        term_grade,
        midterm_percentage,
        finals_percentage,
        term_percentage,
        grade_status
    FROM grade_term 
    WHERE student_id = ? AND class_code = ?
    LIMIT 1
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<p><strong>is_encrypted:</strong> " . ($row['is_encrypted'] == 1 ? "🔒 YES (1)" : "🔓 NO (0)") . "</p>";
    echo "<p><strong>grade_status:</strong> " . htmlspecialchars($row['grade_status']) . "</p>";
    echo "<p><strong>term_grade (raw):</strong> " . htmlspecialchars($row['term_grade']) . "</p>";
    echo "<p><strong>midterm_percentage:</strong> " . htmlspecialchars($row['midterm_percentage']) . "</p>";
    echo "<p><strong>finals_percentage:</strong> " . htmlspecialchars($row['finals_percentage']) . "</p>";
    echo "<p><strong>term_percentage:</strong> " . htmlspecialchars($row['term_percentage']) . "</p>";
} else {
    echo "<p style='color: red;'>❌ No grade record found for this student-class combination</p>";
}
$stmt->close();

echo "<hr>";
echo "<h2>3. Grade Visibility Status Table</h2>";

$stmt = $conn->prepare("
    SELECT 
        student_id,
        grade_visibility,
        changed_by,
        visibility_changed_at
    FROM grade_visibility_status 
    WHERE class_code = ?
    LIMIT 5
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Student ID</th><th>grade_visibility</th><th>changed_by</th><th>visibility_changed_at</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['grade_visibility']) . "</td>";
        echo "<td>" . htmlspecialchars($row['changed_by']) . "</td>";
        echo "<td>" . htmlspecialchars($row['visibility_changed_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No visibility status records found</p>";
}
$stmt->close();

echo "<hr>";
echo "<h2>4. The Problem Analysis</h2>";

// Count encrypted vs decrypted
$stmt = $conn->prepare("
    SELECT 
        is_encrypted,
        COUNT(*) as count
    FROM grade_term
    WHERE class_code = ?
    GROUP BY is_encrypted
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

$encrypted_count = 0;
$decrypted_count = 0;
while ($row = $result->fetch_assoc()) {
    if ($row['is_encrypted'] == 1) {
        $encrypted_count = $row['count'];
    } else {
        $decrypted_count = $row['count'];
    }
}
$stmt->close();

echo "<p><strong>Encrypted grades (is_encrypted = 1):</strong> $encrypted_count</p>";
echo "<p><strong>Decrypted grades (is_encrypted = 0):</strong> $decrypted_count</p>";

echo "<hr>";
echo "<h2>5. Diagnosis</h2>";

if ($encrypted_count > 0 && $decrypted_count === 0) {
    echo "<p style='color: red; font-size: 16px; font-weight: bold;'>❌ ALL GRADES ARE ENCRYPTED</p>";
    echo "<p>This explains why students see locked grades even though faculty shows 'VISIBLE TO STUDENTS'.</p>";
    echo "<p><strong>Root Cause:</strong> The 'Show Grades' button may not have been clicked, or the decryption failed.</p>";
    echo "<p><strong>Solution:</strong> Faculty needs to click 'Show Grades' button in Summary tab to decrypt all grades (set is_encrypted = 0).</p>";
} elseif ($encrypted_count === 0 && $decrypted_count > 0) {
    echo "<p style='color: green; font-size: 16px; font-weight: bold;'>✅ ALL GRADES ARE DECRYPTED</p>";
    echo "<p>Grades should be visible to students. If they're not seeing them, there may be a JavaScript issue.</p>";
} else {
    echo "<p style='color: orange; font-size: 16px; font-weight: bold;'>⚠️ MIXED STATE</p>";
    echo "<p>Some grades are encrypted and some are decrypted. This shouldn't happen.</p>";
}

echo "<hr>";
echo "<h2>6. What Should Happen</h2>";
echo "<ol>";
echo "<li>Faculty logs into their dashboard</li>";
echo "<li>Selects class: $class_code</li>";
echo "<li>Goes to SUMMARY tab</li>";
echo "<li>Looks for 'Grade Visibility Control' section</li>";
echo "<li>Clicks the 'Show Grades' button (should say 'Hide Grades' if grades are visible)</li>";
echo "<li>Confirms the action</li>";
echo "<li>All student records should have is_encrypted = 0</li>";
echo "<li>Student API should then return term_grade_hidden = false</li>";
echo "<li>Student dashboard should show actual grades instead of lock icons</li>";
echo "</ol>";

?>
<hr>
<p><small>Diagnosis completed at <?= date('Y-m-d H:i:s') ?></small></p>
