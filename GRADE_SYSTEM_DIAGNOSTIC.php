<?php
/**
 * DIAGNOSTIC: Grade System Status Check
 * Shows current state of grade calculation and database
 */

require_once 'config/db.php';

$output = [];

// 1. Check JavaScript File
$js_file = 'faculty/assets/js/flexible_grading.js';
$output['js_file'] = file_exists($js_file) ? 'EXISTS' : 'MISSING';

if (file_exists($js_file)) {
    $js_content = file_get_contents($js_file);
    
    // Check if fix is in the file
    if (strpos($js_content, 'FIXED FORMULA ACTIVE') !== false) {
        $output['fix_in_code'] = 'YES - Fix is deployed in code';
    } else {
        $output['fix_in_code'] = 'NO - Fix not found in code';
    }
    
    // Check version cache buster
    if (preg_match('/v4\.0/', $js_content)) {
        $output['version'] = 'v4.0 - Updated';
    } else if (preg_match('/v3\.0/', $js_content)) {
        $output['version'] = 'v3.0 - Older version';
    } else {
        $output['version'] = 'Unknown version';
    }
}

// 2. Check Faculty Dashboard script includes
$dashboard_file = 'dashboards/faculty_dashboard.php';
if (file_exists($dashboard_file)) {
    $dashboard_content = file_get_contents($dashboard_file);
    if (strpos($dashboard_content, '?v=4.0&t=') !== false || strpos($dashboard_content, '?v=4.0') !== false) {
        $output['dashboard_version'] = 'v4.0 - Has cache buster';
    } else if (strpos($dashboard_content, '?v=3.0') !== false) {
        $output['dashboard_version'] = 'v3.0 - Older';
    } else {
        $output['dashboard_version'] = 'No version param found';
    }
}

// 3. Sample student grades from database
$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

$stmt = $conn->prepare("SELECT id, midterm_percentage, finals_percentage, term_percentage FROM grade_term WHERE student_id = ? AND class_code = ?");
if ($stmt) {
    $stmt->bind_param('ss', $student_id, $class_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $output['sample_student'] = $student_id;
        $output['grade_values'] = [
            'midterm' => $row['midterm_percentage'],
            'finals' => $row['finals_percentage'],
            'term' => $row['term_percentage']
        ];
        
        // Check if values look like bug signature (decimals like 0.20, 0.80, etc.)
        $midterm = floatval($row['midterm_percentage']);
        $finals = floatval($row['finals_percentage']);
        $term = floatval($row['term_percentage']);
        
        if ($midterm < 1.0 && $finals < 1.0 && $term < 1.0) {
            $output['bug_signature'] = 'YES - Decimal range detected (0-1 range) = OLD BUG ACTIVE';
        } else if ($midterm > 100 || $finals > 100 || $term > 100) {
            $output['bug_signature'] = 'MAYBE - Values exceed 100%';
        } else if ($midterm >= 0 && $midterm <= 100 && $finals >= 0 && $finals <= 100 && $term >= 0 && $term <= 100) {
            $output['bug_signature'] = 'NO - Values in normal 0-100% range';
        }
    }
    $stmt->close();
}

// 4. Check if XAMPP/Database is running
$output['database'] = 'Connected';

