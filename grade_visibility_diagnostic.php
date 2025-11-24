<?php
/**
 * Comprehensive Grade Visibility Diagnostic Tool
 * This script helps identify exactly why grades appear locked to students
 */
define('SYSTEM_ACCESS', true);
require_once 'config/session.php';
require_once 'config/db.php';
require_once 'config/encryption.php';

startSecureSession();

// Check if user is faculty/admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'admin'])) {
    die("ERROR: Only faculty and admins can access this tool.");
}

header('Content-Type: text/html; charset=utf-8');

$class_code = $_GET['class_code'] ?? '';
$student_id = $_GET['student_id'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Grade Visibility Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1, h2, h3 { color: #003082; }
        .section { margin: 30px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #003082; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 12px; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #003082; color: white; }
        tr:nth-child(even) { background: #f5f5f5; }
        .status-encrypted { background: #ffcccc; color: #cc0000; font-weight: bold; }
        .status-visible { background: #ccffcc; color: #00cc00; font-weight: bold; }
        .status-unknown { background: #ffffe0; color: #666; }
        .code { font-family: monospace; background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
        .info-box { background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #2196f3; }
        .warning-box { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107; }
        .error-box { background: #f8d7da; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .success-box { background: #d4edda; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
        input, select { padding: 8px; margin: 5px; font-size: 14px; }
        button { padding: 10px 20px; background: #003082; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #002050; }
        .row { display: flex; gap: 20px; }
        .col { flex: 1; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Grade Visibility Diagnostic Tool</h1>
    
    <div class="info-box">
        This tool helps identify why grades appear locked to students even when faculty marks them as visible.
    </div>

    <div class="section">
        <h2>Step 1: Select Class and Student</h2>
        <div class="row">
            <div class="col">
                <label><strong>Class Code:</strong></label><br>
                <select id="class_select" onchange="loadStudents()">
                    <option value="">-- Select a Class --</option>
                    <?php
                    $result = $conn->query("SELECT DISTINCT class_code FROM grade_term ORDER BY class_code");
                    while ($row = $result->fetch_assoc()) {
                        $selected = ($row['class_code'] === $class_code) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($row['class_code']) . "' $selected>" . htmlspecialchars($row['class_code']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col">
                <label><strong>Student ID:</strong></label><br>
                <select id="student_select">
                    <option value="">-- Select a Student --</option>
                </select>
            </div>
            <div class="col" style="padding-top: 22px;">
                <button onclick="runDiagnostic()">Run Diagnostic</button>
            </div>
        </div>
    </div>

    <?php
    
    if ($class_code && $student_id) {
        echo "<div class='section'>";
        echo "<h2>Diagnostic Results for " . htmlspecialchars($class_code) . " - Student " . htmlspecialchars($student_id) . "</h2>";
        
        // 1. Check grade_term table
        echo "<h3>1. Grade Term Table Status</h3>";
        $stmt = $conn->prepare("SELECT id, student_id, class_code, term_grade, midterm_percentage, finals_percentage, term_percentage, is_encrypted, grade_status FROM grade_term WHERE student_id = ? AND class_code = ?");
        $stmt->bind_param('ss', $student_id, $class_code);
        $stmt->execute();
        $gt_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$gt_row) {
            echo "<div class='error-box'>❌ No grade record found for this student in this class!</div>";
        } else {
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
            
            $is_encrypted = intval($gt_row['is_encrypted']) === 1;
            echo "<tr>";
            echo "<td>is_encrypted</td>";
            echo "<td><strong>" . ($is_encrypted ? '1 (TRUE)' : '0 (FALSE)') . "</strong></td>";
            echo "<td>";
            if ($is_encrypted) {
                echo "<span class='status-encrypted'>🔒 ENCRYPTED (HIDDEN)</span>";
            } else {
                echo "<span class='status-visible'>🔓 DECRYPTED (VISIBLE)</span>";
            }
            echo "</td>";
            echo "</tr>";
            
            echo "<tr><td>term_grade</td><td>" . htmlspecialchars($gt_row['term_grade'] ?? 'NULL') . "</td><td>";
            if ($gt_row['term_grade'] && $is_encrypted) echo "⚠️ Encrypted value";
            else if ($gt_row['term_grade']) echo "✓ Raw value";
            else echo "Empty";
            echo "</td></tr>";
            
            echo "<tr><td>midterm_percentage</td><td>" . htmlspecialchars($gt_row['midterm_percentage'] ?? 'NULL') . "</td><td>";
            if ($gt_row['midterm_percentage'] && $is_encrypted) echo "⚠️ Encrypted";
            else if ($gt_row['midterm_percentage']) echo "✓ Readable";
            else echo "Empty";
            echo "</td></tr>";
            
            echo "<tr><td>finals_percentage</td><td>" . htmlspecialchars($gt_row['finals_percentage'] ?? 'NULL') . "</td><td>";
            if ($gt_row['finals_percentage'] && $is_encrypted) echo "⚠️ Encrypted";
            else if ($gt_row['finals_percentage']) echo "✓ Readable";
            else echo "Empty";
            echo "</td></tr>";
            
            echo "<tr><td>term_percentage</td><td>" . htmlspecialchars($gt_row['term_percentage'] ?? 'NULL') . "</td><td>";
            if ($gt_row['term_percentage'] && $is_encrypted) echo "⚠️ Encrypted";
            else if ($gt_row['term_percentage']) echo "✓ Readable";
            else echo "Empty";
            echo "</td></tr>";
            
            echo "<tr><td>grade_status</td><td>" . htmlspecialchars($gt_row['grade_status'] ?? 'NULL') . "</td><td></td></tr>";
            
            echo "</table>";
            
            if ($is_encrypted) {
                echo "<div class='error-box'><strong>⚠️ PROBLEM FOUND:</strong> The <code>is_encrypted</code> flag is set to 1, which means the student will see locked grades.</div>";
            } else {
                echo "<div class='success-box'><strong>✓ Good:</strong> Grades are marked as decrypted (is_encrypted = 0). If student still sees locked grades, the problem is in the frontend or browser cache.</div>";
            }
        }
        
        // 2. Check visibility status table
        echo "<h3>2. Grade Visibility Status Table</h3>";
        $stmt = $conn->prepare("SELECT * FROM grade_visibility_status WHERE student_id = ? AND class_code = ?");
        $stmt->bind_param('ss', $student_id, $class_code);
        $stmt->execute();
        $vis_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$vis_row) {
            echo "<div class='warning-box'>⚠️ No visibility status record found (this is OK, we use is_encrypted now)</div>";
        } else {
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>grade_visibility</td><td><strong>" . htmlspecialchars($vis_row['grade_visibility']) . "</strong></td></tr>";
            echo "<tr><td>changed_by</td><td>" . htmlspecialchars($vis_row['changed_by'] ?? 'NULL') . "</td></tr>";
            echo "<tr><td>visibility_changed_at</td><td>" . htmlspecialchars($vis_row['visibility_changed_at'] ?? 'NULL') . "</td></tr>";
            echo "</table>";
        }
        
        // 3. Try to decrypt sample value
        if ($is_encrypted && $gt_row['term_grade']) {
            echo "<h3>3. Decryption Test</h3>";
            try {
                Encryption::init();
                $decrypted = Encryption::decrypt($gt_row['term_grade']);
                echo "<div class='success-box'><strong>✓ Decryption Works:</strong> Sample encrypted value '<code>" . substr(htmlspecialchars($gt_row['term_grade']), 0, 30) . "...</code>' decrypts to '<code>" . htmlspecialchars($decrypted) . "</code>'</div>";
            } catch (Exception $e) {
                echo "<div class='error-box'><strong>❌ Decryption Failed:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        
        // 4. Summary and Recommendation
        echo "<h3>4. Diagnosis Summary</h3>";
        
        if ($is_encrypted) {
            echo "<div class='error-box'>";
            echo "<strong>🔴 ROOT CAUSE IDENTIFIED:</strong><br><br>";
            echo "The grade is marked as encrypted in the database. This means:<br>";
            echo "<ul>";
            echo "<li>Faculty has NOT clicked 'Show Grades' for this student</li>";
            echo "<li>OR faculty clicked 'Show Grades' but the database update did not persist</li>";
            echo "<li>The student API (get_grades.php) correctly checks is_encrypted and returns term_grade_hidden=true</li>";
            echo "<li>The student dashboard correctly shows lock icons</li>";
            echo "</ul>";
            echo "<strong>ACTION REQUIRED:</strong><br>";
            echo "<ol>";
            echo "<li>Have faculty verify they clicked 'Show Grades' and saw success message</li>";
            echo "<li>Check PHP error logs (check logs in " . htmlspecialchars(ini_get('error_log') ?? 'php://stderr') . ") for DECRYPT_ALL messages</li>";
            echo "<li>If DECRYPT_ALL messages appear but grades still encrypted, check database transaction or encryption key</li>";
            echo "<li>If DECRYPT_ALL messages do NOT appear, the 'Show Grades' button click may not be reaching the backend</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<div class='success-box'>";
            echo "<strong>🟢 GRADES ARE DECRYPTED:</strong><br><br>";
            echo "The database shows grades are decrypted (is_encrypted = 0), so:<br>";
            echo "<ul>";
            echo "<li>The backend API will return term_grade_hidden = false</li>";
            echo "<li>The student dashboard SHOULD display grades normally</li>";
            echo "<li>If student still sees locked grades:</li>";
            echo "<ul style='margin-left: 20px;'>";
            echo "<li>Check browser console (F12) for errors</li>";
            echo "<li>Clear browser cache and refresh</li>";
            echo "<li>Check if browser console shows 'Grade data received:' with term_grade_hidden = false</li>";
            echo "<li>Check if student_dashboard.js is actually using the API data</li>";
            echo "</ul>";
            echo "</ul>";
            echo "</div>";
        }
        
        echo "</div>";
    }
    
    ?>

</div>

<script>
function loadStudents() {
    const classCode = document.getElementById('class_select').value;
    const studentSelect = document.getElementById('student_select');
    
    if (!classCode) {
        studentSelect.innerHTML = '<option value="">-- Select a Student --</option>';
        return;
    }
    
    // Make AJAX call to get students
    fetch('admin_ajax/get_class_students.php?class_code=' + encodeURIComponent(classCode))
        .then(r => r.json())
        .then(data => {
            let html = '<option value="">-- Select a Student --</option>';
            if (data.success && data.students) {
                data.students.forEach(s => {
                    html += '<option value="' + s.student_id + '">' + s.student_id + ' (' + (s.last_name || '') + ', ' + (s.first_name || '') + ')</option>';
                });
            }
            studentSelect.innerHTML = html;
        })
        .catch(e => alert('Error loading students: ' + e));
}

function runDiagnostic() {
    const classCode = document.getElementById('class_select').value;
    const studentId = document.getElementById('student_select').value;
    
    if (!classCode || !studentId) {
        alert('Please select both class and student');
        return;
    }
    
    window.location.href = '?class_code=' + encodeURIComponent(classCode) + '&student_id=' + encodeURIComponent(studentId);
}

// Load students on page load if class is selected
window.addEventListener('DOMContentLoaded', () => {
    const classCode = document.getElementById('class_select').value;
    if (classCode) loadStudents();
});
</script>

</body>
</html>
