<?php
/**
 * Manual Grade Decryption Tool
 * This script allows manual decryption of all grades for a specific class
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
$action = $_GET['action'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Grade Encryption Manual Control</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #003082; }
        .info { background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #2196f3; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #ffc107; }
        .success { background: #d4edda; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #dc3545; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #003082; color: white; }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-primary { background: #003082; color: white; }
        .code { font-family: monospace; background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔐 Grade Encryption Manual Control</h1>
    
    <div class="info">
        <strong>Purpose:</strong> Check and manually fix grade encryption status for a class
    </div>

    <?php
    
    if ($action === 'decrypt' && $class_code) {
        // Manual decryption
        echo "<h2>Decrypting Grades for Class: " . htmlspecialchars($class_code) . "</h2>";
        
        Encryption::init();
        
        $select = $conn->prepare("SELECT id, term_grade, midterm_percentage, finals_percentage, term_percentage FROM grade_term WHERE class_code = ? AND is_encrypted = 1");
        $select->bind_param('s', $class_code);
        $select->execute();
        $res = $select->get_result();
        $count = 0;
        $errors = [];
        
        echo "<p>Found " . $res->num_rows . " encrypted records...</p>";
        
        while ($row = $res->fetch_assoc()) {
            try {
                $dec = [];
                $fields = ['term_grade', 'midterm_percentage', 'finals_percentage', 'term_percentage'];
                
                foreach ($fields as $f) {
                    $val = $row[$f];
                    if ($val !== null && $val !== '') {
                        try {
                            $dec[$f] = Encryption::decrypt($val);
                        } catch (Exception $de) {
                            $dec[$f] = $val;
                        }
                    } else {
                        $dec[$f] = $val;
                    }
                }
                
                $upd = $conn->prepare("UPDATE grade_term SET term_grade=?, midterm_percentage=?, finals_percentage=?, term_percentage=?, is_encrypted=0 WHERE id=?");
                $upd->bind_param('ssssi', $dec['term_grade'], $dec['midterm_percentage'], $dec['finals_percentage'], $dec['term_percentage'], $row['id']);
                
                if ($upd->execute()) {
                    $count++;
                    echo "<div class='success'>✓ Decrypted record ID " . $row['id'] . "</div>";
                } else {
                    $errors[] = "Failed to update ID " . $row['id'];
                    echo "<div class='error'>✗ Failed to update ID " . $row['id'] . ": " . $upd->error . "</div>";
                }
                $upd->close();
                
            } catch (Exception $e) {
                $errors[] = "ID " . $row['id'] . ": " . $e->getMessage();
                echo "<div class='error'>✗ Error decrypting ID " . $row['id'] . ": " . $e->getMessage() . "</div>";
            }
        }
        $select->close();
        
        echo "<div class='success'><strong>✓ Decryption Complete</strong><br>";
        echo "Successfully decrypted: $count records<br>";
        if (count($errors) > 0) {
            echo "Errors: " . count($errors) . "<br>";
            echo "<ul>";
            foreach ($errors as $err) {
                echo "<li>" . htmlspecialchars($err) . "</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
        
    } elseif ($class_code) {
        // Show status for class
        echo "<h2>Status for Class: " . htmlspecialchars($class_code) . "</h2>";
        
        $encrypted_query = "SELECT COUNT(*) as count FROM grade_term WHERE class_code = ? AND is_encrypted = 1";
        $stmt = $conn->prepare($encrypted_query);
        $stmt->bind_param('s', $class_code);
        $stmt->execute();
        $encrypted_count = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $decrypted_query = "SELECT COUNT(*) as count FROM grade_term WHERE class_code = ? AND (is_encrypted = 0 OR is_encrypted IS NULL)";
        $stmt = $conn->prepare($decrypted_query);
        $stmt->bind_param('s', $class_code);
        $stmt->execute();
        $decrypted_count = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        echo "<table>";
        echo "<tr><th>Status</th><th>Count</th><th>Action</th></tr>";
        echo "<tr>";
        echo "<td>Encrypted (Hidden)</td>";
        echo "<td><strong>" . $encrypted_count . "</strong></td>";
        echo "<td>";
        if ($encrypted_count > 0) {
            echo "<a href='?class_code=" . urlencode($class_code) . "&action=decrypt' onclick='return confirm(\"Decrypt all " . $encrypted_count . " grades?\");'>";
            echo "<button class='btn btn-success'>Decrypt All</button>";
            echo "</a>";
        } else {
            echo "N/A";
        }
        echo "</td>";
        echo "</tr>";
        echo "<tr>";
        echo "<td>Decrypted (Visible)</td>";
        echo "<td><strong>" . $decrypted_count . "</strong></td>";
        echo "<td>-</td>";
        echo "</tr>";
        echo "</table>";
        
        if ($encrypted_count > 0) {
            echo "<div class='warning'><strong>⚠️ NOTICE:</strong> There are " . $encrypted_count . " encrypted (hidden) grades for this class.</div>";
            echo "<p>Students will see locked grades until you click <strong>Decrypt All</strong> above.</p>";
        } else {
            echo "<div class='success'><strong>✓ ALL GRADES ARE VISIBLE</strong><br>All " . $decrypted_count . " grades are decrypted and visible to students.</div>";
        }
        
    } else {
        // Show list of classes
        echo "<h2>Select a Class to Check</h2>";
        
        $query = "SELECT DISTINCT c.class_code, c.course_code, s.course_title, COUNT(DISTINCT ce.student_id) as student_count FROM class c LEFT JOIN class_enrollments ce ON c.class_code = ce.class_code LEFT JOIN subjects s ON c.course_code = s.course_code GROUP BY c.class_code ORDER BY c.class_code";
        $result = $conn->query($query);
        
        echo "<table>";
        echo "<tr><th>Class Code</th><th>Course Title</th><th>Students</th><th>Action</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td><span class='code'>" . htmlspecialchars($row['class_code']) . "</span></td>";
            echo "<td>" . htmlspecialchars($row['course_title'] ?? 'N/A') . "</td>";
            echo "<td>" . $row['student_count'] . "</td>";
            echo "<td><a href='?class_code=" . urlencode($row['class_code']) . "'><button class='btn btn-primary'>Check Status</button></a></td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    ?>

</div>
</body>
</html>
