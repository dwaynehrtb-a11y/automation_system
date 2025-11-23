    <?php
    define('SYSTEM_ACCESS', true);
    require_once 'config/session.php';
    require_once 'config/db.php';
    requireAdmin();

    // Generate CSRF token
    if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Get dashboard stats with improved error handling
    $total_faculty = getSafeCount($conn, 'users', 'role = ?', ['faculty'], 's');
    $total_students = getSafeCount($conn, 'student');
    $total_subjects = getSafeCount($conn, 'subjects');
    $active_sections = getSafeSectionCount($conn);

    try {
    $total_classes = getSafeCount($conn, 'class');
    } catch (Exception $e) {
    $total_classes = 0;
    }

    // Get all subjects for display
    try {
    $subjects_result = $conn->query("SELECT * FROM subjects ORDER BY course_code");
    $subjects = $subjects_result ?: null;
    } catch (Exception $e) {
    error_log("Subjects query error: " . $e->getMessage());
    $subjects = null;
    }

    // Get faculty without complex joins
    $faculty_list = null;
    $faculty_error = null;

    try {
    if (!$conn) {
    $faculty_error = "No database connection";
    } else {
    $result = $conn->query("SELECT id, name, employee_id, email FROM users WHERE role = 'faculty' ORDER BY name");
    if ($result === false) {
    $faculty_error = "Query failed: " . $conn->error;
    } else {
    $faculty_list = $result;
    }
    }
    } catch (Exception $e) {
    $faculty_error = "Exception: " . $e->getMessage();
    }

    // Get sections for simple management
    $sections_list = null;
    try {
    $tableName = 'sections'; // Hard-code to match your AJAX script
    $sections_sql = "SELECT section_id, section_code, created_at FROM $tableName ORDER BY section_code";
    $sections_list = $conn->query($sections_sql);

    if ($sections_list) {
    echo "<!-- DEBUG: Found " . $sections_list->num_rows . " sections -->";
    if ($sections_list->num_rows > 0) {
    $sections_list->data_seek(0); // Reset pointer
    while($row = $sections_list->fetch_assoc()) {
    echo "<!-- DEBUG: Section " . $row['section_code'] . " -->";
    }
    $sections_list->data_seek(0); // Reset for actual use
    }
    } else {
    echo "<!-- DEBUG: sections_list is null or false -->";
    echo "<!-- DEBUG: MySQL error: " . $conn->error . " -->";
    }

    } catch (Exception $e) {
    error_log("Sections query error: " . $e->getMessage());
    $sections_list = null;
    }

    // Get current user info
    $current_user = getCurrentUser();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>NU Academic Management System - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="admin/assets/css/admin-dashboard.css?v=<?= time() ?>">
    </head>
    <body>
    <!-- External Sidebar Toggle (Top-Left) -->
    <button class="sidebar-toggle-external" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
    </button>

    <header class="nu-header">
    <div class="nu-header-container">
    <!-- NU Brand (Left Side) -->
    <div class="nu-brand">
    <div class="nu-logo-circle">
    <img src="/automation_system/admin/assets/nu_logo.png" alt="NU Logo" class="nu-logo-img">
    </div>
    <div class="nu-brand-text">
    <span class="nu-title-main">NATIONAL UNIVERSITY</span>
    <span class="nu-title-sub">GRADING SYSTEM</span>
    </div>
    </div>

    <!-- User Info (Right Side) -->
    <div class="user-info">
    <div class="user-avatar">
    <?= strtoupper(substr($current_user['name'], 0, 2)) ?>
    </div>
    <div class="user-details">
    <div class="user-name">
    <?= htmlspecialchars($current_user['name']) ?>
    </div>
    <div class="user-role">
    Administrator
    </div>
    </div>
    </div>
    </div>

    <!-- NU Gold Bar -->
    <div class="nu-gold-bar">
    <span class="nu-campus">NU LIPA</span>
    </div>
    </header>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
    <div class="brand">
    <h2>Admin Portal</h2>
    </div>
    </div>

    <nav class="sidebar-nav">
    <div class="nav-item">
    <a href="#dashboard" class="nav-link active" onclick="showSection('dashboard'); return false;">
    <i class="fas fa-tachometer-alt"></i>
    Dashboard
    </a>
    </div>

    <div class="nav-item">
    <a href="#subjects" class="nav-link" onclick="showSection('subjects')">
    <i class="fas fa-book"></i>
    Manage Subjects
    </a>
    </div>

    <div class="nav-item">
    <a href="#faculty" class="nav-link" onclick="showSection('faculty')">
    <i class="fas fa-chalkboard-teacher"></i>
    Manage Faculty
    </a>
    </div>

    <div class="nav-item">
    <a href="#students" class="nav-link" onclick="showSection('students')">
    <i class="fas fa-user-graduate"></i>
    Manage Students
    </a>
    </div>

    <div class="nav-item">
    <a href="#sections" class="nav-link" onclick="showSection('sections')">
    <i class="fas fa-calendar-alt"></i>
    Manage Sections
    </a>
    </div>

    <div class="nav-item">
    <a href="#classes" class="nav-link" onclick="showSection('classes')">
    <i class="fas fa-chalkboard"></i>
    Manage Classes
    </a>
    </div>

    <div class="nav-item">
    <a href="#reports" class="nav-link" onclick="showSection('reports')">
    <i class="fas fa-chart-bar"></i>
    Reports & Analytics
    </a>
    </div>

    <div class="nav-item" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1);">
    <a href="auth/logout.php" class="nav-link">
    <i class="fas fa-sign-out-alt"></i>
    Logout
    </a>
    </div>
    </nav>
    </div>
<div class="main-content" id="mainContent">
   <main class="content">
    <!-- Welcome Section (Shows on ALL pages) -->
    <div class="welcome-section">
        <h1 class="welcome-title">Welcome, <?= htmlspecialchars($current_user['name']) ?></h1>
        <p class="welcome-subtitle">Manage the academic system and oversee all operations</p>
    </div>

    <!-- Dashboard Section -->
    <div id="dashboard" class="section active">
        <!-- Stats Cards -->
        <div class="stats-grid">
    <div class="stat-card">
    <div class="stat-header">
    <div>
    <div class="stat-number"><?= $total_faculty ?></div>
    <div class="stat-label">Faculty Members</div>
    </div>
    <div class="stat-icon success">
    <i class="fas fa-chalkboard-teacher"></i>
    </div>
    </div>
    </div>

    <div class="stat-card">
    <div class="stat-header">
    <div>
    <div class="stat-number"><?= $total_subjects ?></div>
    <div class="stat-label">Total Subjects</div>
    </div>
    <div class="stat-icon warning">
    <i class="fas fa-book"></i>
    </div>
    </div>
    </div>

    <div class="stat-card">
    <div class="stat-header">
    <div>
    <div class="stat-number"><?php 
    // Get total students count
    try {
    $student_count_query = "SELECT COUNT(*) as total FROM student WHERE status = 'active'";
    $student_count_result = $conn->query($student_count_query);
    $total_students = $student_count_result ? $student_count_result->fetch_assoc()['total'] : 0;
    echo $total_students;
    } catch (Exception $e) {
    echo '0';
    }
    ?></div>
    <div class="stat-label">Active Students</div>
    </div>
    <div class="stat-icon info">
    <i class="fas fa-user-graduate"></i>
    </div>
    </div>
    </div>

    <div class="stat-card">
    <div class="stat-header">
    <div>
    <div class="stat-number"><?= $active_sections ?></div>
    <div class="stat-label">Active Sections</div>
    </div>
    <div class="stat-icon primary">
    <i class="fas fa-calendar-alt"></i>
    </div>
    </div>
    </div>

    <div class="stat-card">
    <div class="stat-header">
    <div>
    <div class="stat-number"><?= $total_classes ?></div>
    <div class="stat-label">Total Classes</div>
    </div>
    <div class="stat-icon primary">
    <i class="fas fa-chalkboard"></i>
</div>
    </div>
    </div>

    <div class="stat-card">
    <div class="stat-header">
    <div>
    <div class="stat-number"><?php 
    // Get total enrollments count
    try {
    $enrollment_count_query = "SELECT COUNT(*) as total FROM class_enrollments WHERE status = 'enrolled'";
    $enrollment_count_result = $conn->query($enrollment_count_query);
    $total_enrollments = $enrollment_count_result ? $enrollment_count_result->fetch_assoc()['total'] : 0;
    echo $total_enrollments;
    } catch (Exception $e) {
    echo '0';
    }
    ?></div>
    <div class="stat-label">Total Enrollments</div>
    </div>
    <div class="stat-icon success">
    <i class="fas fa-users"></i>
    </div>
    </div>
    </div>
    </div>
<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-header">
        <i class="fas fa-filter"></i>
        Filter Classes
    </div>
    <div class="filter-controls">
        <div class="filter-group">
            <label class="filter-label">Academic Year</label>
            <select id="academicYearFilter">
                <option value="">Select Academic Year</option>
                <option value="24">2024-2025</option>
                <option value="25">2025-2026</option>
                <option value="26">2026-2027</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label class="filter-label">Term</label>
            <select id="termFilter">
                <option value="">Select Term</option>
                <option value="T1">1st Term</option>
                <option value="T2">2nd Term</option>
                <option value="T3">3rd Term</option>
            </select>
        </div>
        
        <button class="btn-view">
            <i class="fas fa-eye"></i>
            View
        </button>
        
        <button class="btn-clear">
            <i class="fas fa-times"></i>
            Clear
        </button>
    </div>
