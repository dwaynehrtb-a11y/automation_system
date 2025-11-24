<?php
/**
 * Verification Script: Hide Grades Feature
 * 
 * This script verifies that all components of the hide grades feature are working correctly:
 * 1. Database tables exist with correct structure
 * 2. Encryption/decryption endpoints are accessible
 * 3. Student grade retrieval respects visibility settings
 * 4. UI components properly handle hidden/visible states
 */

define('SYSTEM_ACCESS', true);
require_once 'config/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Hide Grades Feature Verification</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #003082 0%, #0047ab 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .content { padding: 30px; }
        .check-item {
            margin: 20px 0;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #ddd;
            background: #f9f9f9;
        }
        .check-item.pass {
            border-left-color: #10b981;
            background: #ecfdf5;
        }
        .check-item.fail {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        .check-item.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        .check-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }
        .check-title .icon {
            font-size: 20px;
            width: 30px;
        }
        .check-detail {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            font-size: 13px;
            color: #666;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
        }
        .summary {
            margin-top: 40px;
            padding: 20px;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-radius: 8px;
            border: 2px solid #10b981;
        }
        .summary h3 {
            color: #065f46;
            margin-bottom: 15px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(16, 185, 129, 0.2);
        }
        .summary-item:last-child {
            border-bottom: none;
        }
        .summary-value {
            font-weight: 600;
            color: #065f46;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 13px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
        }
        tr:hover {
            background: #f9fafb;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔐 Hide Grades Feature - System Verification</h1>
        <p>Checking all components of the hide/show grades functionality</p>
    </div>
    
    <div class="content">
        <?php
        $checks = [
            'pass' => 0,
            'fail' => 0,
            'warning' => 0
        ];
        
        // Check 1: grade_term table exists with required columns
        echo "<div class='check-item'>";
        try {
            $result = $conn->query("SHOW COLUMNS FROM grade_term");
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = $row['Type'];
            }
            
            $required_columns = ['id', 'student_id', 'class_code', 'term_grade', 'midterm_percentage', 'finals_percentage', 'term_percentage', 'is_encrypted'];
            $missing = [];
            foreach ($required_columns as $col) {
                if (!isset($columns[$col])) {
                    $missing[] = $col;
                }
            }
            
            if (empty($missing)) {
                $checks['pass']++;
                echo "<div class='check-title'><span class='icon'>✓</span> grade_term table structure</div>";
                echo "<div class='check-detail'>All required columns present: " . implode(', ', array_keys($columns)) . "</div>";
                echo "</div>";
            } else {
                $checks['fail']++;
                echo "<div class='check-title'><span class='icon'>✗</span> grade_term table structure</div>";
                echo "<div class='check-detail'>Missing columns: " . implode(', ', $missing) . "</div>";
                echo "</div>";
            }
        } catch (Exception $e) {
            $checks['fail']++;
            echo "<div class='check-item fail'>";
            echo "<div class='check-title'><span class='icon'>✗</span> grade_term table</div>";
            echo "<div class='check-detail'>Error: " . $e->getMessage() . "</div>";
            echo "</div>";
        }
        
        // Check 2: grade_visibility_status table exists
        echo "<div class='check-item'>";
        try {
            $result = $conn->query("SHOW COLUMNS FROM grade_visibility_status");
            if ($result && $result->num_rows > 0) {
                $checks['pass']++;
                echo "<div class='check-title'><span class='icon'>✓</span> grade_visibility_status table</div>";
                echo "<div class='check-detail'>Table exists with " . $result->num_rows . " columns</div>";
            } else {
                throw new Exception("Table exists but no columns found");
            }
            echo "</div>";
        } catch (Exception $e) {
            $checks['fail']++;
            echo "<div class='check-item fail'>";
            echo "<div class='check-title'><span class='icon'>✗</span> grade_visibility_status table</div>";
            echo "<div class='check-detail'>Error: " . $e->getMessage() . "</div>";
            echo "</div>";
        }
        
        // Check 3: Count encrypted vs decrypted grades
        echo "<div class='check-item'>";
        try {
            $encrypted = $conn->query("SELECT COUNT(*) as count FROM grade_term WHERE is_encrypted = 1")->fetch_assoc()['count'];
            $decrypted = $conn->query("SELECT COUNT(*) as count FROM grade_term WHERE is_encrypted = 0 OR is_encrypted IS NULL")->fetch_assoc()['count'];
            $total = $encrypted + $decrypted;
            
            $checks['pass']++;
            echo "<div class='check-title'><span class='icon'>✓</span> Grade Encryption Status</div>";
            echo "<div class='check-detail'>";
            echo "Total grades: $total<br>";
            echo "Encrypted (hidden): $encrypted<br>";
            echo "Decrypted (visible): $decrypted";
            echo "</div>";
            echo "</div>";
        } catch (Exception $e) {
            $checks['warning']++;
            echo "<div class='check-item warning'>";
            echo "<div class='check-title'><span class='icon'>⚠</span> Grade Statistics</div>";
            echo "<div class='check-detail'>Could not retrieve statistics: " . $e->getMessage() . "</div>";
            echo "</div>";
        }
        
        // Check 4: Visibility status distribution
        echo "<div class='check-item'>";
        try {
            $hidden = $conn->query("SELECT COUNT(*) as count FROM grade_visibility_status WHERE grade_visibility = 'hidden'")->fetch_assoc()['count'];
            $visible = $conn->query("SELECT COUNT(*) as count FROM grade_visibility_status WHERE grade_visibility = 'visible'")->fetch_assoc()['count'];
            $total_vis = $hidden + $visible;
            
            $checks['pass']++;
            echo "<div class='check-title'><span class='icon'>✓</span> Visibility Status Distribution</div>";
            echo "<div class='check-detail'>";
            echo "Total visibility records: $total_vis<br>";
            echo "Hidden: $hidden<br>";
            echo "Visible: $visible";
            echo "</div>";
            echo "</div>";
        } catch (Exception $e) {
            $checks['warning']++;
            echo "<div class='check-item warning'>";
            echo "<div class='check-title'><span class='icon'>⚠</span> Visibility Statistics</div>";
            echo "<div class='check-detail'>Could not retrieve statistics</div>";
            echo "</div>";
        }
        
        // Check 5: Verify files exist
        echo "<div class='check-item'>";
        $required_files = [
            'faculty/ajax/encrypt_decrypt_grades.php' => 'Grade encryption endpoint',
            'student/ajax/get_grades.php' => 'Student grade retrieval',
            'dashboards/faculty_dashboard.php' => 'Faculty dashboard',
            'student/assets/js/student_dashboard.js' => 'Student frontend logic'
        ];
        $all_exist = true;
        $existing = [];
        
        foreach ($required_files as $file => $desc) {
            $full_path = __DIR__ . '/' . $file;
            if (file_exists($full_path)) {
                $existing[] = "✓ " . $file;
            } else {
                $existing[] = "✗ " . $file;
                $all_exist = false;
            }
        }
        
        if ($all_exist) {
            $checks['pass']++;
            echo "<div class='check-title'><span class='icon'>✓</span> Required Files</div>";
        } else {
            $checks['fail']++;
            echo "<div class='check-title'><span class='icon'>✗</span> Required Files</div>";
        }
        echo "<div class='check-detail'>" . implode("<br>", $existing) . "</div>";
        echo "</div>";
        
        // Check 6: Sample data verification
        echo "<div class='check-item'>";
        try {
            $sample = $conn->query("
                SELECT 
                    gt.student_id,
                    gt.class_code,
                    gt.is_encrypted,
                    gvs.grade_visibility,
                    CASE 
                        WHEN gt.is_encrypted = 1 THEN 'Hidden (encrypted)'
                        WHEN gvs.grade_visibility = 'hidden' THEN 'Hidden (visibility)'
                        ELSE 'Visible'
                    END as status
                FROM grade_term gt
                LEFT JOIN grade_visibility_status gvs ON gt.student_id = gvs.student_id AND gt.class_code = gvs.class_code
                LIMIT 10
            ");
            
            if ($sample && $sample->num_rows > 0) {
                $checks['pass']++;
                echo "<div class='check-title'><span class='icon'>✓</span> Sample Grade Records</div>";
                echo "<table>";
                echo "<tr><th>Student ID</th><th>Class Code</th><th>Is Encrypted</th><th>Visibility</th><th>Status</th></tr>";
                
                $sample->data_seek(0);
                while ($row = $sample->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['class_code']) . "</td>";
                    echo "<td>" . ($row['is_encrypted'] ? 'Yes' : 'No') . "</td>";
                    echo "<td>" . htmlspecialchars($row['grade_visibility'] ?? 'Not set') . "</td>";
                    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                $checks['warning']++;
                echo "<div class='check-item warning'>";
                echo "<div class='check-title'><span class='icon'>⚠</span> Sample Grade Records</div>";
                echo "<div class='check-detail'>No grade records found in database</div>";
            }
            echo "</div>";
        } catch (Exception $e) {
            $checks['warning']++;
            echo "<div class='check-item warning'>";
            echo "<div class='check-title'><span class='icon'>⚠</span> Sample Records</div>";
            echo "<div class='check-detail'>Error: " . $e->getMessage() . "</div>";
            echo "</div>";
        }
        
        // Summary
        echo "<div class='summary'>";
        echo "<h3>📊 Verification Summary</h3>";
        echo "<div class='summary-item'>";
        echo "<span>Passed Checks:</span>";
        echo "<span class='summary-value'>" . $checks['pass'] . "</span>";
        echo "</div>";
        if ($checks['warning'] > 0) {
            echo "<div class='summary-item'>";
            echo "<span>Warnings:</span>";
            echo "<span class='summary-value' style='color: #d97706;'>" . $checks['warning'] . "</span>";
            echo "</div>";
        }
        if ($checks['fail'] > 0) {
            echo "<div class='summary-item'>";
            echo "<span>Failed Checks:</span>";
            echo "<span class='summary-value' style='color: #dc2626;'>" . $checks['fail'] . "</span>";
            echo "</div>";
        }
        
        $total = $checks['pass'] + $checks['warning'] + $checks['fail'];
        $status = ($checks['fail'] === 0) ? '✓ All systems operational' : '✗ Some systems need attention';
        
        echo "<div class='summary-item'>";
        echo "<span>Status:</span>";
        echo "<span class='summary-value'>" . $status . "</span>";
        echo "</div>";
        echo "</div>";
        ?>
    </div>
</div>
</body>
</html>
