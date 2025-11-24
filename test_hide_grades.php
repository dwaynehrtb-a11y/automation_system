<?php
/**
 * Test Script: Hide Grades Functionality
 * 
 * This script tests the hide/show grades functionality for students
 * by checking:
 * 1. Grade visibility status in database
 * 2. Grade encryption status
 * 3. Student can/cannot see grades based on visibility
 */

define('SYSTEM_ACCESS', true);
require_once 'config/session.php';
require_once 'config/db.php';
require_once 'config/encryption.php';

// Start session
startSecureSession();

// Check if user is admin or faculty
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'faculty' && $_SESSION['role'] !== 'admin')) {
    die("ERROR: Only faculty and admins can run this test.");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Hide Grades Test</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #003082; }
        h2 { color: #0047ab; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #003082; color: white; }
        tr:hover { background: #f9f9f9; }
        .status { padding: 8px 12px; border-radius: 4px; font-weight: bold; }
        .hidden { background: #fef3c7; color: #92400e; }
        .visible { background: #d1fae5; color: #065f46; }
        .pending { background: #e0e7ff; color: #312e81; }
        .button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-hide { background: #fbbf24; color: #1e3a8a; }
        .btn-show { background: #10b981; color: white; }
        .info { background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #2196f3; }
        .success { background: #c8e6c9; color: #2e7d32; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .error { background: #ffcdd2; color: #c62828; padding: 10px; margin: 10px 0; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔐 Hide Grades Functionality Test</h1>
    
    <div class="info">
        <strong>Test Purpose:</strong> Verify that faculty can hide/show grades and students cannot access hidden grades
    </div>

    <?php
    // Get all classes for current faculty
    if ($_SESSION['role'] === 'faculty') {
        $faculty_id = $_SESSION['user_id'];
        $query = "SELECT DISTINCT class_code FROM class WHERE faculty_id = ? ORDER BY class_code";
    } else {
        // Admin - get all classes
        $query = "SELECT DISTINCT class_code FROM class ORDER BY class_code";
        $faculty_id = null;
    }

    $stmt = $conn->prepare($query);
    if ($faculty_id) {
        $stmt->bind_param("i", $faculty_id);
    }
    $stmt->execute();
    $classes_result = $stmt->get_result();
    $classes = [];
    while ($row = $classes_result->fetch_assoc()) {
        $classes[] = $row['class_code'];
    }
    $stmt->close();

    if (empty($classes)) {
        echo "<p class='error'>No classes found for your account.</p>";
    } else {
        foreach ($classes as $class_code) {
            echo "<h2>Class: " . htmlspecialchars($class_code) . "</h2>";
            
            // Get students enrolled in this class
            $student_query = "
                SELECT DISTINCT ce.student_id
                FROM class_enrollments ce
                WHERE ce.class_code = ? AND ce.status = 'enrolled'
            ";
            $student_stmt = $conn->prepare($student_query);
            $student_stmt->bind_param("s", $class_code);
            $student_stmt->execute();
            $student_result = $student_stmt->get_result();
            
            echo "<table>";
            echo "<tr>";
            echo "<th>Student ID</th>";
            echo "<th>Grade Entry Exists</th>";
            echo "<th>Is Encrypted</th>";
            echo "<th>Visibility Status</th>";
            echo "<th>Can See Grades</th>";
            echo "<th>Action</th>";
            echo "</tr>";
            
            $has_students = false;
            while ($student = $student_result->fetch_assoc()) {
                $has_students = true;
                $student_id = $student['student_id'];
                
                // Check if grade_term entry exists
                $grade_check = $conn->prepare("SELECT id, is_encrypted FROM grade_term WHERE student_id = ? AND class_code = ? LIMIT 1");
                $grade_check->bind_param("ss", $student_id, $class_code);
                $grade_check->execute();
                $grade_result = $grade_check->get_result();
                $grade_exists = $grade_result->num_rows > 0;
                $is_encrypted = false;
                if ($grade_exists) {
                    $grade_row = $grade_result->fetch_assoc();
                    $is_encrypted = intval($grade_row['is_encrypted']) === 1;
                }
                $grade_check->close();
                
                // Check visibility status
                $vis_check = $conn->prepare("SELECT grade_visibility FROM grade_visibility_status WHERE student_id = ? AND class_code = ? LIMIT 1");
                $vis_check->bind_param("ss", $student_id, $class_code);
                $vis_check->execute();
                $vis_result = $vis_check->get_result();
                $visibility_status = 'not_set';
                if ($vis_result->num_rows > 0) {
                    $vis_row = $vis_result->fetch_assoc();
                    $visibility_status = $vis_row['grade_visibility'];
                }
                $vis_check->close();
                
                // Determine if student can see grades
                $can_see = !$is_encrypted && $visibility_status !== 'hidden';
                
                echo "<tr>";
                echo "<td>" . htmlspecialchars($student_id) . "</td>";
                echo "<td>" . ($grade_exists ? "✓ Yes" : "✗ No") . "</td>";
                echo "<td>";
                if ($grade_exists) {
                    echo "<span class='status " . ($is_encrypted ? "hidden" : "visible") . "'>";
                    echo $is_encrypted ? "Encrypted" : "Decrypted";
                    echo "</span>";
                } else {
                    echo "N/A";
                }
                echo "</td>";
                echo "<td>";
                if ($visibility_status === 'hidden') {
                    echo "<span class='status hidden'>Hidden</span>";
                } elseif ($visibility_status === 'visible') {
                    echo "<span class='status visible'>Visible</span>";
                } else {
                    echo "<span class='status pending'>Not Set</span>";
                }
                echo "</td>";
                echo "<td>";
                echo "<span class='status " . ($can_see ? "visible" : "hidden") . "'>";
                echo $can_see ? "✓ Yes" : "✗ No";
                echo "</span>";
                echo "</td>";
                echo "<td>";
                if ($grade_exists) {
                    echo $is_encrypted 
                        ? "<button class='button btn-show' onclick=\"toggleGrades('" . htmlspecialchars($class_code) . "', 'show')\">Show All</button>" 
                        : "<button class='button btn-hide' onclick=\"toggleGrades('" . htmlspecialchars($class_code) . "', 'hide')\">Hide All</button>";
                }
                echo "</td>";
                echo "</tr>";
            }
            
            if (!$has_students) {
                echo "<tr><td colspan='6'>No enrolled students</td></tr>";
            }
            
            echo "</table>";
            $student_stmt->close();
        }
    }
    ?>

    <h2>📊 System-Wide Summary</h2>
    <table>
        <tr>
            <th>Class Code</th>
            <th>Total Students</th>
            <th>Grades Hidden</th>
            <th>Grades Visible</th>
            <th>Grades Not Set</th>
        </tr>
        <?php
        foreach ($classes as $class_code) {
            $student_query = "
                SELECT DISTINCT ce.student_id
                FROM class_enrollments ce
                WHERE ce.class_code = ? AND ce.status = 'enrolled'
            ";
            $student_stmt = $conn->prepare($student_query);
            $student_stmt->bind_param("s", $class_code);
            $student_stmt->execute();
            $student_result = $student_stmt->get_result();
            $total_students = $student_result->num_rows;
            
            $hidden_count = 0;
            $visible_count = 0;
            $not_set_count = 0;
            
            while ($student = $student_result->fetch_assoc()) {
                $student_id = $student['student_id'];
                $vis_check = $conn->prepare("SELECT grade_visibility FROM grade_visibility_status WHERE student_id = ? AND class_code = ? LIMIT 1");
                $vis_check->bind_param("ss", $student_id, $class_code);
                $vis_check->execute();
                $vis_result = $vis_check->get_result();
                
                if ($vis_result->num_rows > 0) {
                    $vis_row = $vis_result->fetch_assoc();
                    if ($vis_row['grade_visibility'] === 'hidden') {
                        $hidden_count++;
                    } else {
                        $visible_count++;
                    }
                } else {
                    $not_set_count++;
                }
                $vis_check->close();
            }
            $student_stmt->close();
            
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($class_code) . "</strong></td>";
            echo "<td>" . $total_students . "</td>";
            echo "<td><span class='status hidden'>" . $hidden_count . "</span></td>";
            echo "<td><span class='status visible'>" . $visible_count . "</span></td>";
            echo "<td><span class='status pending'>" . $not_set_count . "</span></td>";
            echo "</tr>";
        }
        ?>
    </table>

    <script>
    function toggleGrades(classCode, action) {
        if (!confirm(`Are you sure you want to ${action} grades for class ${classCode}?`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', action === 'hide' ? 'encrypt_all' : 'decrypt_all');
        formData.append('class_code', classCode);
        formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
        
        fetch('faculty/ajax/encrypt_decrypt_grades.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`✓ ${action === 'hide' ? 'Grades hidden' : 'Grades shown'} successfully!\n${data.message}`);
                location.reload();
            } else {
                alert(`✗ Error: ${data.error || data.message}`);
            }
        })
        .catch(error => {
            alert(`✗ Request failed: ${error.message}`);
        });
    }
    </script>

</div>
</body>
</html>