</div>

    <!-- Recent Activities Grid -->
    <div class="dashboard-grid">
    <!-- Recent Students -->
    <div class="table-container">
    <div class="table-header">
    <h3>
    <i class="fas fa-user-plus"></i>
    Recent Students
    </h3>
    </div>
    <div class="table-wrapper">
    <table class="table three-column">
    <thead>
    <tr>
    <th>Student ID</th>
    <th>Name</th>
    <th>Status</th>
    </tr>
    </thead>
    <tbody>
    <?php 
    try {
    $recent_students_query = "
    SELECT 
    student_id,
    CONCAT(last_name, ', ', first_name) as name,
    status
    FROM student 
    ORDER BY enrollment_date DESC 
    LIMIT 5
    ";
    $recent_students_result = $conn->query($recent_students_query);

    if ($recent_students_result && $recent_students_result->num_rows > 0) {
    while($student = $recent_students_result->fetch_assoc()): 
    $status_class = match($student['status']) {
    'active' => 'badge-success',
    'inactive' => 'badge-secondary',
    'graduated' => 'badge-info',
    'transferred' => 'badge-warning',
    'suspended' => 'badge-danger',
    'pending' => 'badge-warning',
    default => 'badge-secondary'
    };
    ?>
    <tr>
    <td><strong><?= htmlspecialchars($student['student_id']) ?></strong></td>
    <td><?= htmlspecialchars($student['name']) ?></td>
    <td><span class="badge <?= $status_class ?>"><?= ucfirst($student['status']) ?></span></td>
    </tr>
    <?php 
    endwhile; 
    } else {
    echo '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No students found</td></tr>';
    }
    } catch (Exception $e) {
    echo '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">Error loading students</td></tr>';
    }
    ?>
    </tbody>
    </table>
    </div>
    </div>

    <!-- Recent Sections -->
    <div class="table-container">
    <div class="table-header">
    <h3>
    <i class="fas fa-clock"></i>
    Recent Sections
    </h3>
    </div>
    <div class="table-wrapper">
    <table class="table three-column">
    <thead>
    <tr>
    <th>Section</th>
    <th>Date Added</th>
    <th>Status</th>
    </tr>
    </thead>
    <tbody>
    <?php 
    try {
    $check_sections = $conn->query("SHOW TABLES LIKE 'sections'");
    if ($check_sections && $check_sections->num_rows > 0) {
    $sections_query = "SELECT section_id, section_code, created_at FROM sections ORDER BY created_at DESC LIMIT 5";
    } else {
    $sections_query = "SELECT section_id, section_code, created_at FROM section ORDER BY created_at DESC LIMIT 5";
    }

    $sections_result = $conn->query($sections_query);

    if ($sections_result && $sections_result->num_rows > 0) {
    while($section = $sections_result->fetch_assoc()): 
    ?>
    <tr>
    <td><strong><?= htmlspecialchars($section['section_code']) ?></strong></td>
    <td><?= date('M j, Y', strtotime($section['created_at'])) ?></td>
    <td><span class="badge badge-success">Active</span></td>
    </tr>
    <?php 
    endwhile; 
    } else {
    echo '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No sections found</td></tr>';
    }
    } catch (Exception $e) {
    echo '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">Error loading sections</td></tr>';
    }
    ?>
    </tbody>
    </table>
    </div>
    </div>
    </div>
    </div>
    <!-- Students Management Section -->
    <div id="students" class="section">
    

    <div class="form-section">
    <div class="form-header">
    <h3>
    <i class="fas fa-user-plus"></i>
    Add New Student
    </h3>
    <p class="form-subtitle">Register a new student and send login credentials via email</p>
    </div>
   
    <div class="form-body">
    <form id="addStudentForm">
    <div class="form-grid">
    <div class="form-group">
    <label for="student_id" class="form-label">
    <i class="fas fa-id-card"></i>
    Student ID *
    </label>
    <input type="text" id="student_id" name="student_id" class="form-control" 
    placeholder="e.g., 2024-123456" maxlength="50" required>
    <small class="form-text text-muted">This will be used as the username</small>
    </div>

    <div class="form-group">
    <label for="first_name" class="form-label">
    <i class="fas fa-user"></i>
    First Name *
    </label>
    <input type="text" id="first_name" name="first_name" class="form-control" 
    placeholder="e.g., Juan" required>
    </div>

    <div class="form-group">
    <label for="last_name" class="form-label">
    <i class="fas fa-user"></i>
    Last Name *
    </label>
    <input type="text" id="last_name" name="last_name" class="form-control" 
    placeholder="e.g., Dela Cruz" required>
    </div>

    <div class="form-group">
    <label for="middle_initial" class="form-label">
    <i class="fas fa-user"></i>
    Middle Initial
    </label>
    <input type="text" id="middle_initial" name="middle_initial" class="form-control" 
    placeholder="e.g., S" maxlength="2">
    </div>

    <div class="form-group">
    <label for="student_email" class="form-label">
    <i class="fas fa-envelope"></i>
    Email Address *
    </label>
    <input type="email" id="student_email" name="email" class="form-control" 
    placeholder="e.g., juan.delacruz@email.com" required>
    <small class="form-text text-muted">Login credentials will be sent to this email</small>
    </div>

    <div class="form-group">
    <label for="birthday" class="form-label">
    <i class="fas fa-calendar"></i>
    Birthday *
    </label>
    <input type="date" id="birthday" name="birthday" class="form-control" required>
    </div>
    </div>

    <div class="form-actions">
    <button type="submit" class="btn btn-primary" id="addStudentBtn">
    <span class="btn-text">
    <i class="fas fa-paper-plane"></i>
    Create Account & Send Email
    </span>
    <span class="btn-loading" style="display: none;">
    <i class="fas fa-spinner fa-spin"></i>
    Creating...
    </span>
    </button>
    </div>
    </form>
    </div>
    </div>

 <!-- Bulk Import Section -->
<div class="form-section" style="margin-top: 30px;">
    <div class="form-header">
        <h3>
            <i class="fas fa-file-csv"></i>
            Bulk Import Students
        </h3>
        <p class="form-subtitle">Upload a CSV file to create multiple student accounts at once</p>
    </div>
    
    <div class="form-body">
        <!-- Import Info Card -->
        <div class="import-section" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 24px; border-radius: 16px; margin-bottom: 24px; border: 2px solid #0ea5e9; box-shadow: 0 4px 6px rgba(14, 165, 233, 0.1);">
            <div style="display: flex; align-items: center; gap: 20px;">
                <!-- CSV Icon -->
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #0ea5e9, #0284c7); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);">
                    <i class="fas fa-file-csv" style="font-size: 32px; color: white;"></i>
                </div>

                <!-- Text Content -->
                <div style="flex: 1;">
                    <h4 style="margin: 0 0 8px 0; color: #0c4a6e; font-size: 18px; font-weight: 700;">
                        <i class="fas fa-upload"></i> Quick Import
                    </h4>
                    <p style="margin: 0; color: #0369a1; font-size: 14px; line-height: 1.5;">
                        Upload a CSV file to create multiple student accounts at once. Save time by importing bulk student data. All students will receive activation emails automatically.
                    </p>
                </div>

                <!-- Import Button -->
                <button type="button" class="btn btn-primary" onclick="openStudentImportModal()" style="flex-shrink: 0; white-space: nowrap;">
                    <i class="fas fa-file-import"></i>
                    Import CSV
                </button>
            </div>

            <!-- Help Text -->
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(14, 165, 233, 0.2);">
                <small style="color: #0369a1; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-info-circle"></i>
                   <span>Download the <a href="ajax/download_student_template_excel.php" style="color: #0284c7; text-decoration: underline; font-weight: 600;">CSV template</a>
                </small>
            </div>
        </div>

        <!-- Instructions -->
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; border-radius: 8px;">
            <h4 style="color: #856404; font-size: 1rem; margin-bottom: 10px;">
                <i class="fas fa-exclamation-triangle"></i> Important Instructions
            </h4>
            <ul style="color: #856404; font-size: 0.9rem; line-height: 1.8; margin-left: 20px;">
                <li><strong>Required columns:</strong> student_id, last_name, first_name, email</li>
                <li><strong>Optional columns:</strong> middle_initial, birthday (YYYY-MM-DD), status (active/inactive)</li>
                <li>Email addresses must be unique and valid</li>
                <li>Student IDs must follow format: YYYY-XXXXXX (e.g., 2024-123456)</li>
                <li>All students will receive an email with account activation instructions</li>
                <li>Maximum 500 students per upload</li>
            </ul>
        </div>
    </div>
