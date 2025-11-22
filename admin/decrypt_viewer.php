<?php
/**
 * Admin Dashboard - Encrypted Data Viewer & Decryption Tool
 * Use this to decrypt and view encrypted student data, grades, etc.
 */

define('SYSTEM_ACCESS', true);
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/encryption.php';

// Require admin access
requireAdmin();

// Get current user
$current_user = getCurrentUser();

// Initialize decryption
try {
    Encryption::init();
} catch (Exception $e) {
    die("Encryption initialization failed: " . $e->getMessage());
}

// Handle decryption requests
$decrypted_data = [];
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid CSRF token";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'decrypt_student') {
            // Decrypt a single student record
            $student_id = $_POST['student_id'] ?? '';
            
            if (empty($student_id)) {
                $error_message = "Student ID is required";
            } else {
                try {
                    $result = $conn->query("SELECT * FROM student WHERE student_id = '" . $conn->real_escape_string($student_id) . "'");
                    
                    if (!$result || $result->num_rows === 0) {
                        $error_message = "Student not found";
                    } else {
                        $student = $result->fetch_assoc();
                        
                        // Decrypt encrypted fields
                        $decrypted_data = [
                            'student_id' => $student['student_id'],
                            'first_name' => !empty($student['first_name']) ? Encryption::decrypt($student['first_name']) : 'N/A',
                            'last_name' => !empty($student['last_name']) ? Encryption::decrypt($student['last_name']) : 'N/A',
                            'email' => !empty($student['email']) ? Encryption::decrypt($student['email']) : 'N/A',
                            'birthday' => !empty($student['birthday']) ? Encryption::decrypt($student['birthday']) : 'N/A',
                            'course_code' => $student['course_code'] ?? 'N/A',
                            'created_at' => $student['created_at'] ?? 'N/A'
                        ];
                        
                        $success_message = "Successfully decrypted student data for: {$decrypted_data['first_name']} {$decrypted_data['last_name']}";
                    }
                } catch (Exception $e) {
                    $error_message = "Decryption error: " . $e->getMessage();
                }
            }
        }
        
        elseif ($action === 'decrypt_grades') {
            // Decrypt a single grade record
            $grade_id = $_POST['grade_id'] ?? '';
            
            if (empty($grade_id)) {
                $error_message = "Grade ID is required";
            } else {
                try {
                    $result = $conn->query("SELECT * FROM student_grades WHERE id = " . intval($grade_id));
                    
                    if (!$result || $result->num_rows === 0) {
                        $error_message = "Grade record not found";
                    } else {
                        $grade = $result->fetch_assoc();
                        
                        // Decrypt encrypted grade fields
                        $decrypted_data = [
                            'id' => $grade['id'],
                            'student_id' => $grade['student_id'],
                            'class_code' => $grade['class_code'],
                            'term_grade' => !empty($grade['term_grade']) ? Encryption::decrypt($grade['term_grade']) : 'N/A',
                            'midterm_percentage' => !empty($grade['midterm_percentage']) ? Encryption::decrypt($grade['midterm_percentage']) : 'N/A',
                            'finals_percentage' => !empty($grade['finals_percentage']) ? Encryption::decrypt($grade['finals_percentage']) : 'N/A',
                            'quiz_percentage' => !empty($grade['quiz_percentage']) ? Encryption::decrypt($grade['quiz_percentage']) : 'N/A',
                            'created_at' => $grade['created_at'] ?? 'N/A'
                        ];
                        
                        $success_message = "Successfully decrypted grade data for student: {$decrypted_data['student_id']}";
                    }
                } catch (Exception $e) {
                    $error_message = "Decryption error: " . $e->getMessage();
                }
            }
        }
        
        elseif ($action === 'decrypt_all_students') {
            // Decrypt ALL students (for export/backup)
            try {
                $result = $conn->query("SELECT * FROM student LIMIT 100");
                
                if (!$result || $result->num_rows === 0) {
                    $error_message = "No students found";
                } else {
                    $decrypted_data = [];
                    while ($student = $result->fetch_assoc()) {
                        $decrypted_data[] = [
                            'student_id' => $student['student_id'],
                            'first_name' => !empty($student['first_name']) ? Encryption::decrypt($student['first_name']) : 'N/A',
                            'last_name' => !empty($student['last_name']) ? Encryption::decrypt($student['last_name']) : 'N/A',
                            'email' => !empty($student['email']) ? Encryption::decrypt($student['email']) : 'N/A',
                            'course_code' => $student['course_code'] ?? 'N/A'
                        ];
                    }
                    
                    $success_message = "Successfully decrypted " . count($decrypted_data) . " student records";
                }
            } catch (Exception $e) {
                $error_message = "Decryption error: " . $e->getMessage();
            }
        }
    }
}

// Get list of students for the dropdown
$students_list = [];
try {
    $result = $conn->query("SELECT student_id, first_name, last_name FROM student LIMIT 50");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students_list[] = $row;
        }
    }
} catch (Exception $e) {
    // Ignore error
}

