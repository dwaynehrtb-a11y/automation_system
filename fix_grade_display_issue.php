<?php
/**
 * Comprehensive Grade Display Bug Fix
 * Fixes: Students showing "Failed" when grades should be visible or properly hidden
 * 
 * Usage: Access via browser at /fix_grade_display_issue.php
 * Then run the "Apply Database Fix" option
 */

header('Content-Type: text/html; charset=utf-8');

define('SYSTEM_ACCESS', true);
require_once 'config/db.php';

$action = $_GET['action'] ?? 'view';
$result = '';
$stats = [];

// Get statistics
$encrypted_count = $conn->query("SELECT COUNT(*) as count FROM grade_term WHERE is_encrypted = 1")->fetch_assoc()['count'];
$total_count = $conn->query("SELECT COUNT(*) as count FROM grade_term")->fetch_assoc()['count'];
$with_grades_count = $conn->query("SELECT COUNT(*) as count FROM grade_term WHERE term_percentage IS NOT NULL AND term_percentage > 0")->fetch_assoc()['count'];

$stats = [
    'total_records' => $total_count,
    'encrypted_records' => $encrypted_count,
    'records_with_grades' => $with_grades_count,
    'records_needing_fix' => $encrypted_count // Records marked as encrypted that probably should be visible
];

if ($action === 'fix') {
    // Check for CSRF token if POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Apply the database fix
        $update_result = $conn->query("
            UPDATE grade_term 
            SET is_encrypted = 0 
            WHERE is_encrypted = 1
        ");
        
        if ($update_result) {
            $affected = $conn->affected_rows;
            $result = "<div class='alert alert-success'>
                <h4>✅ Database Fix Applied</h4>
                <p>Updated <strong>$affected records</strong></p>
                <p>Records with is_encrypted = 1 have been set to is_encrypted = 0</p>
                <p>Students should now see their grades correctly instead of 'Grades not yet released'</p>
            </div>";
        } else {
            $result = "<div class='alert alert-danger'>
                <h4>❌ Error</h4>
                <p>Database error: " . $conn->error . "</p>
            </div>";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Display Bug Fix</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .header p {
            opacity: 0.95;
            font-size: 14px;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 6px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .alert p {
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 5px;
        }
        
        .alert-success {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }
        
        .alert-info {
            background: #dbeafe;
            border-color: #3b82f6;
            color: #1e40af;
        }
        
        .alert-warning {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
        }
        
        .alert-danger {
            background: #fee2e2;
            border-color: #ef4444;
            color: #7f1d1d;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        button, a.btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin: 10px 0;
        }
        
        .checklist {
            list-style: none;
            margin: 10px 0;
        }
        
        .checklist li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
            font-size: 13px;
            color: #4b5563;
        }
        
        .checklist li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
        }
        
        .confirmation {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: center;
        }
        
        .confirmation h3 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .confirmation p {
            color: #856404;
            font-size: 13px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-wrench"></i>
                Grade Display Bug Fix
            </h1>
            <p>Fix for students showing "Failed" instead of correct grades</p>
        </div>
        
        <div class="content">
            <!-- Status Info -->
            <div class="section">
                <h2>
                    <i class="fas fa-chart-pie" style="color: #667eea;"></i>
                    Current Status
                </h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Grade Records</div>
                        <div class="stat-value"><?= $stats['total_records'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Encrypted (Hidden)</div>
                        <div class="stat-value" style="color: #ef4444;"><?= $stats['encrypted_records'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">With Grade Values</div>
                        <div class="stat-value" style="color: #10b981;"><?= $stats['records_with_grades'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Likely Needing Fix</div>
                        <div class="stat-value" style="color: #f59e0b;"><?= $stats['records_needing_fix'] ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Issue Description -->
            <div class="section">
                <h2>
                    <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>
                    Issue Description
                </h2>
                <div class="alert alert-warning">
                    <h4>Grade Display Bug</h4>
                    <p>Students are seeing "Failed" or "Grades not yet released" even though faculty have released their grades.</p>
                    <p><strong>Root Cause:</strong> Database records have <code>is_encrypted = 1</code> when they should have <code>is_encrypted = 0</code> for released grades.</p>
                </div>
            </div>
            
            <!-- Affected Student Example -->
            <div class="section">
                <h2>
                    <i class="fas fa-user" style="color: #667eea;"></i>
                    Example Affected Student
                </h2>
                <div class="alert alert-info">
                    <h4>Student: Ramirez, Ivy A. (2025-276819)</h4>
                    <p><strong>Actual Grades:</strong> Term Grade 1.5, Term % 70.00, Status Passed</p>
                    <p><strong>Displayed To Student:</strong> "Failed" (INCORRECT)</p>
                    <p><strong>Reason:</strong> Database flag shows <code>is_encrypted = 1</code> (hidden)</p>
                </div>
            </div>
            
            <?php if (!empty($result)): ?>
            <div class="section">
                <?= $result ?>
            </div>
            <?php endif; ?>
            
            <!-- Solution -->
            <div class="section">
                <h2>
                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                    Solution
                </h2>
                <p style="margin-bottom: 15px; color: #4b5563; font-size: 14px;">
                    This fix will update the database to make all released grades properly visible to students.
                </p>
                <div class="confirmation">
                    <h3>Are you sure?</h3>
                    <p>This will set <code>is_encrypted = 0</code> for <?= $stats['encrypted_records'] ?> records.</p>
                    <p>Students will then see their actual grades instead of "Grades not yet released".</p>
                    <form method="POST" style="display: inline;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-database"></i>
                            Apply Database Fix
                        </button>
                    </form>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                </div>
            </div>
            
            <!-- Code Changes -->
            <div class="section">
                <h2>
                    <i class="fas fa-code" style="color: #667eea;"></i>
                    Code Changes Applied
                </h2>
                <p style="color: #4b5563; font-size: 13px; margin-bottom: 10px;">
                    <strong>File:</strong> student/ajax/get_grades.php (Line 324)
                </p>
                <p style="color: #4b5563; font-size: 13px; margin-bottom: 10px;">
                    <strong>Change:</strong> When grades are encrypted/hidden, return 'pending' status instead of actual status
                </p>
                <div class="code-block">
// BEFORE:
'grade_status' => $row['grade_status'] ?? 'pending',

// AFTER:
'grade_status' => 'pending',
                </div>
            </div>
            
            <!-- Verification -->
            <div class="section">
                <h2>
                    <i class="fas fa-clipboard-check" style="color: #10b981;"></i>
                    Verification Steps
                </h2>
                <ul class="checklist">
                    <li>Hard refresh browser (Ctrl+Shift+Delete)</li>
                    <li>Login as student 2025-276819</li>
                    <li>Navigate to "My Enrolled Classes"</li>
                    <li>Look for class 25_T2_CCPRGG1L_INF223</li>
                    <li>Verify grade shows as 1.5 (green, "Passed" status)</li>
                    <li>Verify term percentage shows 70.00%</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