</div>
    <!-- Students List -->
    <div class="table-container">
    
    <!-- Encryption Controls -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 20px; background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); border-radius: 12px; box-shadow: 0 8px 24px rgba(30, 58, 138, 0.2); border: 1px solid rgba(251, 191, 36, 0.2);">
        <div style="display: flex; align-items: center; gap: 20px; color: white; flex: 1;">
            <div style="font-size: 32px; color: #fbbf24;">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 1.1em;">Student Data Encryption</div>
                <div style="font-size: 0.9em; opacity: 0.9; margin-top: 4px;">
                    Status: <span id="encryptionStatusTable" style="font-weight: 700; color: #fbbf24; text-transform: uppercase;">Checking...</span>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button id="decryptBtnTable" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; padding: 12px 28px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.95em; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(16, 185, 129, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.3)'">
                <i class="fas fa-unlock-alt"></i> Decrypt
            </button>
            <button id="encryptBtnTable" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #1e3a8a; border: none; padding: 12px 28px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.95em; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(251, 191, 36, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(251, 191, 36, 0.3)'">
                <i class="fas fa-lock"></i> Encrypt
            </button>
        </div>
    </div>
    
    <div class="table-header">
    <h3>
    <i class="fas fa-users"></i>
    All Students
    </h3>
    <div class="table-controls">
    <div class="search-box">
    <input type="text" id="studentSearch" placeholder="Search students...">
    <i class="fas fa-search"></i>
    </div>
    <select id="statusFilter" class="form-control">
    <option value="">All Status</option>
    <option value="active">Active</option>
    <option value="pending">Pending Activation</option>
    <option value="inactive">Inactive</option>
    <option value="graduated">Graduated</option>
    <option value="suspended">Suspended</option>
    </select>
    </div>
    </div>
    <div class="table-wrapper">
    <table class="table">
    <thead>
    <tr>
    <th>Student ID</th>
    <th>Full Name</th>
    <th>Email</th>
    <th>Birthday</th>
    <th>Account Status</th>
    <th>Enrollment Status</th>
    <th>Actions</th>
    </tr>
    </thead>
    <tbody id="studentsTableBody">
    <?php 
    // Get students with enrollment count
    $students_query = "
    SELECT 
    s.student_id,
    CONCAT(s.last_name, ', ', s.first_name, 
    CASE WHEN s.middle_initial IS NOT NULL THEN CONCAT(' ', s.middle_initial, '.') ELSE '' END) as full_name,
    s.first_name,
    s.last_name,
    s.middle_initial,
    s.email,
    s.birthday,
    s.status,
    s.account_status,
    s.must_change_password,
    s.first_login_at,
    s.enrollment_date,
    COUNT(ce.enrollment_id) as enrolled_classes
    FROM student s
    LEFT JOIN class_enrollments ce ON s.student_id = ce.student_id AND ce.status = 'enrolled'
    GROUP BY s.student_id, s.first_name, s.last_name, s.middle_initial, s.email, s.birthday, s.status, s.account_status, s.must_change_password, s.first_login_at, s.enrollment_date
    ORDER BY s.enrollment_date DESC, s.last_name, s.first_name
    ";

    $students_result = $conn->query($students_query);

    if ($students_result && $students_result->num_rows > 0) {
        require_once 'config/encryption.php';
        Encryption::init();
        
        while($student = $students_result->fetch_assoc()): 
            // Try to decrypt encrypted fields for display
            // Only decrypt if the field looks like encrypted data (base64 encoded)
            $is_encrypted = function($data) {
                if (empty($data)) return false;
                // Check if it's valid base64
                if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $data)) return false;
                if ((strlen($data) % 4) != 0) return false;
                // Try to base64_decode to check if it's valid base64
                $decoded = base64_decode($data, true);
                return $decoded !== false;
            };
            
            if ($is_encrypted($student['first_name'])) {
                try {
                    $student['first_name'] = Encryption::decrypt($student['first_name']);
                } catch (Exception $e) {
                    // If decryption fails, keep original
                }
            }
            if ($is_encrypted($student['last_name'])) {
                try {
                    $student['last_name'] = Encryption::decrypt($student['last_name']);
                } catch (Exception $e) {
                    // If decryption fails, keep original
                }
            }
            if ($is_encrypted($student['email'])) {
                try {
                    $student['email'] = Encryption::decrypt($student['email']);
                } catch (Exception $e) {
                    // If decryption fails, keep original
                }
            }
            if ($is_encrypted($student['birthday'])) {
                try {
                    $student['birthday'] = Encryption::decrypt($student['birthday']);
                } catch (Exception $e) {
                    // If decryption fails, keep original
                }
            }
            // Reconstruct full_name with decrypted values
            $student['full_name'] = $student['last_name'] . ', ' . $student['first_name'];
            if (!empty($student['middle_initial'])) {
                $student['full_name'] .= ' ' . $student['middle_initial'] . '.';
            }
        
        // Determine account status badge
    if ($student['account_status'] == 'inactive' || $student['account_status'] == 'suspended') {
    $account_status_badge = '<span class="badge badge-danger"><i class="fas fa-ban"></i> Inactive</span>';
    $account_status_class = 'inactive';
    } elseif ($student['must_change_password'] == 1 && $student['first_login_at'] == null) {
    $account_status_badge = '<span class="badge badge-warning"><i class="fas fa-clock"></i> Pending Activation</span>';
    $account_status_class = 'pending';
    } else {
    $account_status_badge = '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>';
    $account_status_class = 'active';
    }

    // Enrollment status badge
    $enrollment_class = match($student['status']) {
    'active' => 'badge-success',
    'inactive' => 'badge-secondary',
    'graduated' => 'badge-info',
    'transferred' => 'badge-warning',
    'suspended' => 'badge-danger',
    'pending' => 'badge-warning',
    default => 'badge-secondary'
    };
    ?>
    <tr>
    <td><strong><?= htmlspecialchars($student['student_id']) ?></strong></td>
    <td>
    <div>
    <div style="font-weight: 600;"><?= htmlspecialchars($student['full_name']) ?></div>
    <?php if ($student['first_login_at']): ?>
    <small style="color: var(--text-muted); font-size: 11px;">
    <i class="fas fa-sign-in-alt"></i> 
    First login: <?= date('M j, Y', strtotime($student['first_login_at'])) ?>
    </small>
    <?php endif; ?>
    </div>
    </td>
    <td><?= htmlspecialchars($student['email']) ?></td>
    <td><?= date('M j, Y', strtotime($student['birthday'])) ?></td>
    <td><?= $account_status_badge ?></td>
    <td>
    <span class="badge <?= $enrollment_class ?>">
    <?= ucfirst($student['status']) ?>
    </span>
    <br>
    <small style="color: var(--text-muted);">
    <?= $student['enrolled_classes'] ?> class<?= $student['enrolled_classes'] != 1 ? 'es' : '' ?>
    </small>
    </td>
    <td>
    <div class="d-flex gap-2">
    <?php if ($account_status_class == 'pending'): ?>
    <button onclick="resendStudentCredentials('<?= htmlspecialchars($student['student_id']) ?>', '<?= htmlspecialchars(addslashes($student['full_name'])) ?>', '<?= htmlspecialchars($student['email']) ?>')" 
    class="btn btn-info btn-sm">
    <i class="fas fa-envelope"></i>
    Resend Email
    </button>
    <?php endif; ?>

    <button onclick="viewStudentDetails('<?= htmlspecialchars($student['student_id']) ?>')" 
    class="btn btn-sm btn-info">
    <i class="fas fa-eye"></i>
    View
    </button>

    <button onclick="editStudent('<?= htmlspecialchars($student['student_id']) ?>', 
    '<?= htmlspecialchars(addslashes($student['first_name'])) ?>', 
    '<?= htmlspecialchars(addslashes($student['last_name'])) ?>', 
    '<?= htmlspecialchars(addslashes($student['middle_initial'] ?? '')) ?>', 
    '<?= htmlspecialchars($student['email']) ?>', 
    '<?= $student['birthday'] ?>', 
    '<?= $student['status'] ?>')" 
    class="btn btn-sm btn-warning">
    <i class="fas fa-edit"></i>
    Edit
    </button>

    <button onclick="deleteStudent('<?= htmlspecialchars($student['student_id']) ?>')" 
    class="btn btn-danger btn-sm">
    <i class="fas fa-trash"></i>
    Delete
    </button>
    </div>
    </td>
    </tr>
    <?php 
    endwhile; 
    } else {
    echo '<tr><td colspan="7" style="text-align: center; color: var(--text-muted);">No students found</td></tr>';
    }
    ?>
    </tbody>
    </table>
    </div>
    </div>
    </div>


    <!-- Subjects Management Section -->
    <div id="subjects" class="section">
    <div class="form-section">
    <div class="form-header">
    <h3>
    <i class="fas fa-plus-circle"></i>
    Add New Subject
    </h3>
    <p class="form-subtitle">Create a new course subject for the academic system</p>
    </div>
    <div class="form-body">
    <form id="addSubjectForm">

    <div class="form-grid">
    <div class="form-group">
    <label for="course_code" class="form-label">
    <i class="fas fa-code"></i>
    Course Code *
    </label>

    <input type="text" id="subject_course_code" name="course_code" class="form-control"
    placeholder="e.g., CTAPROJ1" maxlength="50" required>
    <div class="invalid-feedback" style="display: none;">
    <i class="fas fa-exclamation-circle"></i>
    Course code is required and must be unique
    </div>
    <div>
    </div>
    </div>

    <div class="form-group">
    <label for="course_title" class="form-label">
    <i class="fas fa-heading"></i>
    Course Title *
    </label>
    <input type="text" id="course_title" name="course_title" class="form-control" 
    placeholder="e.g., Capstone Project 1" required>
    <div class="invalid-feedback" style="display: none;">
    <i class="fas fa-exclamation-circle"></i>
    Course title is required
    </div>
    </div>

    <div class="form-group">
    <label for="units" class="form-label">
    <i class="fas fa-calculator"></i>
    Units *
    </label>
    <select id="units" name="units" class="form-control" required>
    <option value="">Select Units</option>
    <option value="1">1 Unit</option>
    <option value="2">2 Units</option>
    <option value="3">3 Units</option>
    <option value="4">4 Units</option>
    <option value="5">5 Units</option>
    </select>
    </div>

    <div class="form-group">
    <label for="course_desc" class="form-label">
    <i class="fas fa-align-left"></i>
    Course Description
    </label>
    <input type="text" id="course_desc" name="course_desc" class="form-control" 
    placeholder="Brief description of the course">
    </div>
    </div>
    <!-- Course Outcomes Section -->
<div class="form-section-divider"></div>

<div class="course-outcomes-section">
    <div class="section-header">
        <h4>
            <i class="fas fa-tasks"></i>
            Course Outcomes
        </h4>
        <p class="section-description">
            Define the learning outcomes that students should achieve upon completing this course
        </p>
    </div>

    <!-- Course Outcomes List -->
    <div id="courseOutcomesList" class="outcomes-list">
        <!-- Course outcomes will be dynamically added here -->
        <div class="empty-state" id="emptyOutcomesState">
            <div class="empty-state-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <p class="empty-state-text">No course outcomes yet</p>
            <p class="empty-state-subtext">Click "Add Course Outcome" to get started</p>
        </div>
    </div>

    <!-- Add Course Outcome Button -->
    <button type="button" class="btn btn-outline add-outcome-btn" id="addOutcomeBtn">
        <i class="fas fa-plus"></i>
        Add Course Outcome
    </button>
</div>
<!-- CO-SO Mapping Section -->
<div class="form-section-divider"></div>

<div class="co-so-mapping-section" id="cosoMappingSection" style="display: none;">
    <div class="section-header">
        <h4>
            <i class="fas fa-project-diagram"></i>
            CO-SO Mapping
        </h4>
        <p class="section-description">
            Map each Course Outcome to the relevant Student Outcomes (program-level outcomes)
        </p>
    </div>

    <div class="mapping-container" id="mappingContainer">
        <!-- Matrix will be generated here by JavaScript -->
        <div class="empty-state" id="emptyMappingState">
            <div class="empty-state-icon">
                <i class="fas fa-network-wired"></i>
            </div>
            <p class="empty-state-text">Add Course Outcomes first</p>
            <p class="empty-state-subtext">CO-SO mapping will appear once you add course outcomes above</p>
        </div>
    </div>
