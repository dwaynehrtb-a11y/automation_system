<?php
/**
 * Comprehensive Grade Visibility Debug & Fix Tool
 * Tests the entire flow and can manually fix issues
 */
define('SYSTEM_ACCESS', true);
require_once 'config/session.php';
require_once 'config/db.php';
require_once 'config/encryption.php';

header('Content-Type: text/html; charset=utf-8');

$test_class = $_GET['test_class'] ?? 'CCPRGG1L';
$action = $_GET['action'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Grade Visibility - Complete Debug Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #003082; margin-bottom: 30px; font-size: 2.5em; text-align: center; }
        .section { background: white; padding: 25px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .section h2 { color: #003082; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #003082; }
        .section h3 { color: #005eb8; margin-top: 20px; margin-bottom: 10px; font-size: 1.1em; }
        .status-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .status-table th { background: #003082; color: white; padding: 12px; text-align: left; font-weight: 600; }
        .status-table td { padding: 12px; border-bottom: 1px solid #ddd; }
        .status-table tr:hover { background: #f9f9f9; }
        .status-encrypted { background: #ffcccc; color: #cc0000; font-weight: bold; text-align: center; }
        .status-visible { background: #ccffcc; color: #00cc00; font-weight: bold; text-align: center; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; margin: 5px; }
        .btn-primary { background: #003082; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; }
        .info { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0; border-radius: 4px; color: #155724; }
        .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0; border-radius: 4px; color: #721c24; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0; border-radius: 4px; color: #856404; }
        .code { font-family: 'Courier New', monospace; background: #f4f4f4; padding: 10px; border-radius: 4px; display: inline-block; margin: 5px 0; overflow-x: auto; }
        .row { display: flex; gap: 20px; margin: 15px 0; }
        .col { flex: 1; }
        .student-list { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px; }
        .student-item { padding: 8px; border-bottom: 1px solid #eee; cursor: pointer; }
        .student-item:hover { background: #f5f5f5; }
        .test-result { padding: 10px; margin: 5px 0; border-radius: 4px; font-family: monospace; font-size: 0.9em; }
        .test-pass { background: #d4edda; color: #155724; }
        .test-fail { background: #f8d7da; color: #721c24; }
        select { padding: 10px; font-size: 1em; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Grade Visibility Debug & Fix Tool</h1>

    <!-- Class Selection -->
    <div class="section">
        <h2>Step 1: Select Class</h2>
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="hidden" name="action" value="view">
            <select name="test_class" onchange="this.form.submit()">
                <option value="">-- Select a Class --</option>
                <?php
                $result = $conn->query("SELECT DISTINCT class_code FROM grade_term ORDER BY class_code");
                while ($row = $result->fetch_assoc()) {
                    $selected = ($row['class_code'] === $test_class) ? 'selected' : '';
                    echo "<option value='" . htmlspecialchars($row['class_code']) . "' $selected>" . htmlspecialchars($row['class_code']) . "</option>";
                }
                ?>
            </select>
            <button type="submit" class="btn btn-primary">View</button>
        </form>
    </div>

    <?php
    if (!$test_class) {
        exit;
    }

    // Get class info
    $class_info = $conn->prepare("SELECT * FROM class WHERE class_code = ? LIMIT 1");
    $class_info->bind_param('s', $test_class);
    $class_info->execute();
    $class_row = $class_info->get_result()->fetch_assoc();
    $class_info->close();

    echo "<div class='section'>";
    echo "<h2>Class Information: " . htmlspecialchars($test_class) . "</h2>";
    
    if ($class_row) {
        echo "<table class='status-table'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>Course Code</td><td>" . htmlspecialchars($class_row['course_code'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td>Faculty ID</td><td>" . htmlspecialchars($class_row['faculty_id'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td>Academic Year</td><td>" . htmlspecialchars($class_row['academic_year'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td>Term</td><td>" . htmlspecialchars($class_row['term'] ?? 'N/A') . "</td></tr>";
        echo "</table>";
    }
    echo "</div>";

    // ENCRYPTION STATUS
    echo "<div class='section'>";
    echo "<h2>Current Encryption Status</h2>";

    $encrypted = $conn->prepare("SELECT COUNT(*) as count FROM grade_term WHERE class_code = ? AND is_encrypted = 1");
    $encrypted->bind_param('s', $test_class);
    $encrypted->execute();
    $enc_count = $encrypted->get_result()->fetch_assoc()['count'];
    $encrypted->close();

    $decrypted = $conn->prepare("SELECT COUNT(*) as count FROM grade_term WHERE class_code = ? AND (is_encrypted = 0 OR is_encrypted IS NULL)");
    $decrypted->bind_param('s', $test_class);
    $decrypted->execute();
    $dec_count = $decrypted->get_result()->fetch_assoc()['count'];
    $decrypted->close();

    $total = $enc_count + $dec_count;
    $pct_encrypted = $total > 0 ? round(($enc_count / $total) * 100) : 0;

    echo "<table class='status-table'>";
    echo "<tr>";
    echo "<th>Status</th>";
    echo "<th>Count</th>";
    echo "<th>Percentage</th>";
    echo "<th>Status Indicator</th>";
    echo "</tr>";

    echo "<tr>";
    echo "<td>🔒 Encrypted (Hidden)</td>";
    echo "<td><strong>" . $enc_count . "</strong></td>";
    echo "<td>" . $pct_encrypted . "%</td>";
    echo "<td class='" . ($enc_count > 0 ? 'status-encrypted' : '') . "'>" . ($enc_count > 0 ? 'HIDDEN' : 'NONE') . "</td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td>🔓 Decrypted (Visible)</td>";
    echo "<td><strong>" . $dec_count . "</strong></td>";
    echo "<td>" . (100 - $pct_encrypted) . "%</td>";
    echo "<td class='" . ($dec_count > 0 ? 'status-visible' : '') . "'>" . ($dec_count > 0 ? 'VISIBLE' : 'NONE') . "</td>";
    echo "</tr>";

    echo "</table>";

    if ($enc_count > 0 && $dec_count > 0) {
        echo "<div class='warning'>⚠️ <strong>MIXED STATE:</strong> Some grades are encrypted and some are decrypted. This is unusual.</div>";
    } elseif ($enc_count > 0) {
        echo "<div class='warning'>⚠️ <strong>ALL GRADES ARE HIDDEN.</strong> Students will see lock icons. Click 'Show Grades' button below.</div>";
    } else {
        echo "<div class='success'>✓ <strong>ALL GRADES ARE VISIBLE.</strong> Students should see their grades normally.</div>";
    }

    // ACTION BUTTONS
    echo "<div style='margin-top: 20px;'>";
    if ($enc_count > 0) {
        echo "<form method='GET' style='display: inline;'>";
        echo "<input type='hidden' name='test_class' value='" . htmlspecialchars($test_class) . "'>";
        echo "<input type='hidden' name='action' value='decrypt'>";
        echo "<button type='submit' class='btn btn-success' onclick='return confirm(\"Decrypt all " . $enc_count . " grades?\");'>🔓 SHOW GRADES (Decrypt All)</button>";
        echo "</form>";
    }
    if ($dec_count > 0) {
        echo "<form method='GET' style='display: inline;'>";
        echo "<input type='hidden' name='test_class' value='" . htmlspecialchars($test_class) . "'>";
        echo "<input type='hidden' name='action' value='encrypt'>";
        echo "<button type='submit' class='btn btn-danger' onclick='return confirm(\"Encrypt all " . $dec_count . " grades?\");'>🔒 HIDE GRADES (Encrypt All)</button>";
        echo "</form>";
    }
    echo "</div>";

    echo "</div>";

    // If action is decrypt or encrypt, perform it
    if ($action === 'decrypt' || $action === 'encrypt') {
        echo "<div class='section'>";
        echo "<h2>Processing: " . ($action === 'decrypt' ? 'Decrypt' : 'Encrypt') . " Grades</h2>";

        Encryption::init();

        if ($action === 'decrypt') {
            echo "<p>Decrypting all " . $enc_count . " grades for class " . htmlspecialchars($test_class) . "...</p>";

            $select = $conn->prepare("SELECT id, term_grade, midterm_percentage, finals_percentage, term_percentage FROM grade_term WHERE class_code = ? AND is_encrypted = 1");
            $select->bind_param('s', $test_class);
            $select->execute();
            $res = $select->get_result();

            $success = 0;
            $failed = 0;

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
                        $success++;
                        echo "<div class='test-result test-pass'>✓ ID " . $row['id'] . " decrypted successfully</div>";
                    } else {
                        $failed++;
                        echo "<div class='test-result test-fail'>✗ ID " . $row['id'] . " failed: " . $upd->error . "</div>";
                    }
                    $upd->close();
                } catch (Exception $e) {
                    $failed++;
                    echo "<div class='test-result test-fail'>✗ ID " . $row['id'] . " error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            $select->close();

            echo "<div class='success'><strong>Decryption Complete</strong><br>Success: " . $success . " | Failed: " . $failed . "</div>";

            // Update visibility status
            $students = $conn->prepare("SELECT DISTINCT student_id FROM class_enrollments WHERE class_code = ?");
            $students->bind_param('s', $test_class);
            $students->execute();
            $sr = $students->get_result();

            $updated_vis = 0;
            while ($s = $sr->fetch_assoc()) {
                $vis = $conn->prepare("INSERT INTO grade_visibility_status (student_id, class_code, grade_visibility, changed_by) VALUES (?, ?, 'visible', 1) ON DUPLICATE KEY UPDATE grade_visibility='visible', changed_by=1, visibility_changed_at=NOW()");
                $vis->bind_param('ss', $s['student_id'], $test_class);
                if ($vis->execute()) {
                    $updated_vis++;
                }
                $vis->close();
            }
            $students->close();

            echo "<div class='info'>Updated " . $updated_vis . " visibility status records</div>";

        } elseif ($action === 'encrypt') {
            echo "<p>Encrypting all " . $dec_count . " grades for class " . htmlspecialchars($test_class) . "...</p>";

            $select = $conn->prepare("SELECT id, term_grade, midterm_percentage, finals_percentage, term_percentage FROM grade_term WHERE class_code = ? AND (is_encrypted = 0 OR is_encrypted IS NULL)");
            $select->bind_param('s', $test_class);
            $select->execute();
            $res = $select->get_result();

            $success = 0;
            $failed = 0;

            while ($row = $res->fetch_assoc()) {
                try {
                    $enc = [];
                    $fields = ['term_grade', 'midterm_percentage', 'finals_percentage', 'term_percentage'];

                    foreach ($fields as $f) {
                        $val = $row[$f];
                        if ($val !== null && $val !== '' && is_numeric(trim($val))) {
                            $enc[$f] = Encryption::encrypt($val);
                        } else {
                            $enc[$f] = $val;
                        }
                    }

                    $upd = $conn->prepare("UPDATE grade_term SET term_grade=?, midterm_percentage=?, finals_percentage=?, term_percentage=?, is_encrypted=1 WHERE id=?");
                    $upd->bind_param('ssssi', $enc['term_grade'], $enc['midterm_percentage'], $enc['finals_percentage'], $enc['term_percentage'], $row['id']);

                    if ($upd->execute()) {
                        $success++;
                        echo "<div class='test-result test-pass'>✓ ID " . $row['id'] . " encrypted successfully</div>";
                    } else {
                        $failed++;
                        echo "<div class='test-result test-fail'>✗ ID " . $row['id'] . " failed: " . $upd->error . "</div>";
                    }
                    $upd->close();
                } catch (Exception $e) {
                    $failed++;
                    echo "<div class='test-result test-fail'>✗ ID " . $row['id'] . " error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            $select->close();

            echo "<div class='success'><strong>Encryption Complete</strong><br>Success: " . $success . " | Failed: " . $failed . "</div>";

            // Update visibility status
            $students = $conn->prepare("SELECT DISTINCT student_id FROM class_enrollments WHERE class_code = ?");
            $students->bind_param('s', $test_class);
            $students->execute();
            $sr = $students->get_result();

            $updated_vis = 0;
            while ($s = $sr->fetch_assoc()) {
                $vis = $conn->prepare("INSERT INTO grade_visibility_status (student_id, class_code, grade_visibility, changed_by) VALUES (?, ?, 'hidden', 1) ON DUPLICATE KEY UPDATE grade_visibility='hidden', changed_by=1, visibility_changed_at=NOW()");
                $vis->bind_param('ss', $s['student_id'], $test_class);
                if ($vis->execute()) {
                    $updated_vis++;
                }
                $vis->close();
            }
            $students->close();

            echo "<div class='info'>Updated " . $updated_vis . " visibility status records</div>";
        }

        echo "</div>";

        // Redirect back to view status
        echo "<script>setTimeout(() => { window.location.href = '?test_class=" . urlencode($test_class) . "'; }, 3000);</script>";
    }

    ?>

</div>
</body>
</html>