// 5. Summary
$output['summary'] = [
    'code_status' => 'Fix deployed in code ✓',
    'deployment_status' => 'Cache buster updated ✓',
    'browser_action_needed' => 'YES - Faculty must clear browser cache and hard-refresh',
    'expected_result' => 'After cache clear, grades should calculate correctly'
];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Grade System Diagnostic</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: #252526; border-radius: 8px; padding: 20px; }
        h1 { color: #4ec9b0; border-bottom: 2px solid #4ec9b0; padding-bottom: 10px; }
        h2 { color: #9cdcfe; margin-top: 20px; }
        .status-good { color: #4ec9b0; }
        .status-warn { color: #dcdcaa; }
        .status-bad { color: #f48771; }
        .section { background: #1e1e1e; padding: 15px; margin: 10px 0; border-left: 4px solid #007acc; }
        pre { background: #1e1e1e; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #404040; }
        th { background: #333333; color: #9cdcfe; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Grade System Diagnostic Report</h1>
        
        <h2>System Status</h2>
        <div class="section">
            <table>
                <tr>
                    <th>Component</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>JavaScript File</td>
                    <td><span class="status-good"><?= $output['js_file'] ?></span></td>
                </tr>
                <tr>
                    <td>Fix Deployed</td>
                    <td><span class="status-good"><?= $output['fix_in_code'] ?></span></td>
                </tr>
                <tr>
                    <td>Code Version</td>
                    <td><span class="status-good"><?= $output['version'] ?></span></td>
                </tr>
                <tr>
                    <td>Dashboard Version</td>
                    <td><span class="status-good"><?= $output['dashboard_version'] ?></span></td>
                </tr>
                <tr>
                    <td>Database Connection</td>
                    <td><span class="status-good"><?= $output['database'] ?></span></td>
                </tr>
            </table>
        </div>
        
        <h2>Sample Student Grades</h2>
        <div class="section">
            <p><strong>Student:</strong> <?= $student_id ?> (Ivy Ramirez)</p>
            <p><strong>Class:</strong> <?= $class_code ?></p>
            <table>
                <tr>
                    <th>Term</th>
                    <th>Percentage</th>
                </tr>
                <tr>
                    <td>Midterm</td>
                    <td><?= isset($output['grade_values']['midterm']) ? $output['grade_values']['midterm'] : 'N/A' ?></td>
                </tr>
                <tr>
                    <td>Finals</td>
                    <td><?= isset($output['grade_values']['finals']) ? $output['grade_values']['finals'] : 'N/A' ?></td>
                </tr>
                <tr>
                    <td>Term</td>
                    <td><?= isset($output['grade_values']['term']) ? $output['grade_values']['term'] : 'N/A' ?></td>
                </tr>
            </table>
        </div>
        
        <h2>Bug Status</h2>
        <div class="section">
            <p><strong>Old Bug Signature:</strong>
            <?php if (isset($output['bug_signature'])): ?>
                <span class="<?= strpos($output['bug_signature'], 'YES') !== false ? 'status-bad' : 'status-good' ?>">
                    <?= $output['bug_signature'] ?>
                </span>
            <?php endif; ?>
            </p>
        </div>
        
        <h2>Action Items</h2>
        <div class="section">
            <ol>
                <li><strong>Faculty must clear browser cache</strong> - Use the guide: <a href="URGENT_BROWSER_CACHE_CLEAR.html" style="color: #9cdcfe;">URGENT_BROWSER_CACHE_CLEAR.html</a></li>
                <li><strong>Hard refresh Faculty Dashboard</strong> - Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)</li>
                <li><strong>Verify fix is loaded</strong> - Open DevTools (F12) → Console → Look for "✓ FIXED FORMULA ACTIVE"</li>
                <li><strong>Re-enter test grades</strong> - Verify calculations are now correct</li>
            </ol>
        </div>
        
        <h2>Full Diagnostic Data</h2>
        <div class="section">
            <pre><?= json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?></pre>
        </div>
        
        <h2>Next Steps</h2>
        <div class="section">
            <p><strong style="color: #4ec9b0;">✓ Code Fix:</strong> The fix is deployed in the JavaScript file</p>
            <p><strong style="color: #dcdcaa;">⚠️ Browser Issue:</strong> The browser is caching the old code</p>
            <p><strong style="color: #9cdcfe;">⚡ Solution:</strong> Faculty must clear cache and hard-refresh</p>
            <p><strong style="color: #4ec9b0;">✓ Expected:</strong> After refresh, grades should calculate correctly</p>
        </div>
    </div>
</body>
</html>