</div>


    <div class="form-actions">
    <button type="submit" class="btn btn-primary" id="addSubjectBtn">
    <span class="btn-text">
    <i class="fas fa-save"></i>
    Add Subject
    </span>
    <span class="btn-loading" style="display: none;">
    <i class="fas fa-spinner fa-spin"></i>
    Adding...
    </span>
    </button>
    </div>
    </form>
    </div>
    </div>

    <!-- Subjects List -->
    <div class="table-container">
    <div class="table-header">
    <h3>
    <i class="fas fa-book"></i>
    All Subjects
    </h3>
    </div>
    <div class="table-wrapper">
    <table class="table five-column">
    <thead>
    <tr>
    <th>Course Code</th>
    <th>Course Title</th>
    <th>Description</th>
    <th>Units</th>
    <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php 
    if ($subjects) {
    $subjects->data_seek(0);
    while($subject = $subjects->fetch_assoc()): 
    ?>
    <tr>
    <td><strong><?= htmlspecialchars($subject['course_code']) ?></strong></td>
    <td><?= htmlspecialchars($subject['course_title']) ?></td>
    <td><?= htmlspecialchars($subject['course_desc'] ?? 'No description') ?></td>
    <td>
    <span class="badge badge-primary">
    <?= $subject['units'] ?> unit<?= $subject['units'] > 1 ? 's' : '' ?>
    </span>
    </td>
    <td>
    <div class="d-flex">
    <button onclick="editSubject('<?= htmlspecialchars($subject['course_code']) ?>', '<?= htmlspecialchars($subject['course_title']) ?>', '<?= htmlspecialchars($subject['course_desc'] ?? '') ?>', '<?= $subject['units'] ?>')" class="btn btn-sm btn-outline" style="color: var(--warning-600); border-color: var(--warning-300);">
    Edit
    </button>
    <button onclick="deleteSubject('<?= htmlspecialchars($subject['course_code']) ?>')" class="btn btn-danger btn-sm">
    <i class="fas fa-trash"></i>
    Delete
    </button>
    </div>
    </td>
    </tr>
    <?php 
    endwhile; 
    } else {
    echo '<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No subjects found</td></tr>';
    }
    ?>
    </tbody>
    </table>
    </div>
    </div>
    </div>

    <!-- Faculty Management Section -->
    <div id="faculty" class="section">
    <!-- Faculty Account Creation Form -->
    <div class="form-section">
    <div class="form-header">
    <h3>
    <i class="fas fa-user-plus"></i>
    Create Faculty Account
    </h3>
    <p class="form-subtitle">Create a new faculty account and send login credentials via email</p>
    </div>

    <div class="form-body">
    <form id="createFacultyAccountForm">
    <div class="form-grid">
    <!-- Full Name -->
    <div class="form-group">
    <label for="faculty_name" class="form-label">
    <i class="fas fa-user"></i>
    Full Name *
    </label>
    <input type="text" 
    id="faculty_name" 
    name="name" 
    class="form-control" 
    placeholder="e.g., Juan Dela Cruz"
    required>
    <small class="form-text text-muted">Enter complete name (First Name Last Name)</small>
    </div>

    <!-- School Email -->
    <div class="form-group">
    <label for="faculty_email" class="form-label">
    <i class="fas fa-envelope"></i>
    School Email *
    </label>
    <input type="email" 
    id="faculty_email" 
    name="email" 
    class="form-control" 
    placeholder="e.g., juan.delacruz@nu-lipa.edu.ph"
    required>
    <small class="form-text text-muted">Credentials will be sent to this email</small>
    </div>

    <!-- Employee ID -->
    <div class="form-group">
    <label for="faculty_employee_id" class="form-label">
    <i class="fas fa-id-card"></i>
    Employee ID *
    </label>
    <input type="text" 
    id="faculty_employee_id" 
    name="employee_id" 
    class="form-control" 
    placeholder="e.g., 2024-123456"
    required>
    <small class="form-text text-muted">This will be used as the username</small>
    </div>
    </div>

    <div class="form-actions">
    <button type="submit" class="btn btn-primary" id="createFacultyBtn">
    <span class="btn-text">
    <i class="fas fa-paper-plane"></i>
    Create Account & Send Email
    </span>
    <span class="btn-loading" style="display: none;">
    <i class="fas fa-spinner fa-spin"></i>
    Creating...
    </span>
    </button>
    </div>
    </form>
    </div>
    </div>


    <div class="table-container">
    <div class="table-header">
    <h3>
    <i class="fas fa-users"></i>
    Faculty Members
    </h3>
    </div>
    <div class="table-wrapper">
    <table class="table five-column">
    <thead>
    <tr>
    <th>Name</th>
    <th>Employee ID</th>
    <th>Email</th>
    <th>Status</th>
    <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php 
    $faculty_query = "
    SELECT 
    id, 
    name, 
    employee_id, 
    email,
    must_change_password,
    first_login_at,
    account_status,
    created_at
    FROM users 
    WHERE role = 'faculty' 
    ORDER BY created_at DESC
    ";
    $faculty_result = $conn->query($faculty_query);

    if ($faculty_result && $faculty_result->num_rows > 0) {
    while($faculty = $faculty_result->fetch_assoc()): 
    // Determine status
    if ($faculty['account_status'] == 'inactive' || $faculty['account_status'] == 'locked') {
    $status_badge = '<span class="badge badge-danger"><i class="fas fa-ban"></i> Inactive</span>';
    $status_class = 'inactive';
    } elseif ($faculty['must_change_password'] == 1 && $faculty['first_login_at'] == null) {
    $status_badge = '<span class="badge badge-warning"><i class="fas fa-clock"></i> Pending Activation</span>';
    $status_class = 'pending';
    } else {
    $status_badge = '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>';
    $status_class = 'active';
    }

    $days_since_created = $faculty['created_at'] 
    ? floor((time() - strtotime($faculty['created_at'])) / (60 * 60 * 24)) 
    : 0;
    ?>
    <tr>
    <td>
    <div class="d-flex align-center">
    <div class="user-avatar" style="margin-right: var(--space-3);">
    <?= strtoupper(substr($faculty['name'], 0, 1)) ?>
    </div>
    <div>
    <div style="font-weight: 600;"><?= htmlspecialchars($faculty['name']) ?></div>
    <?php if ($status_class == 'pending' && $days_since_created > 3): ?>
    <small style="color: var(--warning-600);">
    <i class="fas fa-exclamation-triangle"></i> 
    Created <?= $days_since_created ?> days ago
    </small>
    <?php endif; ?>
    </div>
    </div>
    </td>
    <td><?= htmlspecialchars($faculty['employee_id']) ?></td>
    <td>
    <?= htmlspecialchars($faculty['email']) ?>
    <?php if ($faculty['first_login_at']): ?>
    <br><small style="color: var(--text-muted); font-size: 11px;">
    <i class="fas fa-sign-in-alt"></i> 
    First login: <?= date('M j, Y g:i A', strtotime($faculty['first_login_at'])) ?>
    </small>
    <?php endif; ?>
    </td>
    <td><?= $status_badge ?></td>
    <td>
    <div class="d-flex gap-3">
    <?php if ($status_class == 'pending'): ?>
    <button onclick="resendCredentials(<?= $faculty['id'] ?>, '<?= addslashes($faculty['name']) ?>', '<?= addslashes($faculty['email']) ?>')" 
    class="btn btn-info btn-sm">
    <i class="fas fa-envelope"></i>
    Resend Email
    </button>
    <?php endif; ?>

    <button onclick="viewFacultyClasses(<?= $faculty['id'] ?>, '<?= addslashes($faculty['name']) ?>')" 
    class="btn btn-info btn-sm">
    <i class="fas fa-eye"></i>
    View Classes
    </button>

    <button onclick="editFaculty(<?= $faculty['id'] ?>, '<?= addslashes($faculty['name']) ?>', '<?= $faculty['employee_id'] ?>', '<?= $faculty['email'] ?>')" 
    class="btn btn-warning btn-sm">
    <i class="fas fa-edit"></i>
    Edit
    </button>

    <button onclick="confirmDeleteFaculty(<?= $faculty['id'] ?>, '<?= addslashes($faculty['name']) ?>')" 
    class="btn btn-danger btn-sm">
    <i class="fas fa-trash"></i>
    Delete
    </button>
    </div>
    </td>
    </tr>
    <?php 
    endwhile; 
    } else {
    echo '<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No faculty members found</td></tr>';
    }
    ?>
    </tbody>
    </table>
    </div>
    </div>
    </div>
    <!--  Management Section -->
    <div id="sections" class="section">
    <div class="form-section">
    <div class="form-header">
    <h3>
    <i class="fas fa-plus-circle"></i>
    Section Management
    </h3>
    <p class="form-subtitle">Add and manage academic sections</p>
    </div>

    <!-- Add Section Form -->
    <div class="form-body">
    <form id="addSectionForm">

    <?php if (isset($_SESSION['csrf_token'])): ?>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php endif; ?>

    <div class="form-grid">
    <div class="form-group">
    <label for="section_code" class="form-label">
    <i class="fas fa-tag"></i>
    Section Code *
    </label>
    <input type="text" name="section_code" id="section_code" 
    class="form-control" placeholder="e.g., INF221" 
    maxlength="20" required>
    <small class="form-text text-muted">
    Use only letters, numbers, hyphens, and underscores (max 20 characters)
    </small>
    </div>
    </div>

    <div class="form-actions">
    <button type="submit" class="btn btn-primary" id="addSectionBtn">
    <span class="btn-text">
    <i class="fas fa-plus"></i>
    Add Section
    </span>
    <span class="btn-loading" style="display: none;">
    <i class="fas fa-spinner fa-spin"></i>
    Adding...
    </span>
    </button>
    </div>
    </form>
    </div>
    </div>

    <!-- Sections List -->
    <div class="table-container">
    <div class="table-header">
    <h3>
    <i class="fas fa-list"></i>
    All Sections
    </h3>
    </div>
    <div class="table-wrapper">
    <table class="table three-column" id="sectionsTable">
    <thead>
    <tr>
    <th>Section Code</th>
    <th>Date Created</th>
    <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php 
    if ($sections_list && $sections_list->num_rows > 0) {
    while($section = $sections_list->fetch_assoc()): 
    ?>
    <tr>
    <td><strong><?= htmlspecialchars($section['section_code']) ?></strong></td>
    <td><?= date('M j, Y', strtotime($section['created_at'])) ?></td>
    <td>
    <button data-action="edit-section" 
    data-section-id="<?= $section['section_id'] ?>" 
    data-section-code="<?= htmlspecialchars($section['section_code']) ?>"
    class="btn btn-warning btn-sm">
    <i class="fas fa-edit"></i>
    Edit
    </button>
    <button data-action="delete-section" 
    data-section-id="<?= $section['section_id'] ?>" 
    data-section-code="<?= htmlspecialchars($section['section_code']) ?>"
    class="btn btn-danger btn-sm">
    <i class="fas fa-trash"></i>
    Delete
    </button>
    </td>
    </tr>
    <?php 
    endwhile; 
    } else {
    echo '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No sections added yet</td></tr>';
    }
    ?>
    </tbody>
    </table>
    </div>
    </div>
    </div>


    <!-- Classes Management Section -->
    <div id="classes" class="section">

    <div class="form-section">
    <div class="form-header">
    <h3>
    <i class="fas fa-plus-circle"></i>
    Create New Class
    </h3>
    <p class="form-subtitle">Configure class details and schedule</p>
    <!-- Quick Import Section -->
    <div class="import-section" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 24px; border-radius: 16px; margin-bottom: 32px; border: 2px solid #0ea5e9; box-shadow: 0 4px 6px rgba(14, 165, 233, 0.1);">
    <div style="display: flex; align-items: center; gap: 20px;">
    <!-- CSV Icon -->
    <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #0ea5e9, #0284c7); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);">
    <i class="fas fa-file-csv" style="font-size: 32px; color: white;"></i>
    </div>

    <!-- Text Content -->
    <div style="flex: 1;">
    <h4 style="margin: 0 0 8px 0; color: #0c4a6e; font-size: 18px; font-weight: 700;">
    <i class="fas fa-upload"></i> Quick Import
    </h4>
    <p style="margin: 0; color: #0369a1; font-size: 14px; line-height: 1.5;">
    Upload a CSV file to create multiple classes at once. Save time by importing bulk class data.
    </p>
    </div>

    <!-- Import Button -->
    <button type="button" class="btn btn-primary" onclick="openImportModal()" style="flex-shrink: 0; white-space: nowrap;">
    <i class="fas fa-file-import"></i>
    Import CSV
    </button>
    </div>

    <!-- Help Text -->
    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(14, 165, 233, 0.2);">
    <small style="color: #0369a1; display: flex; align-items: center; gap: 8px;">
    <i class="fas fa-info-circle"></i>
    <span>Download the <a href="#" onclick="downloadCSVTemplate(); return false;" style="color: #0284c7; text-decoration: underline; font-weight: 600;">CSV template</a> to see the required format</span>
    </small>
    </div>
    </div>
    </div>

    <div class="form-body">
    <form id="addClassForm">
    <input type="hidden" name="action" value="add">

    <!-- Basic Information -->
    <div class="form-section-group">
    <h4 class="section-title">
    <i class="fas fa-info-circle"></i>
    Basic Information
    </h4>

    <div class="form-grid">
    <div class="form-group">
    <label for="section" class="form-label">
    <i class="fas fa-users"></i>
    Section *
    </label>
    <select id="section" name="section" class="form-control" required>
    <option value="">Choose Section</option>
    <?php 
    try {
    $check_sections = $conn->query("SHOW TABLES LIKE 'sections'");
    if ($check_sections && $check_sections->num_rows > 0) {
    $sections_query = "SELECT section_id, section_code FROM sections ORDER BY section_code";
    } else {
    $sections_query = "SELECT section_id, section_code FROM section ORDER BY section_code";
    }
    $sections_result = $conn->query($sections_query);
    if ($sections_result) {
    while($section = $sections_result->fetch_assoc()) {
    echo "<option value='{$section['section_code']}'>{$section['section_code']}</option>";
    }
    }
    } catch (Exception $e) {
    echo "<option value=''>Error loading sections</option>";
    }
    ?>
    </select>
    <div class="invalid-feedback" style="display: none;">
    <i class="fas fa-exclamation-circle"></i>
    Please select a section
    </div>
    </div>

    <div class="form-group">
    <label for="academic_year" class="form-label">
    <i class="fas fa-calendar-alt"></i>
    Academic Year *
    </label>
    <select id="academic_year" name="academic_year" class="form-control" required>  
    <option value="">Select Academic Year</option>
    <option value="24">2024-2025</option>
    <option value="25">2025-2026</option>
    <option value="26">2026-2027</option>
    </select>
    </div>

    <div class="form-group">
    <label for="term" class="form-label">
    <i class="fas fa-bookmark"></i>
    Term *
    </label>
    <select id="term" name="term" class="form-control" required>
    <option value="">Select Term</option>
    <option value="T1">1st Term</option>
    <option value="T2">2nd Term</option>
    <option value="T3">3rd Term</option>
    </select>
    </div>

    <div class="form-group">
    <label for="subject_course_code" class="form-label">
    <i class="fas fa-book"></i>
    Course *
    </label>
    <select id="course_code" name="course_code" class="form-control" required>
    <option value="">Select Course</option>
    <?php 
    if ($subjects) {
    $subjects->data_seek(0);
    while($subject = $subjects->fetch_assoc()) {
    echo "<option value='{$subject['course_code']}'>{$subject['course_code']} - {$subject['course_title']}</option>";
    }
    }
    ?>
    </select>
    </div>
    </div>
    </div>

    <!-- Schedule Configuration -->
    <div class="form-section-group">
    <h4 class="section-title">
    <i class="fas fa-clock"></i>
    Schedule Configuration
    </h4>

    <div class="form-group">
    <h5 class="form-label">
    <i class="fas fa-calendar-week"></i>
    Class Schedule *
    </h5>
    <div class="schedule-container" id="scheduleContainer">
    <div class="schedule-header">
    <h4><i class="fas fa-calendar-week"></i> Weekly Schedule</h4>
    <button type="button" id="addScheduleBtn" class="btn-add-schedule">
    <i class="fas fa-plus"></i> Add Schedule
    </button>
    </div>

    <div class="schedule-list" id="scheduleList">
    <div class="schedule-card">
    <div class="schedule-card-header">
    <span class="schedule-number">1</span>
    <button type="button" class="btn-remove-schedule" style="display: none;" onclick="removeSchedule(this)">
    <i class="fas fa-times"></i>
    </button>
    </div>
    <div class="schedule-card-body">
    <div class="schedule-row">
    <div class="form-group">
    <label for="day_1" class="form-label"><i class="fas fa-calendar-day"></i> Day</label>
    <select id="day_1" name="day[]" class="form-control" required>
    <option value="">Select Day</option>
    <option value="Monday">Monday</option>
    <option value="Tuesday">Tuesday</option>
    <option value="Wednesday">Wednesday</option>
    <option value="Thursday">Thursday</option>
    <option value="Friday">Friday</option>
    <option value="Saturday">Saturday</option>
    <option value="Sunday">Sunday</option>
    </select>
    </div>
    <div class="form-group">
    <h5 class="form-label"><i class="fas fa-clock"></i> Time Range</h5>
    <div class="time-range-container">
    <!-- Start Time -->
    <div>
    <div class="time-label">From</div>
    <div class="time-input-group">
    <input type="text" id="start_hour_1" class="time-input hour-input" placeholder="08" maxlength="2" data-type="hour" aria-label="Start hour">
    <span class="time-colon">:</span>
    <input type="text" id="start_minute_1" class="time-input minute-input" placeholder="00" maxlength="2" data-type="minute" aria-label="Start minute">
    <div class="ampm-selector">
    <button type="button" class="ampm-option active" data-ampm="AM">AM</button>
    <button type="button" class="ampm-option" data-ampm="PM">PM</button>
    </div>
    </div>
    </div>

    <div class="time-separator">
    <i class="fas fa-arrow-down"></i>
    </div>

    <!-- End Time -->
    <div>
    <div class="time-label">To</div>
    <div class="time-input-group">
    <input type="text" id="end_hour_1" class="time-input hour-input" placeholder="09" maxlength="2" data-type="hour" aria-label="End hour">
    <span class="time-colon">:</span>
    <input type="text" id="end_minute_1" class="time-input minute-input" placeholder="30" maxlength="2" data-type="minute" aria-label="End minute">
    <div class="ampm-selector">
    <button type="button" class="ampm-option active" data-ampm="AM">AM</button>
    <button type="button" class="ampm-option" data-ampm="PM">PM</button>
    </div>
    </div>
    </div>

    <!-- Time Display -->
    <div class="time-display">
    <strong>Time Range:</strong> <span class="formatted-time">Not set</span>
    </div>

    <!-- Hidden input for form submission -->
    <input type="hidden" id="time_value_1" name="time[]" class="time-value">
    </div>
    </div>
    </div>
    </div>
    </div>
    </div>

    <div class="schedule-help">
    <i class="fas fa-info-circle"></i>
    Add multiple schedules if this class meets on different days/times
    </div>
    </div>
    </div>
    </div>

    <!-- Location & Faculty -->
    <div class="form-section-group">
    <h4 class="section-title">
    <i class="fas fa-map-marker-alt"></i>
    Location & Faculty
    </h4>

    <div class="form-grid">
    <div class="form-group">
    <label for="room" class="form-label">
    <i class="fas fa-door-open"></i>
    Room/Location *
    </label>
    <input type="text" id="room" name="room" class="form-control" 
    placeholder="e.g., Room 101, Computer Lab, Online" required>   
    <small class="form-text text-muted">
    Enter the room number or location where the class will be held
    </small>
    </div>

    <div class="form-group">
    <label for="faculty_id" class="form-label">
    <i class="fas fa-chalkboard-teacher"></i>
    Faculty Member *
    </label>
    <select id="faculty_id" name="faculty_id" class="form-control" required> 
    <option value="">Select Faculty Member</option>
    <?php 
    $faculty_query = "SELECT id, name FROM users WHERE role = 'faculty' ORDER BY name";
    $faculty_result = $conn->query($faculty_query);
    if ($faculty_result) {
    while($faculty = $faculty_result->fetch_assoc()) {
    echo "<option value='{$faculty['id']}'>{$faculty['name']}</option>";
    }
    }
    ?>
    </select>
    </div>
    </div>
    </div>

    <!-- Submit Button -->
    <div class="form-actions">
    <button type="submit" class="btn btn-primary btn-lg" id="addClassBtn">
    <span class="btn-text">
    <i class="fas fa-save"></i>
    Create Class
    </span>
    <span class="btn-loading" style="display: none;">
    <i class="fas fa-spinner fa-spin"></i>
    Creating...
    </span>
    </button>
    </div>
    </form>
    </div>
    </div>

    <!-- Classes List -->
    <div class="table-container">
    <div class="table-header">
    <h3>
    <i class="fas fa-chalkboard"></i>
    All Classes
    </h3>
    </div>
    <div class="table-wrapper">
    <table class="table">
    <thead>
    <tr>
    <th>Class ID</th>
    <th>Section</th>
    <th>Course</th>
    <th>Schedule</th>
    <th>Room</th>
    <th>Faculty</th>
    <th>Term</th>
    <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php 
    try {
    $classes_query = "
    SELECT 
    MIN(c.class_id) as class_id,
    c.section,
    c.academic_year,
    c.term,
    c.course_code,
    s.course_title,
    GROUP_CONCAT(
    CONCAT(c.day, ' ', c.time) 
    ORDER BY FIELD(c.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), c.time ASC
    SEPARATOR '<br>'
    ) as schedule_display,
    GROUP_CONCAT(
    CONCAT(c.day, '|', c.time, '|', c.class_id) 
    ORDER BY FIELD(c.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), c.time ASC
    SEPARATOR '||'
    ) as schedule_data,
    c.room,
    c.faculty_id,
    u.name as faculty_name,
    COUNT(*) as schedule_count
    FROM class c 
    LEFT JOIN subjects s ON c.course_code = s.course_code 
    LEFT JOIN users u ON c.faculty_id = u.id 
    GROUP BY c.section, c.academic_year, c.term, c.course_code, c.faculty_id, c.room
    ORDER BY c.academic_year DESC, c.term, c.section, c.course_code
    ";
    $classes_result = $conn->query($classes_query);

    if ($classes_result && $classes_result->num_rows > 0) {
    while($class = $classes_result->fetch_assoc()): 
    ?>
    <tr>
    <td><strong><?= htmlspecialchars($class['class_id']) ?></strong></td>
    <td><span class="badge badge-primary"><?= htmlspecialchars($class['section']) ?></span></td>
    <td>
    <div>
    <strong><?= htmlspecialchars($class['course_code']) ?></strong><br>
    <small style="color: var(--text-muted);"><?= htmlspecialchars($class['course_title'] ?? 'N/A') ?></small>
    </div>
    </td>
    <td>
    <div class="schedule-display">
    <?= $class['schedule_display'] ?>
    <?php if ($class['schedule_count'] > 1): ?>
    <br><small class="text-muted">(<?= $class['schedule_count'] ?> schedules)</small>
    <?php endif; ?>
    </div>
    </td>
    <td><span class="badge badge-info"><?= htmlspecialchars($class['room']) ?></span></td>
    <td><?= htmlspecialchars($class['faculty_name'] ?? 'Unassigned') ?></td>
    <td>
    <div>
    <small style="color: var(--text-muted);"><?= htmlspecialchars($class['academic_year']) ?></small><br>
    <span class="badge badge-success"><?= htmlspecialchars($class['term']) ?></span>
    </div>
    </td>
    <td>
    <div class="d-flex">
    <button onclick="viewClassSchedules('<?= addslashes($class['section']) ?>', '<?= addslashes($class['course_code']) ?>', '<?= addslashes($class['academic_year']) ?>', '<?= addslashes($class['term']) ?>')" class="btn btn-info btn-sm">
    <i class="fas fa-eye"></i>
    View (<?= $class['schedule_count'] ?>)
    </button>
    <button onclick="editClassGroup('<?= addslashes($class['section']) ?>', '<?= addslashes($class['academic_year']) ?>', '<?= addslashes($class['term']) ?>', '<?= addslashes($class['course_code']) ?>', <?= $class['faculty_id'] ?? 'null' ?>, '<?= addslashes($class['room']) ?>', '<?= addslashes($class['schedule_data']) ?>')" class="btn btn-warning btn-sm">
    <i class="fas fa-edit"></i>
    Edit
    </button>
    <button onclick="confirmDeleteClassGroup('<?= addslashes($class['section']) ?>', '<?= addslashes($class['academic_year']) ?>', '<?= addslashes($class['term']) ?>', '<?= addslashes($class['course_code']) ?>', <?= $class['faculty_id'] ?? 'null' ?>, '<?= addslashes($class['room']) ?>')" class="btn btn-danger btn-sm">
    <i class="fas fa-trash"></i>
    Delete
    </button>
    </div>
    </td>
    </tr>
    <?php 
    endwhile; 

    }
    } catch (Exception $e) {
    echo '<tr><td colspan="8" style="text-align: center; color: var(--error); padding: var(--space-2xl);">
    <div><i class="fas fa-exclamation-triangle" style="font-size: var(--font-size-2xl); margin-bottom: var(--space-md);"></i></div>
    <div>Error loading classes</div>
    <small>Please check your database connection</small>
    </td></tr>';
    }
    ?>
    </tbody>
    </table>
    </div>
    </div>
    </div>