// Get list of grades for the dropdown
$grades_list = [];
try {
    $result = $conn->query("SELECT id, student_id FROM student_grades LIMIT 50");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $grades_list[] = $row;
        }
    }
} catch (Exception $e) {
    // Ignore error
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encrypted Data Viewer - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 0.95em;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-box strong {
            color: #667eea;
        }
        
        .alerts {
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #f5c6cb;
        }
        
        .section {
            margin-bottom: 40px;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
        }
        
        .section h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.4em;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .results {
            margin-top: 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .results h3 {
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .data-grid {
            overflow-x: auto;
        }
        
        .data-grid table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .data-grid table th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        .data-grid table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        
        .data-grid table tr:hover {
            background: #f8f9fa;
        }
        
        .code {
            background: #f4f4f4;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            word-break: break-all;
            margin-top: 5px;
        }
        
        .hint {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            font-size: 0.9em;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-lock-open"></i> Encrypted Data Viewer</h1>
            <p>Decrypt and view sensitive student and grade data</p>
        </div>
        
        <div class="content">
            <?php if (!empty($error_message)): ?>
                <div class="alerts">
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error_message) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alerts">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($success_message) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong><i class="fas fa-info-circle"></i> How This Works:</strong><br>
                Your encrypted data uses <strong>AES-256-GCM encryption</strong>. This tool decrypts it using your APP_ENCRYPTION_KEY. 
                Only admins can decrypt data. The decryption happens server-side for security.
            </div>
            
            <!-- Decrypt Single Student -->
            <div class="section">
                <h2><i class="fas fa-user"></i> Decrypt Student Data</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCSRFToken()) ?>">
                    <input type="hidden" name="action" value="decrypt_student">
                    
                    <div class="form-group">
                        <label for="student_id">Student ID:</label>
                        <input type="text" id="student_id" name="student_id" placeholder="e.g., 2024-001" required>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-unlock"></i> Decrypt Student
                        </button>
                    </div>
                    
                    <div class="hint">
                        <i class="fas fa-lightbulb"></i> Enter the student ID to decrypt and view their personal data 
                        (first name, last name, email, birthday).
                    </div>
                </form>
            </div>
            
            <!-- Decrypt Single Grade -->
            <div class="section">
                <h2><i class="fas fa-graduation-cap"></i> Decrypt Grade Record</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCSRFToken()) ?>">
                    <input type="hidden" name="action" value="decrypt_grades">
                    
                    <div class="form-group">
                        <label for="grade_id">Grade Record ID:</label>
                        <input type="number" id="grade_id" name="grade_id" placeholder="e.g., 1" required>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-unlock"></i> Decrypt Grade
                        </button>
                    </div>
                    
                    <div class="hint">
                        <i class="fas fa-lightbulb"></i> Enter the grade record ID to decrypt and view the actual grades 
                        (term grade, midterm percentage, finals percentage, etc.).
                    </div>
                </form>
            </div>
            
            <!-- Decrypt All Students -->
            <div class="section">
                <h2><i class="fas fa-database"></i> Decrypt All Students (Bulk)</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCSRFToken()) ?>">
                    <input type="hidden" name="action" value="decrypt_all_students">
                    
                    <div class="hint">
                        <i class="fas fa-warning"></i> <strong>Warning:</strong> This will decrypt and display up to 100 student records at once.
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-unlock"></i> Decrypt All Students
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Display Results -->
            <?php if (!empty($decrypted_data)): ?>
                <div class="results">
                    <h3><i class="fas fa-check-circle"></i> Decrypted Data</h3>
                    
                    <?php if (is_array($decrypted_data) && isset($decrypted_data[0])): ?>
                        <!-- Multiple records -->
                        <div class="data-grid">
                            <table>
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($decrypted_data[0]) as $key): ?>
                                            <th><?= htmlspecialchars($key) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($decrypted_data as $record): ?>
                                        <tr>
                                            <?php foreach ($record as $value): ?>
                                                <td><?= htmlspecialchars($value) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- Single record -->
                        <div class="data-grid">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($decrypted_data as $key => $value): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($key) ?></strong></td>
                                            <td><?= htmlspecialchars($value) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Quick Code Examples -->
            <div class="section">
                <h2><i class="fas fa-code"></i> How to Decrypt in Your Code</h2>
                
                <h3 style="color: #667eea; margin-top: 15px;">1. Basic Decryption:</h3>
                <div class="code"><?= htmlspecialchars("<?php
require_once 'config/encryption.php';

// Initialize
Encryption::init();

// Decrypt a value
\$encrypted_value = /* from database */;
\$plaintext = Encryption::decrypt(\$encrypted_value);
echo \$plaintext;
?>") ?></div>
                
                <h3 style="color: #667eea; margin-top: 15px;">2. Using StudentModel (Auto-Decrypts):</h3>
                <div class="code"><?= htmlspecialchars("<?php
require_once 'includes/StudentModel.php';

\$studentModel = new StudentModel(\$conn);
\$student = \$studentModel->getStudentById('2024-001', \$_SESSION['user_id'], \$_SESSION['role']);

// All fields are already decrypted!
echo \$student['first_name'];  // Decrypted
echo \$student['email'];       // Decrypted
?>") ?></div>
                
                <h3 style="color: #667eea; margin-top: 15px;">3. Using GradesModel (Auto-Decrypts):</h3>
                <div class="code"><?= htmlspecialchars("<?php
require_once 'includes/GradesModel.php';

\$gradesModel = new GradesModel(\$conn);
\$grades = \$gradesModel->getStudentGrades('2024-001', 'CS101-2024-A', \$_SESSION['user_id'], \$_SESSION['role']);

// All fields are already decrypted!
echo \$grades['term_grade'];  // Decrypted
echo \$grades['midterm_percentage'];  // Decrypted
?>") ?></div>
                
                <div class="hint">
                    <i class="fas fa-check"></i> <strong>Recommended:</strong> Use StudentModel and GradesModel classes instead of manual decryption.
                    They handle access control and decryption automatically!
                </div>
            </div>
        </div>
        
        <div class="footer">
            <i class="fas fa-lock"></i> All decryption happens server-side. Encryption Key: <?= substr(getenv('APP_ENCRYPTION_KEY'), 0, 16) ?>...
        </div>
    </div>
</body>
</html>