<!-- Reports Section -->
<div id="reports" class="section">
    <!-- KPI Overview -->
    <div class="analytics-header" style="margin-bottom: 30px;">
        <h2 style="color: var(--primary); margin-bottom: 8px;">
            <i class="fas fa-chart-line"></i> Analytics Dashboard
        </h2>
        <p style="color: var(--text-muted);">Real-time insights and comprehensive system analytics</p>
    </div>

    <!-- 1. KEY PERFORMANCE INDICATORS -->
    <div class="form-section">
        <div class="form-header">
            <h3>
                <i class="fas fa-tachometer-alt"></i>
                Key Performance Indicators
            </h3>
            <p class="form-subtitle">Monitor critical system metrics</p>
        </div>
        <div class="form-body">
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                <!-- Student-Faculty Ratio -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="studentFacultyRatio">
                                <?php 
                                $ratio = $total_faculty > 0 ? round($total_students / $total_faculty) : 0;
                                echo $ratio . ':1';
                                ?>
                            </div>
                            <div class="stat-label">Student-Faculty Ratio</div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <!-- Average Class Size -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="avgClassSize">
                                <?php 
                                try {
                                    $avg_query = "
                                        SELECT AVG(enrollment_count) as avg_size
                                        FROM (
                                            SELECT COUNT(*) as enrollment_count
                                            FROM class_enrollments
                                            WHERE status = 'enrolled'
                                            GROUP BY class_id
                                        ) as class_sizes
                                    ";
                                    $avg_result = $conn->query($avg_query);
                                    $avg_size = $avg_result ? round($avg_result->fetch_assoc()['avg_size']) : 0;
                                    echo $avg_size;
                                } catch (Exception $e) {
                                    echo '0';
                                }
                                ?>
                            </div>
                            <div class="stat-label">Avg Class Size</div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-user-friends"></i>
                        </div>
                    </div>
                </div>

                <!-- Active Faculty Percentage -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="activeFacultyPercent">
                                <?php 
                                try {
                                    $active_faculty_query = "
                                        SELECT COUNT(DISTINCT faculty_id) as active_count
                                        FROM class
                                    ";
                                    $active_fac_result = $conn->query($active_faculty_query);
                                    $active_fac_count = $active_fac_result ? $active_fac_result->fetch_assoc()['active_count'] : 0;
                                    $percentage = $total_faculty > 0 ? round(($active_fac_count / $total_faculty) * 100) : 0;
                                    echo $percentage . '%';
                                } catch (Exception $e) {
                                    echo '0%';
                                }
                                ?>
                            </div>
                            <div class="stat-label">Active Faculty</div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                </div>

                <!-- Avg Sections per Subject -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number"><?= number_format(($active_sections / max($total_subjects, 1)), 1) ?></div>
                            <div class="stat-label">Avg Sections per Subject</div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-calculator"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. DATA VISUALIZATIONS -->
    <div class="form-section" style="margin-top: 30px;">
        <div class="form-header">
            <h3>
                <i class="fas fa-chart-bar"></i>
                Data Visualizations
            </h3>
            <p class="form-subtitle">Visual insights into system data</p>
        </div>
        <div class="form-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
                <!-- Chart 1: Faculty Workload -->
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h4 style="margin-bottom: 16px; color: var(--primary);">
                        <i class="fas fa-chart-bar"></i> Faculty Workload
                    </h4>
                    <canvas id="facultyWorkloadChart" style="max-height: 300px;"></canvas>
                </div>

                <!-- Chart 2: Enrollment by Term -->
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h4 style="margin-bottom: 16px; color: var(--primary);">
                        <i class="fas fa-chart-line"></i> Enrollment Trends
                    </h4>
                    <canvas id="enrollmentTrendsChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. REPORT GENERATION (Keep Original) -->
    <div class="form-section" style="margin-top: 30px;">
        <div class="form-header">
            <h3>
                <i class="fas fa-file-download"></i>
                Generate Reports
            </h3>
            <p class="form-subtitle">Export comprehensive system reports</p>
        </div>
        <div class="form-body">
            <!-- Report Generation -->
            <div class="form-grid">
                <div class="form-group">
                    <label for="reportType" class="form-label">
                        <i class="fas fa-file-alt"></i>
                        Report Type
                    </label>
                    <select id="reportType" class="form-control">
                        <option value="">Select Report Type</option>
                        <option value="faculty">Faculty Assignment Report</option>
                        <option value="subjects">Subject Utilization Report</option>
                        <option value="sections">Section Overview Report</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="reportPeriod" class="form-label">
                        <i class="fas fa-calendar-range"></i>
                        Report Period
                    </label>
                    <select id="reportPeriod" class="form-control">
                        <option value="">Select Period</option>
                        <option value="current">Current Term</option>
                        <option value="2024-2025">Academic Year 2024-2025</option>
                        <option value="2025-2026">Academic Year 2025-2026</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" onclick="generateReport()">
                    <i class="fas fa-download"></i>
                    Generate Report
                </button>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="admin/assets/jss/bridge.js"></script>
    <script src="admin/assets/jss/sidebar.js"></script>
    <script src="admin/assets/jss/time-picker.js"></script>
    <script src="admin/assets/jss/utils.js"></script>
    <script src="admin/assets/jss/subjects.js"></script>

    <script>

    // Test if form exists
    document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, checking form...');

    const form = document.getElementById('addStudentForm');
    if (form) {
    console.log(' Form found');

    // Test basic form data collection
    form.addEventListener('submit', function(e) {
    console.log('=== FORM SUBMIT TRIGGERED ===');
    console.log('Event:', e);

    const formData = new FormData(form);
    console.log('Form data collected:');
    for (let [key, value] of formData.entries()) {
    console.log(`  ${key}: ${value}`);
    }

    // Now prevent default AFTER logging
    e.preventDefault();
    e.stopPropagation();

    // Test simple AJAX call
    console.log('Testing AJAX call...');

    formData.append('action', 'add');

    fetch('ajax/process_student.php', {
    method: 'POST',
    body: formData,
    headers: {
    'X-Requested-With': 'XMLHttpRequest'
    }
    })
    .then(response => {
    console.log('Response received:', response);
    console.log('Response status:', response.status);
    return response.text();
    })
    .then(text => {
    console.log('Raw response text:', text);
    try {
    const data = JSON.parse(text);
    console.log('Parsed JSON:', data);

    if (data.success) {
    alert('SUCCESS: ' + data.message);
    } else {
    alert('ERROR: ' + data.message);
    }
    } catch (e) {
    console.error('JSON parse error:', e);
    alert('Response is not valid JSON: ' + text);
    }
    })
    .catch(error => {
    console.error('Fetch error:', error);
    alert('Network error: ' + error.message);
    });
    });

    } else {
    console.log(' Form NOT found');
    }

    // Test if StudentManager exists
    setTimeout(() => {
    if (window.studentManager) {
    console.log(' StudentManager initialized');
    } else {
    console.log(' StudentManager NOT initialized');
    }
    }, 1000);
    });

    // Test AJAX files existence
    fetch('ajax/check_student_id.php?id=2024-123456')
    .then(response => {
    if (response.ok) {
    console.log(' check_student_id.php exists');
    return response.json();
    } else {
    console.log(' check_student_id.php missing or error:', response.status);
    }
    })
    .then(data => console.log('check_student_id.php response:', data))
    .catch(e => console.log(' check_student_id.php error:', e));

    fetch('ajax/check_student_email.php?email=test@test.com')
    .then(response => {
    if (response.ok) {
    console.log(' check_student_email.php exists');
    return response.json();
    } else {
    console.log(' check_student_email.php missing or error:', response.status);
    }
    })
    .then(data => console.log('check_student_email.php response:', data))
    .catch(e => console.log(' check_student_email.php error:', e));
    </script>
    <!-- END TEST SCRIPT -->

    <script src="admin/assets/jss/students.js"></script>
    <script src="admin/assets/jss/faculty.js"></script>
    <script src="admin/assets/jss/sections.js"></script> 
    <script src="admin/assets/jss/main.js"></script>
    <script src="admin/assets/jss/classes.js"></script>
    <script src="admin/assets/jss/reports.js"></script>
<!-- Student Bulk Import Scripts -->
<script>
// Open student import modal
function openStudentImportModal() {
    const modal = document.getElementById('studentImportModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
    }
}

// Close student import modal
function closeStudentImportModal() {
    const modal = document.getElementById('studentImportModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        const fileInput = document.getElementById('studentCsvFileInput');
        if (fileInput) {
            fileInput.value = '';
        }
    }
}

// Process CSV import
function processStudentCSVImport() {
    const fileInput = document.getElementById('studentCsvFileInput');
    const file = fileInput.files[0];
    
    if (!file) {
        Toast.fire({
            icon: 'error',
            title: 'Please select a file'
        });
        return;
    }
    
    const validExtensions = ['.xlsx', '.xls'];
    const fileExtension = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();

    if (!validExtensions.includes(fileExtension)) {
        Toast.fire({
            icon: 'error',
            title: 'Invalid file type. Please upload an Excel file (.xlsx or .xls)'
        });
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        Toast.fire({
            icon: 'error',
            title: 'File too large. Maximum size is 5MB.'
        });
        return;
    }
    
    
    // Show loading with better message
    Swal.fire({
    title: 'Processing Excel...',
    html: `
    <div>
        <p>Please wait while we import your students.</p>
        <p style="color: #666; font-size: 14px; margin-top: 10px;">
            <i class="fas fa-info-circle"></i> 
            This may take 1-2 minutes for large files...
        </p>
    </div>
    `,
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
    Swal.showLoading();
    }
    });
    
    // Create FormData
    const formData = new FormData();
    formData.append('csv_file', file);
    
    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }
    
    // Send to server
    fetch('ajax/process_bulk_student_import.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error('Server returned ' + response.status);
        }
        return response.text();
    })
    .then(text => {
        console.log('Raw response:', text);
        try {
            const data = JSON.parse(text);
            console.log('Parsed data:', data);
            
            // Close loading modal
            Swal.close();
            
            if (data.success) {
                // Close the upload modal
                closeStudentImportModal();
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Students Imported Successfully!',
                    html: `
                        <div style="padding: 20px;">
                            ${data.imported > 0 ? `
                                <div style="background: #ecfdf5; border: 2px solid #10b981; border-radius: 12px; padding: 20px; margin-bottom: 16px; text-align: center;">
                                    <div style="font-size: 56px; color: #10b981; margin-bottom: 12px;">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <p style="font-size: 24px; font-weight: 700; color: #065f46; margin: 0;">
                                        ${data.imported} Student${data.imported > 1 ? 's' : ''} Created
                                    </p>
                                    <p style="font-size: 14px; color: #047857; margin-top: 8px;">
                                        Activation emails sent successfully
                                    </p>
                                </div>
                            ` : ''}
                            
                            ${data.skipped > 0 ? `
                                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="font-size: 32px; color: #f59e0b;">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div style="text-align: left;">
                                            <p style="font-size: 16px; font-weight: 700; color: #92400e; margin: 0 0 4px 0;">
                                                ${data.skipped} Row${data.skipped > 1 ? 's' : ''} Failed
                                            </p>
                                            <p style="font-size: 13px; color: #78350f; margin: 0;">
                                                Validation errors or duplicates detected
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                            
                            ${data.errors && data.errors.length > 0 ? `
                                <details style="background: #fee2e2; border-left: 4px solid #dc2626; border-radius: 8px; padding: 16px; cursor: pointer;">
                                    <summary style="font-size: 16px; font-weight: 700; color: #991b1b; cursor: pointer; list-style: none; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <span>Error Details (${data.errors.length})</span>
                                        <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 12px;"></i>
                                    </summary>
                                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #fecaca;">
                                        <ul style="margin: 0; padding-left: 24px; text-align: left; max-height: 200px; overflow-y: auto;">
                                            ${data.errors.slice(0, 20).map(err => `
                                                <li style="font-size: 13px; color: #7f1d1d; line-height: 1.8; margin-bottom: 6px;">
                                                    ${err}
                                                </li>
                                            `).join('')}
                                            ${data.errors.length > 20 ? `
                                                <li style="font-size: 13px; font-style: italic; color: #991b1b; margin-top: 8px;">
                                                    <i class="fas fa-ellipsis-h"></i> and ${data.errors.length - 20} more error${data.errors.length - 20 > 1 ? 's' : ''}
                                                </li>
                                            ` : ''}
                                        </ul>
                                    </div>
                                </details>
                            ` : ''}
                        </div>
                    `,
                    confirmButtonText: '<i class="fas fa-check"></i> OK',
                    confirmButtonColor: '#003082',
                    width: '600px',
                    padding: '2rem'
                }).then(() => {
                // Save current section and reload
                sessionStorage.setItem('activeSection', 'students');
                location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Import Failed',
                    text: data.message || 'An error occurred during import',
                    confirmButtonColor: '#dc2626'
                });
            }
        } catch (e) {
            console.error('JSON parse error:', e);
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Invalid response from server: ' + text.substring(0, 100),
                confirmButtonColor: '#dc2626'
            });
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: error.message,
            confirmButtonColor: '#dc2626'
        });
    });
}

// Close modal on backdrop click
document.addEventListener('click', function(e) {
    const modal = document.getElementById('studentImportModal');
    if (e.target === modal) {
        closeStudentImportModal();
    }
});
</script>
    <!-- Toast Configuration -->
    <script>
    const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
    toast.addEventListener('mouseenter', Swal.stopTimer)
    toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
    });

    window.Toast = Toast;
    </script>
    <!-- CSV Import Functions -->
    <script>
    // Open import modal
    function openImportModal() {
    const modal = document.getElementById('importModal');
    modal.style.display = 'flex';
    modal.classList.add('show');
}

function closeImportModal() {
    const modal = document.getElementById('importModal');
    modal.style.display = 'none';
    modal.classList.remove('show');
    document.getElementById('csvFileInput').value = '';
}

    // Download CSV template
function downloadCSVTemplate() {
    // Show loading toast
    Toast.fire({
        icon: 'info',
        title: 'Generating Excel template...'
    });
    
    // Direct download - no fetch
    window.location.href = 'ajax/generate_excel.php';
    
    // Show success after short delay
    setTimeout(() => {
        Toast.fire({
            icon: 'success',
            title: 'Excel Template Downloaded!',
            html: '<strong>NU_Class_Import_Template.xlsx</strong><br>Open with Microsoft Excel'
        });
    }, 1000);
}
    // Process CSV import
function processCSVImport() {
    const fileInput = document.getElementById('csvFileInput');
    const file = fileInput.files[0];
    
    if (!file) {
        Toast.fire({
            icon: 'error',
            title: 'Please select a CSV file'
        });
        return;
    }
    
   const validExtensions = ['.xlsx', '.xls'];
const fileExtension = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();

if (!validExtensions.includes(fileExtension)) {
    Toast.fire({
        icon: 'error',
        title: 'Invalid file type. Please upload an Excel file (.xlsx or .xls)'
    });
    return;
}
    
    if (file.size > 5 * 1024 * 1024) {
        Toast.fire({
            icon: 'error',
            title: 'File too large. Maximum size is 5MB.'
        });
        return;
    }
    
    // Show loading
    Swal.fire({
        title: 'Processing CSV...',
        html: 'Please wait while we import your classes.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Create FormData
    const formData = new FormData();
    formData.append('csv_file', file);
    formData.append('action', 'import_csv');
    
    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }
    
    // Send to server
    fetch('ajax/import_classes.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
.then(data => {
    Swal.close();
    
    if (data.success) {
        // Close the modal first
        closeImportModal();
        
        // Show success message
        Swal.fire({
            icon: 'success',
            title: 'Classes Imported Successfully!',
            html: `
    <div style="padding: 20px;">
        ${data.imported > 0 ? `
            <div style="background: #ecfdf5; border: 2px solid #10b981; border-radius: 12px; padding: 20px; margin-bottom: 16px; text-align: center;">
                <div style="font-size: 56px; color: #10b981; margin-bottom: 12px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <p style="font-size: 24px; font-weight: 700; color: #065f46; margin: 0;">
                    ${data.imported} Class${data.imported > 1 ? 'es' : ''} Imported
                </p>
            </div>
        ` : ''}
        
        ${data.skipped > 0 ? `
            <div style="background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="font-size: 32px; color: #f59e0b;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div style="text-align: left;">
                        <p style="font-size: 16px; font-weight: 700; color: #92400e; margin: 0 0 4px 0;">
                            ${data.skipped} Row${data.skipped > 1 ? 's' : ''} Skipped
                        </p>
                        <p style="font-size: 13px; color: #78350f; margin: 0;">
                            Duplicates or validation errors detected
                        </p>
                    </div>
                </div>
            </div>
        ` : ''}
        
        ${data.errors && data.errors.length > 0 ? `
            <details style="background: #fee2e2; border-left: 4px solid #dc2626; border-radius: 8px; padding: 16px; cursor: pointer;" open>
                <summary style="font-size: 16px; font-weight: 700; color: #991b1b; cursor: pointer; list-style: none; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Error Details (${data.errors.length})</span>
                    <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 12px;"></i>
                </summary>
                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #fecaca;">
                    <ul style="margin: 0; padding-left: 24px; text-align: left;">
                        ${data.errors.slice(0, 10).map(err => `
                            <li style="font-size: 13px; color: #7f1d1d; line-height: 1.8; margin-bottom: 6px;">
                                ${err}
                            </li>
                        `).join('')}
                        ${data.errors.length > 10 ? `
                            <li style="font-size: 13px; font-style: italic; color: #991b1b; margin-top: 8px;">
                                <i class="fas fa-ellipsis-h"></i> and ${data.errors.length - 10} more error${data.errors.length - 10 > 1 ? 's' : ''}
                            </li>
                        ` : ''}
                    </ul>
                </div>
            </details>
        ` : ''}
        
        ${data.imported === 0 && data.skipped > 0 ? `
            <div style="background: #fee2e2; border: 2px solid #dc2626; border-radius: 12px; padding: 20px; text-align: center; margin-top: 16px;">
                <div style="font-size: 48px; color: #dc2626; margin-bottom: 12px;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <p style="font-size: 18px; font-weight: 700; color: #991b1b; margin: 0;">
                    No Classes Imported
                </p>
                <p style="font-size: 14px; color: #7f1d1d; margin: 8px 0 0 0;">
                    All rows were skipped due to errors or duplicates
                </p>
            </div>
        ` : ''}
    </div>
`,
            confirmButtonText: '<i class="fas fa-check"></i> OK',
            confirmButtonColor: '#003082',
            width: '600px',
            padding: '2rem'
        }).then(() => {
    // Save current section before reload
    sessionStorage.setItem('activeSection', 'classes');
    location.reload();
});
    } else {
        Swal.fire({
            icon: 'error',
            title: 'Import Failed',
            text: data.message || 'An error occurred during import',
            confirmButtonColor: '#dc2626'
        });
    }
})
    .catch(error => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Network error: ' + error.message,
            confirmButtonColor: '#dc2626'
        });
    });
}

// Close modal on backdrop click
document.addEventListener('click', function(e) {
    const modal = document.getElementById('importModal');
    if (e.target === modal) {
        closeImportModal();
    }
});
// Restore active section after page reload
document.addEventListener('DOMContentLoaded', function() {
    const savedSection = sessionStorage.getItem('activeSection');
    if (savedSection) {
        sessionStorage.removeItem('activeSection');
        setTimeout(() => {
            showSection(savedSection);
        }, 100);
    }
});
    </script>
    <!-- Import CSV Modal -->
    <div id="importModal" class="modal-backdrop" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
    <div class="modal-header">
    <h3><i class="fas fa-file-upload"></i> Import Classes from CSV</h3>
    <button class="modal-close" onclick="closeImportModal()">&times;</button>
    </div>
    <div class="modal-body">
    <div class="form-group">
    <label class="form-label">
    <i class="fas fa-file-csv"></i> Select CSV File *
    </label>
   <input type="file" id="csvFileInput" accept=".xlsx,.xls" class="form-control">
<small class="form-text text-muted">
    Maximum file size: 5MB. Only Excel files (.xlsx, .xls) are allowed.
</small>
    </div>

    <div class="alert alert-info" style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 12px; margin-top: 16px;">
    <strong><i class="fas fa-info-circle"></i> CSV Format:</strong>
    <ul style="margin: 8px 0 0 20px; font-size: 13px;">
    <li>section, academic_year, term, course_code, day, time, room, faculty_id</li>
    <li>Example: INF221, 24, T1, CTAPROJ1, Monday, 08:00 AM - 10:00 AM, Room 101, 1</li>
    </ul>
    </div>
    </div>
    <div class="modal-footer-actions">
    <button type="button" class="btn btn-secondary btn-flex" onclick="closeImportModal()">
    <i class="fas fa-times"></i> Cancel
    </button>
    <button type="button" class="btn btn-primary btn-flex" onclick="processCSVImport()">
    <i class="fas fa-upload"></i> Upload & Import
    </button>
    </div>
    </div>
    </div>
    <!-- Student Import Modal -->
<div id="studentImportModal" class="modal-backdrop" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-file-upload"></i> Import Students from CSV</h3>
            <button class="modal-close" onclick="closeStudentImportModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-file-csv"></i> Select CSV File *
                </label>
                <input type="file" id="studentCsvFileInput" accept=".xlsx,.xls" class="form-control">
                <small class="form-text text-muted">
                Maximum file size: 5MB. Only Excel files (.xlsx, .xls) are allowed.
                </small>
            </div>

            <div class="alert alert-info" style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 12px; margin-top: 16px;">
                <strong><i class="fas fa-info-circle"></i> CSV Format:</strong>
                <ul style="margin: 8px 0 0 20px; font-size: 13px;">
                    <li>Headers: student_id, last_name, first_name, middle_initial, email, birthday, status</li>
                    <li>Example: 2024-123456, Dela Cruz, Juan, A, juan@email.com, 2004-01-15, active</li>
                    <li>Birthday format: YYYY-MM-DD</li>
                </ul>
            </div>
        </div>
        <div class="modal-footer-actions">
            <button type="button" class="btn btn-secondary btn-flex" onclick="closeStudentImportModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary btn-flex" onclick="processStudentCSVImport()">
                <i class="fas fa-upload"></i> Upload & Import
            </button>
        </div>
    </div>
</div>
<script>
/**
 * Toggle Sidebar Function
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const nuHeader = document.querySelector('.nu-header');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (!sidebar) return;
    
    // Toggle collapsed state
    sidebar.classList.toggle('collapsed');
    
    // Toggle overlay on mobile
    if (overlay) {
        overlay.classList.toggle('active');
    }
    
    // On desktop, adjust header position when sidebar toggles
    if (window.innerWidth > 768 && nuHeader) {
        if (sidebar.classList.contains('collapsed')) {
            nuHeader.classList.add('expanded');
        } else {
            nuHeader.classList.remove('expanded');
        }
    }
    
    console.log('Sidebar toggled:', sidebar.classList.contains('collapsed') ? 'Hidden' : 'Visible');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.querySelector('.sidebar-overlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && !sidebar.classList.contains('collapsed')) {
                toggleSidebar();
            }
        });
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Dashboard loaded');
    
    const sidebar = document.getElementById('sidebar');
    const nuHeader = document.querySelector('.nu-header');
    const overlay = document.querySelector('.sidebar-overlay');
    
    // On desktop, sidebar should be visible by default
    if (window.innerWidth > 768) {
        if (sidebar) {
            sidebar.classList.remove('collapsed');
        }
        if (nuHeader) {
            nuHeader.classList.remove('expanded');
        }
        if (overlay) {
            overlay.classList.remove('active');
        }
    } else {
        // On mobile, sidebar should be hidden by default
        if (sidebar) {
            sidebar.classList.add('collapsed');
        }
        if (overlay) {
            overlay.classList.remove('active');
        }
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            // Desktop: Show sidebar by default
            if (sidebar) {
                sidebar.classList.remove('collapsed');
            }
            if (nuHeader) {
                nuHeader.classList.remove('expanded');
            }
            if (overlay) {
                overlay.classList.remove('active');
            }
        } else {
            // Mobile: Hide sidebar by default
            if (sidebar) {
                sidebar.classList.add('collapsed');
            }
            if (overlay) {
                overlay.classList.remove('active');
            }
        }
    });
});
</script>
<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="admin/assets/jss/analytics.js"></script>

<!-- Encryption/Decryption Functions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check encryption status on page load
    checkEncryptionStatus();
    
    // Dashboard buttons
    const decryptBtn = document.getElementById('decryptBtn');
    const encryptBtn = document.getElementById('encryptBtn');
    
    if (decryptBtn) {
        decryptBtn.addEventListener('click', function() {
            showConfirmation('decrypt_all');
        });
    }
    
    if (encryptBtn) {
        encryptBtn.addEventListener('click', function() {
            showConfirmation('encrypt_all');
        });
    }
    
    // Students section buttons
    const decryptBtnStudents = document.getElementById('decryptBtnStudents');
    const encryptBtnStudents = document.getElementById('encryptBtnStudents');
    
    if (decryptBtnStudents) {
        decryptBtnStudents.addEventListener('click', function() {
            showConfirmation('decrypt_all');
        });
    }
    
    if (encryptBtnStudents) {
        encryptBtnStudents.addEventListener('click', function() {
            showConfirmation('encrypt_all');
        });
    }
    
    // Table encryption buttons
    const decryptBtnTable = document.getElementById('decryptBtnTable');
    const encryptBtnTable = document.getElementById('encryptBtnTable');
    
    if (decryptBtnTable) {
        decryptBtnTable.addEventListener('click', function() {
            showConfirmation('decrypt_all', 'table');
        });
    }
    
    if (encryptBtnTable) {
        encryptBtnTable.addEventListener('click', function() {
            showConfirmation('encrypt_all', 'table');
        });
    }
});

function loadStudentsTable(search = '', status = '') {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    fetch('ajax/get_students_table.php?search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status), {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('studentsTableBody').innerHTML = data.html;
            
            // Update encryption status
            checkEncryptionStatus();
        } else {
            console.error('Error:', data.error);
            alert('Error loading students: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading students table');
    });
}

function showConfirmation(action, source = 'dashboard') {
    const isDecrypt = action === 'decrypt_all';
    const title = isDecrypt ? 'Decrypt All Students?' : 'Encrypt All Students?';
    const confirmText = isDecrypt ? 'Yes, Decrypt' : 'Yes, Encrypt';
    const confirmColor = isDecrypt ? '#28a745' : '#667eea';
    
    Swal.fire({
        title: title,
        html: '<strong>WARNING:</strong><br>This will ' + (isDecrypt ? 'decrypt' : 'encrypt') + ' ALL student data in the database.<br><br><small>This action cannot be undone!</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            performAction(action, source);
        }
    });
}

function checkEncryptionStatus() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    fetch('ajax/encrypt_decrypt_students.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=check_status&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        const statusEl = document.getElementById('encryptionStatus');
        const statusElStudents = document.getElementById('encryptionStatusStudents');
        const statusElTable = document.getElementById('encryptionStatusTable');
        
        if (data.success) {
            const statusDecrypted = '<span style="color: #10b981;">DECRYPTED</span>';
            const statusEncrypted = '<span style="color: #fbbf24;">ENCRYPTED</span>';
            const status = data.is_encrypted ? statusEncrypted : statusDecrypted;
            
            if (statusEl) statusEl.innerHTML = status;
            if (statusElStudents) statusElStudents.innerHTML = status;
            if (statusElTable) statusElTable.innerHTML = status;
        } else {
            if (statusEl) statusEl.innerHTML = 'Error checking status';
            if (statusElStudents) statusElStudents.innerHTML = 'Error checking status';
            if (statusElTable) statusElTable.innerHTML = 'Error checking status';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const statusEl = document.getElementById('encryptionStatus');
        const statusElStudents = document.getElementById('encryptionStatusStudents');
        const statusElTable = document.getElementById('encryptionStatusTable');
        if (statusEl) statusEl.innerHTML = 'Error checking';
        if (statusElStudents) statusElStudents.innerHTML = 'Error checking';
        if (statusElTable) statusElTable.innerHTML = 'Error checking';
    });
}

function performAction(action, source = 'dashboard') {
    Swal.fire({
        title: 'Processing...',
        html: 'Please wait while we ' + (action === 'decrypt_all' ? 'decrypt' : 'encrypt') + ' all student data...',
        icon: 'info',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    fetch('ajax/encrypt_decrypt_students.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=' + action + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                html: data.message,
                icon: 'success',
                confirmButtonColor: '#667eea',
                confirmButtonText: 'OK'
            }).then(() => {
                // If from manage student table, just refresh the table without full page reload
                if (source === 'table') {
                    loadStudentsTable();
                } else {
                    // For dashboard, reload the page
                    location.reload();
                }
            });
        } else {
            Swal.fire({
                title: 'Error!',
                html: (data.error || 'Unknown error occurred'),
                icon: 'error',
                confirmButtonColor: '#667eea'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            html: error.message,
            icon: 'error',
            confirmButtonColor: '#667eea'
        });
    });
}
</script>

</body>  
</html>