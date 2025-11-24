<?php

// Session Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error Handling
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

// Core Includes
require_once '../config/db.php';
require_once '../config/session.php';

// Security
$csrf_token = generateCSRFToken();

// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

// User Data
$faculty_id = $_SESSION['user_id'];
$faculty_name = $_SESSION['name'];

// Fetch faculty details from database
$faculty_user = null;
$user_stmt = $conn->prepare("
    SELECT id, name, email, employee_id, role, created_at
    FROM users WHERE id = ?
");
$user_stmt->bind_param("i", $faculty_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$faculty_user = $user_result->fetch_assoc();
$user_stmt->close();

/**
 * Get Faculty Statistics
 */
function getFacultyStats($conn, $faculty_id) {
    $stats = [
        'total_classes' => 0,
        'total_students' => 0,
        'active_terms' => 0
    ];
    
    try {
        // Total Classes
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT class_code) as total FROM class WHERE faculty_id = ?");
        $stmt->bind_param("i", $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_classes'] = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        
        // Total Students
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT ce.student_id) as total 
            FROM class c 
            LEFT JOIN class_enrollments ce ON c.class_code = ce.class_code AND ce.status = 'enrolled'
            WHERE c.faculty_id = ?
        ");
        $stmt->bind_param("i", $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_students'] = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        
        // Active Terms
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT CONCAT(academic_year, '_', term)) as total 
            FROM class WHERE faculty_id = ?
        ");
        $stmt->bind_param("i", $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['active_terms'] = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Faculty Stats Error: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Get Classes by Year and Term
 */
function getClassesByYearTerm($conn, $faculty_id, $academic_year, $term) {
    $query = "
        SELECT 
            MIN(c.class_id) as class_id,
            c.class_code,
            c.section,
            c.course_code,
            c.academic_year,
            c.term,
            GROUP_CONCAT(
                DISTINCT CONCAT(c.day, ' ', c.time) 
                ORDER BY FIELD(c.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                c.time SEPARATOR '\n'
            ) as schedule_display,
            c.room,
            s.course_title,
            s.units,
            COALESCE(enrollment_counts.student_count, 0) as student_count
        FROM class c
        LEFT JOIN subjects s ON c.course_code = s.course_code
        LEFT JOIN (
            SELECT class_code, COUNT(*) as student_count
            FROM class_enrollments
            WHERE status = 'enrolled'
            GROUP BY class_code
        ) enrollment_counts ON c.class_code = enrollment_counts.class_code
        WHERE c.faculty_id = ? AND c.academic_year = ? AND c.term = ?
        GROUP BY c.class_code, c.section, c.course_code, c.academic_year, c.term, c.room, s.course_title, s.units, enrollment_counts.student_count
        ORDER BY c.course_code, c.section
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) return null;

    $stmt->bind_param("iss", $faculty_id, $academic_year, $term);
    $stmt->execute();
    return $stmt->get_result();
}

// Load Stats
$faculty_stats = getFacultyStats($conn, $faculty_id);
$total_students = $faculty_stats['total_students'];
$active_sections = $faculty_stats['total_classes'];
$active_terms = $faculty_stats['active_terms'];

// Messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="<?= $csrf_token ?>">
    <title>NU Faculty Dashboard - Grading System</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    
    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../faculty/assets/css/faculty_dashboard.css">
    <link rel="stylesheet" href="../faculty/assets/css/flexible_grading.css">
    
    <!-- PDF Generation Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
</head>
<body>
   <button class="sidebar-toggle-external" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

   <header class="nu-header">
    <div class="nu-header-container">
        <div class="nu-brand">
            <div class="nu-logo-circle">
   <img src="/auth/assets/images/nu_logo.png" alt="NU Logo" class="nu-logo-img">
</div>
            <div class="nu-brand-text">
                <div class="nu-title-main">NATIONAL UNIVERSITY</div>
                <div class="nu-title-sub">GRADING SYSTEM</div>
            </div>
        </div>
        
        <div class="user-dropdown-container" style="position: relative; margin-right: 2rem;">
            <button class="user-dropdown-trigger" onclick="toggleUserDropdown()" style="background: linear-gradient(135deg, rgba(0, 48, 130, 0.08) 0%, rgba(0, 71, 171, 0.08) 100%); border: 1.5px solid rgba(255, 255, 255, 0.25); display: flex; align-items: center; gap: 1.25rem; color: white; cursor: pointer; padding: 1rem 1.5rem; border-radius: 16px; transition: all 0.3s;" onmouseover="this.style.background='linear-gradient(135deg, rgba(0, 48, 130, 0.15) 0%, rgba(0, 71, 171, 0.15) 100%)'; this.style.borderColor='rgba(255, 255, 255, 0.35)'" onmouseout="this.style.background='linear-gradient(135deg, rgba(0, 48, 130, 0.08) 0%, rgba(0, 71, 171, 0.08) 100%)'; this.style.borderColor='rgba(255, 255, 255, 0.25)'">
                <div class="user-avatar" style="width: 48px; height: 48px; background: #D4AF37; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #003082; font-size: 18px; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);">
                    <?= strtoupper(substr($faculty_name, 0, 2)) ?>
                </div>
                <div class="user-details" style="display: flex; flex-direction: column; text-align: left;">
                    <div class="user-name" style="font-size: 16px; font-weight: 600; color: white;"><?= htmlspecialchars($faculty_name) ?></div>
                    <div class="user-role" style="font-size: 12px; color: rgba(255,255,255,0.8);">Faculty</div>
                </div>
            </button>

            <!-- Dropdown Menu -->
            <div class="user-dropdown-menu" id="userDropdownMenu" style="position: absolute; top: calc(100% + 0.75rem); right: 0; background: white; border-radius: 12px; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12); min-width: 260px; display: none; overflow: hidden; z-index: 1001;">
                <!-- Profile Info Card -->
                <div class="dropdown-profile-card" style="padding: 1.25rem; background: linear-gradient(135deg, rgba(0, 48, 130, 0.05) 0%, rgba(0, 71, 171, 0.05) 100%); border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 1rem;">
                    <div class="user-avatar" style="width: 48px; height: 48px; background: #D4AF37; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #003082; font-size: 18px; flex-shrink: 0;">
                        <?= strtoupper(substr($faculty_name, 0, 2)) ?>
                    </div>
                    <div class="user-details" style="display: flex; flex-direction: column;">
                        <div class="user-name" style="font-size: 16px; font-weight: 600; color: #003082;"><?= htmlspecialchars($faculty_name) ?></div>
                        <div class="user-role" style="font-size: 12px; color: #6b7280;">Faculty</div>
                    </div>
                </div>

                <!-- Profile Option -->
                <a onclick="showSection('profile'); toggleUserDropdown();" class="dropdown-profile-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.25rem; color: #374151; text-decoration: none; transition: all 0.3s; cursor: pointer;" onmouseover="this.style.background='#f9fafb'; this.style.color='#003082'" onmouseout="this.style.background='transparent'; this.style.color='#374151'">
                    <i class="fas fa-user" style="width: 16px;"></i>
                    <span>My Profile</span>
                </a>

                <!-- Logout Option -->
                <a href="../auth/logout.php" class="dropdown-logout-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.25rem; color: #374151; text-decoration: none; transition: all 0.3s; border: none;" onmouseover="this.style.background='#f9fafb'; this.style.color='#003082'" onmouseout="this.style.background='transparent'; this.style.color='#374151'">
                    <i class="fas fa-sign-out-alt" style="width: 16px;"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
    
    <div class="nu-gold-bar">
        <div class="nu-campus">NU LIPA</div>
    </div>
</header>

    <!-- Admin Banner (if applicable) -->
    <?php if (isset($_SESSION['switched_from_admin'])): ?>
    <!-- ... existing admin banner code ... -->
    <?php endif; ?>

    <div class="dashboard-container">
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand">
                <h2>Faculty Portal</h2>
            </div>
        </div>

            <div class="sidebar-nav">
                <div class="nav-item">
                    <a href="#dashboard" class="nav-link active" onclick="event.preventDefault(); showSection('dashboard');">
                        <i class="fas fa-chart-bar"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#grading-system" class="nav-link" onclick="event.preventDefault(); showSection('grading-system');">
                        <i class="fas fa-calculator"></i>
                        <span>Grading System</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <button class="floating-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Dashboard Section -->
            <section id="dashboard" class="content-section active">
                <div class="section-header">
                    <div class="header-content">
                        <h1>Welcome, <?= htmlspecialchars($faculty_name) ?></h1>
                        <p class="subtitle">Manage your courses and student grades</p>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-content">
                                <div class="stat-number"><?= $active_sections ?></div>
                                <div class="stat-label">Active Classes</div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="fas fa-book"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-content">
                                <div class="stat-number"><?= $total_students ?></div>
                                <div class="stat-label">Total Students</div>
                            </div>
                            <div class="stat-icon success">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-content">
                                <div class="stat-number"><?= $active_terms ?></div>
                                <div class="stat-label">Active Terms</div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-content">
                                <div class="stat-number"><?= $active_sections > 0 ? round($total_students / $active_sections, 1) : 0 ?></div>
                                <div class="stat-label">Avg Students/Class</div>
                            </div>
                            <div class="stat-icon info">
                                <i class="fas fa-calculator"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Form -->
                <div class="filter-card">
                    <div class="filter-header">
                        <h3><i class="fas fa-filter"></i> Filter Classes</h3>
                    </div>
                    <form method="GET" class="filter-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="academic_year">Academic Year</label>
                                <select name="academic_year" id="academic_year" class="form-control" required>
                                    <option value="">Select Academic Year</option>
                                    <?php
                                    $stmt = $conn->prepare("SELECT DISTINCT academic_year FROM class WHERE faculty_id = ? ORDER BY academic_year DESC");
                                    $stmt->bind_param("i", $faculty_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    
                                    while($year = $result->fetch_assoc()) {
                                        $selected = ($_GET['academic_year'] ?? '') === $year['academic_year'] ? 'selected' : '';
                                        echo "<option value='{$year['academic_year']}' {$selected}>{$year['academic_year']}</option>";
                                    }
                                    $stmt->close();
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="term">Term</label>
                                <select name="term" id="term" class="form-control" required>
                                    <option value="">Select Term</option>
                                    <?php
                                    $stmt = $conn->prepare("SELECT DISTINCT term FROM class WHERE faculty_id = ? ORDER BY term");
                                    $stmt->bind_param("i", $faculty_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    
                                    while($term = $result->fetch_assoc()) {
                                        $selected = ($_GET['term'] ?? '') === $term['term'] ? 'selected' : '';
                                        echo "<option value='{$term['term']}' {$selected}>{$term['term']}</option>";
                                    }
                                    $stmt->close();
                                    ?>
                                </select>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <a href="?" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

 <!-- Classes Display -->
<div class="content-card">
    <div class="card-header">
        <h2>
            <i class="fas fa-book-open"></i>
            <?php if (!empty($_GET['academic_year']) && !empty($_GET['term'])): ?>
                Classes for <?= htmlspecialchars($_GET['term']) ?>, <?= htmlspecialchars($_GET['academic_year']) ?>
            <?php else: ?>
                Select Academic Year and Term
            <?php endif; ?>
        </h2>
    </div>

    <?php if (!empty($_GET['academic_year']) && !empty($_GET['term'])): ?>
        <?php
        $classes_result = getClassesByYearTerm($conn, $faculty_id, $_GET['academic_year'], $_GET['term']);
        $classes_data = [];
        if ($classes_result) {
            while($row = $classes_result->fetch_assoc()) {
                $classes_data[] = $row;
            }
        }
        ?>

        <?php if (count($classes_data) > 0): ?>
        <div class="table-container table-no-padding">
            <table class="table">
                <thead>
                    <tr>
                        <th class="col-course">Course</th>
                        <th class="col-section">Section</th>
                        <th class="col-schedule">Schedule</th>
                        <th class="col-room">Room</th>
                        <th class="col-students">Students</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes_data as $class): ?>
                    <tr>
                        <td>
                        <div class="table-course-title">
                        <?= htmlspecialchars($class['course_title'] ?? 'Course') ?>
                        </div>
                        <div class="table-course-code">
                        <?= htmlspecialchars($class['class_code']) ?>
                        </div>
                        </td>
                        <td>
                        <span class="table-section-badge">
                        <?= htmlspecialchars($class['section']) ?>
                        </span>
                        </td>
                        <td>
                        <div class="table-schedule">
                        <?= nl2br(htmlspecialchars($class['schedule_display'])) ?>
                        </div>
                        </td>
                        <td><?= htmlspecialchars($class['room'] ?: 'TBA') ?></td>
                        <td class="text-center">
                            <span class="badge badge-primary">
                                <i class="fas fa-users"></i> <?= $class['student_count'] ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="table-actions">
                                <button class="btn btn-primary btn-sm" 
                                        onclick="viewStudentMasterlist('<?= htmlspecialchars($class['class_code']) ?>')"
                                        title="View Students">
                                    <i class="fas fa-users"></i>
                                </button>
                                <button class="btn btn-success btn-sm" 
                                        onclick="openAddStudentModal('<?= htmlspecialchars($class['class_code']) ?>')"
                                        title="Enroll Student">
                                    <i class="fas fa-user-plus"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-book-open"></i>
            </div>
            <h3>No Classes Found</h3>
            <p>No classes for selected period.</p>
        </div>
        <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">
            <i class="fas fa-filter"></i>
        </div>
        <h3>Select Filters</h3>
        <p>Choose academic year and term to view classes.</p>
    </div>
    <?php endif; ?>
</div>
            </section>
            

            <!-- GRADING SYSTEM SECTION -->
            <section id="grading-system" class="content-section">
                <div class="section-header">
                    <div class="header-content">
                        <h1><i class="fas fa-calculator"></i> Grading System</h1>
                        <p class="subtitle">Flexible grading with customizable components</p>
                    </div>
                </div>

                <!-- Class Selection -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chalkboard-teacher"></i> Select Class to Grade</h3>
                    </div>
                    <div class="class-selection-container">
                        <div class="grading-filter-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="flex_grading_academic_year">Academic Year</label>
                                    <select name="flex_grading_academic_year" id="flex_grading_academic_year" class="form-control" required>
                                        <option value="">-- Select Year --</option>
                                        <?php
                                        $stmt = $conn->prepare("SELECT DISTINCT academic_year FROM class WHERE faculty_id = ? ORDER BY academic_year DESC");
                                        $stmt->bind_param("i", $faculty_id);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        
                                        while($year = $result->fetch_assoc()) {
                                            echo "<option value='{$year['academic_year']}'>{$year['academic_year']}</option>";
                                        }
                                        $stmt->close();
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="flex_grading_term">Term</label>
                                    <select name="flex_grading_term" id="flex_grading_term" class="form-control" required>
                                        <option value="">-- Select Term --</option>
                                        <?php
                                        $stmt = $conn->prepare("SELECT DISTINCT term FROM class WHERE faculty_id = ? ORDER BY term");
                                        $stmt->bind_param("i", $faculty_id);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        
                                        while($term = $result->fetch_assoc()) {
                                            echo "<option value='{$term['term']}'>{$term['term']}</option>";
                                        }
                                        $stmt->close();
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="flex_grading_class">Class</label>
                                    <select name="flex_grading_class" id="flex_grading_class" class="form-control" required disabled>
                                        <option value="">-- Select Class --</option>
                                    </select>
                                </div>

                                <div class="form-actions"> 
                                <button class="btn btn-load-students" onclick="loadClassAndGrading()">
                                <i class="fas fa-users"></i> Load Students
                                </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Grading Interface -->
                <div id="flexible-grading-interface" class="content-card grading-interface-hidden">
                    <div class="card-header">
                        <div class="header-info">
                            <h3 id="flex-selected-class-title"><i class="fas fa-users"></i> Students</h3>
                        </div>
                        <div class="header-actions">
                            <span id="flex-student-count" class="results-count">
                                <i class="fas fa-user-graduate"></i> 0 students
                            </span>
                        </div>
                    </div>

                    
                    <!-- Main Term Tabs -->
                    <div class="main-term-tabs">
                    <button class="main-tab-btn active" data-term="midterm" onclick="switchFlexibleMainTab('midterm')">
                        <i class="fas fa-bookmark"></i> MIDTERM
                    </button>
                    <button class="main-tab-btn" data-term="finals" onclick="switchFlexibleMainTab('finals')">
                        <i class="fas fa-graduation-cap"></i> FINALS
                    </button>
                    <button class="main-tab-btn" data-term="summary" onclick="switchFlexibleMainTab('summary'); if(typeof renderSummary === 'function' && FGS.currentClassCode) renderSummary();">
    <i class="fas fa-trophy"></i> SUMMARY
</button>
                    </div>

                    <!-- MIDTERM Content -->
                        <div id="flex-midterm-content" class="main-tab-content active">
                        <div class="flexible-grading-header">
                        <div id="midterm-components"></div>
                                <div class="flexible-controls">
                <button id="edit-mode-toggle" class="btn btn-secondary btn-sm" onclick="toggleEdit()">
                <i class="fas fa-edit"></i> Edit Mode
                </button>
                <button class="btn btn-primary btn-sm" onclick="editTermWeights()">
                <i class="fas fa-percentage"></i> Edit Term Weights (Midterm: 40% | Finals: 60%)
                </button>
                <button class="btn btn-success btn-sm" onclick="addComponentModal()">
                <i class="fas fa-plus"></i> Add Component
                </button>
                </div>
                        </div>

                        <div id="flexible-edit-panel" class="edit-panel-hidden">
                            <div id="flexible-edit-panel-content"></div>
                        </div>

                        <div class="flexible-tabs" id="flexible-tabs-container">
                            <!-- Tabs loaded by JS -->
                        </div>

                        <div id="flexible-table-container" class="table-container">
                            <!-- Table loaded by JS -->
                        </div>
                    </div>

                   <!-- FINALS Content -->
                    <div id="flex-finals-content" class="main-tab-content">
                    <div class="flexible-grading-header">
                    <div id="finals-components"></div>

                    <div class="flexible-controls">
                    <button id="edit-mode-toggle-finals" class="btn btn-secondary btn-sm" onclick="toggleEdit()">
                        <i class="fas fa-edit"></i> Edit Mode
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="editTermWeights()">
                        <i class="fas fa-percentage"></i> Edit Term Weights (Midterm: 40% | Finals: 60%)
                    </button>
                    <button class="btn btn-success btn-sm" onclick="addComponentModal()">
                        <i class="fas fa-plus"></i> Add Component
                    </button>
                    </div>
                    </div>

                    <div id="flexible-table-container-finals" class="table-container">
                    <!-- Table loaded by JS -->
                    </div>
                    </div>

                   <!-- SUMMARY Content -->
<div id="flex-summary-content" class="main-tab-content">
    <div class="flexible-grading-header">
        <h2><i class="fas fa-trophy"></i> Grade Summary</h2>
        <div class="flexible-controls">
            <button class="btn btn-success btn-sm" onclick="exportFlexibleGradesToCSV()">
                <i class="fas fa-download"></i> Export CSV           
            <!--  CAR Generation Button -->
            <button onclick="openCARPreparation()" class="btn btn-primary">
    <i class="fas fa-file-alt"></i> PREVIEW CAR
</button>
            
            <!-- COA Generation Button -->
            <button class="btn btn-info btn-sm" onclick="openCOAPreparation()">
                <i class="fas fa-chart-bar"></i> Generate COA
            </button>
        </div>
    </div>

    <!-- Grade Encryption/Decryption Controls -->
    <div id="gradeEncryptionSection" style="margin: 0 0 24px 0; padding: 20px; background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); border-radius: 12px; box-shadow: 0 8px 24px rgba(30, 58, 138, 0.2); border: 1px solid rgba(251, 191, 36, 0.2); display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div style="display: flex; align-items: center; gap: 16px; color: white; flex: 1;">
                <div style="font-size: 36px; color: #fbbf24;">
                    <i class="fas fa-lock"></i>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 1.1em;">Grade Visibility Control</div>
                    <div style="font-size: 0.9em; opacity: 0.9; margin-top: 4px;">
                        Status: <span id="gradeEncryptionStatus" style="font-weight: 700; color: #fbbf24; text-transform: uppercase;">Checking...</span>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <!-- Single Toggle Button -->
                <button id="toggleGradesBtn" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #1e3a8a; border: none; padding: 12px 28px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.95em; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(251, 191, 36, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(251, 191, 36, 0.3)'">
                    <i class="fas fa-eye-slash"></i> <span id="toggleBtnText">Hide Grades</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Grade Summary Container -->
    <div id="summary-grades-container" class="table-container">
        <!-- Rendered by renderSummary() in flexible_grading.js -->
        <div class="fgs-loading-container">
            <i class="fas fa-spinner fa-spin fgs-loading-icon"></i>
            <p class="fgs-loading-text">Loading summary...</p>
        </div>
    </div>
</div>
            </section> <!-- end grading-system -->
            <!-- PROFILE SECTION -->
            <section id="profile" class="content-section">
                <div class="section-header">
                    <div class="header-content">
                        <h1><i class="fas fa-user-circle"></i> My Profile</h1>
                        <p class="subtitle">View and manage your account information</p>
                    </div>
                </div>
                <div class="profile-grid">
                    <!-- Personal Information Card -->
                    <div class="content-card profile-card">
                        <div class="card-header">
                            <h3><i class="fas fa-user"></i> Personal Information</h3>
                        </div>
                        <div class="profile-details">
                            <div class="profile-row"><div class="profile-label">Full Name</div><div class="profile-value"><?= $faculty_user ? htmlspecialchars($faculty_user['name']) : htmlspecialchars($faculty_name) ?></div></div>
                            <div class="profile-row"><div class="profile-label">Email</div><div class="profile-value"><i class="fas fa-envelope text-icon"></i><?= $faculty_user ? htmlspecialchars($faculty_user['email']) : 'N/A' ?></div></div>
                            <div class="profile-row"><div class="profile-label">Employee ID</div><div class="profile-value"><?= $faculty_user ? htmlspecialchars($faculty_user['employee_id']) : 'N/A' ?></div></div>
                            <div class="profile-row"><div class="profile-label">Role</div><div class="profile-value"><span class="role-badge">Faculty</span></div></div>
                        </div>
                    </div>
                    <!-- Account Status Card -->
                    <div class="content-card profile-card">
                        <div class="card-header">
                            <h3><i class="fas fa-clock"></i> Account Status</h3>
                        </div>
                        <div class="profile-details">
                            <div class="profile-row"><div class="profile-label">Account Created</div><div class="profile-value"><i class="fas fa-calendar text-icon"></i><?= $faculty_user && $faculty_user['created_at'] ? date('M d, Y', strtotime($faculty_user['created_at'])) : 'N/A' ?></div></div>
                            <div class="profile-row"><div class="profile-label">Status</div><div class="profile-value"><span class="status-badge-active"><i class="fas fa-check-circle"></i> Active</span></div></div>
                        </div>
                    </div>
                    <!-- Security Card -->
                    <div class="content-card profile-card">
                        <div class="card-header">
                            <h3><i class="fas fa-lock"></i> Security</h3>
                        </div>
                        <div class="profile-actions">
                            <button class="btn btn-primary" onclick="openChangePasswordModal()">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                            <a href="../auth/logout.php" class="btn btn-secondary">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                    <!-- Notification Preferences Card -->
                    <div class="content-card profile-card">
                        <div class="card-header">
                            <h3><i class="fas fa-cog"></i> Notification Preferences</h3>
                        </div>
                        <div class="preferences-list">
                            <div class="preference-item">
                                <span class="preference-label"><i class="fas fa-envelope text-icon"></i> Email Notifications</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="email_notifications" checked onchange="updatePreference('email_notifications')">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="preference-item">
                                <span class="preference-label"><i class="fas fa-bell text-icon"></i> Dashboard Notifications</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="dashboard_notifications" checked onchange="updatePreference('dashboard_notifications')">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <!-- Add Component Modal -->
    <div id="add-component-modal" class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-folder-plus"></i> Add New Component</h3>
                <button class="modal-close" onclick="closeAddComponentModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Component Name</label>
                    <input type="text" id="new-component-name" class="form-control" placeholder="e.g., Recitation, Project">
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i> Enter a descriptive name for the component
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-percent"></i> Percentage</label>
                    <input type="number" id="new-component-percentage" class="form-control" min="0" max="100" step="0.01" placeholder="0.00">
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i> Weight of this component in the final grade
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button class="btn btn-secondary" onclick="closeAddComponentModal()" style="flex: 1;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn btn-success" onclick="addNewComponent()" style="flex: 1;">
                        <i class="fas fa-check"></i> Add Component
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="add-item-modal" class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Item</h3>
                <button class="modal-close" onclick="closeAddItemModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="new-item-name">
                        <i class="fas fa-tag"></i> Item Name
                    </label>
                    <input type="text" 
                           id="new-item-name" 
                           class="form-control" 
                           placeholder="e.g., Quiz 1, Assignment 1"
                           maxlength="50">
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i> Enter a descriptive name
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new-item-max-score">
                        <i class="fas fa-hashtag"></i> Maximum Score
                    </label>
                    <input type="number" 
                           id="new-item-max-score" 
                           class="form-control" 
                           placeholder="e.g., 50, 100"
                           min="1"
                           max="1000"
                           value="100">
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i> Maximum points for this item
                    </div>
                </div>
                
                <div class="modal-footer-actions">
                <button class="btn btn-secondary btn-flex" onclick="closeAddComponentModal()">
                <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn btn-success btn-flex" onclick="addNewComponent()">
                <i class="fas fa-check"></i> Add Component
                </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Masterlist Modal -->
    <div id="student-masterlist-modal" class="modal-backdrop">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-users"></i> <span id="masterlist-class-title">Students Enrolled</span></h3>
                <button class="modal-close" onclick="closeStudentMasterlist()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
    <div class="modal-actions-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <div class="form-group" style="margin: 0; flex: 1; max-width: 250px;">
            <label for="masterlist-status-filter" style="display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600;">
                <i class="fas fa-filter"></i> Filter by Status
            </label>
            <select id="masterlist-status-filter" class="form-control" onchange="filterMasterlistByStatus()">
                <option value="all">All Students</option>
                <option value="enrolled" selected>Enrolled Only</option>
                <option value="dropped">Dropped/Withdrawn</option>
            </select>
        </div>
        
        <div style="text-align: right;">
            <span id="masterlist-filtered-count" style="display: inline-block; padding: 6px 12px; background: #eff6ff; color: #1e40af; border-radius: 6px; font-size: 13px; font-weight: 600;">
                <i class="fas fa-users"></i> <span id="filtered-count-number">0</span> students
            </span>
        </div>
    </div>
                
                <div id="masterlist-content" class="modal-table-wrapper">
                    <table class="masterlist-table">
                <colgroup>
                <col style="width: 130px;">
                <col style="width: 240px;">
                <col style="width: 320px;">
                <col style="width: 150px;">
                <col style="width: 150px;">
                </colgroup>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="masterlist-table-body">
                            <tr>
                            <td colspan="5" class="loading-cell">
                            <i class="fas fa-spinner fa-spin loading-icon"></i>
                            <p class="loading-text">Loading students...</p>
                            </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Student to Class Modal -->
    <div id="add-student-to-class-modal" class="modal-backdrop">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Enroll Student</h3>
                <button class="modal-close" onclick="closeAddStudentToClass()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="search-student-input">
                        <i class="fas fa-search"></i> Search Student
                    </label>
                    <input type="text" 
                           id="search-student-input" 
                           class="form-control" 
                           placeholder="Search by name, student ID, or email..."
                           onkeyup="searchStudentsForEnrollment()"
                           onfocus="if(this.value === '') searchStudentsForEnrollment()">
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i> Search by name, student ID, or email. Leave blank to see all available students.
                    </div>
                </div>
                
                <div id="student-search-results" class="search-results-container">
                <p class="search-placeholder" style="text-align: center; color: #666; padding: 40px 20px;">
                <i class="fas fa-users" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.3;"></i>
                Click the search box to view all available students
                </p>
                </div>
            </div>
        </div>
    </div>

   <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <!-- NU Themed SweetAlert Mixin -->
        <script src="../faculty/assets/js/nu_swal_theme.js?v=1.0"></script>
    
    <!-- Faculty Dashboard Scripts -->
    <script src="../faculty/assets/js/faculty_dashboard.js?v=2.1"></script>
    <script src="../faculty/assets/js/student_management.js?v=2.4"></script>
    <script src="../faculty/assets/js/grading_integration.js?v=2.1"></script>
    <script src="../faculty/assets/js/flexible_grading.js?v=2.9"></script>
    <script src="../faculty/assets/js/view_grades.js?v=2.1"></script>
    <script src="../faculty/assets/js/car_management.js?v=1.1"></script>
    <script src="../faculty/assets/js/car-pdf-generator.js?v=1.1"></script>
    <script src="../faculty/assets/js/debug_watcher.js?v=1.0"></script>
    
    <script>
        /**
         * Faculty Dashboard - Configuration Only
         * All functions have been moved to separate JS files for better organization
         * Ensure global APP object exists with apiPath fallback.
         */
        window.APP = window.APP || {};
        if(!window.APP.apiPath){
            // Fallback relative to dashboards directory
            window.APP.apiPath = '../faculty/ajax/';
        }

        // Grade Encryption/Decryption Functions
        let currentSelectedClass = null;

        // Listen for class selection changes
        const observer = new MutationObserver(function(mutations) {
            const classSelect = document.querySelector('select[name="class_select"], select[id="class_select"], select[data-class-select]');
            if (classSelect) {
                currentSelectedClass = classSelect.value;
                if (currentSelectedClass) {
                    updateGradeEncryptionStatus();
                    document.getElementById('gradeEncryptionSection').style.display = 'flex';
                } else {
                    document.getElementById('gradeEncryptionSection').style.display = 'none';
                }
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });

        // Check for class selection every 500ms
        setInterval(function() {
            const classSelect = document.querySelector('select[name="class_select"], select[id="class_select"], [name*="class"]');
            if (classSelect && classSelect.value) {
                if (currentSelectedClass !== classSelect.value) {
                    currentSelectedClass = classSelect.value;
                    updateGradeEncryptionStatus();
                    document.getElementById('gradeEncryptionSection').style.display = 'flex';
                }
            }
        }, 500);

        function updateGradeEncryptionStatus() {
            if (!currentSelectedClass) return;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?= $csrf_token ?>';

            const apiBase = (window.APP && window.APP.apiPath) ? window.APP.apiPath : '../faculty/ajax/';
            fetch(apiBase + 'encrypt_decrypt_grades.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=check_status&class_code=' + encodeURIComponent(currentSelectedClass) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => {
                // Check if response is OK and has JSON content type
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error(`Expected JSON but got ${contentType}`);
                }
                return response.text();
            })
            .then(text => {
                if (!text || text.trim() === '') {
                    throw new Error('Empty response from server');
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e, 'Response:', text.substring(0, 100));
                    throw e;
                }
            })
            .then(data => {
                const statusEl = document.getElementById('gradeEncryptionStatus');
                const btnTextEl = document.getElementById('toggleBtnText');
                const toggleBtn = document.getElementById('toggleGradesBtn');
                
                if (statusEl) {
                    if (data.success) {
                        const statusHidden = '<span style="color: #fbbf24;"> HIDDEN FROM STUDENTS</span>';
                        const statusVisible = '<span style="color: #10b981;"> VISIBLE TO STUDENTS</span>';
                        statusEl.innerHTML = data.is_encrypted ? statusHidden : statusVisible;
                        
                        // Update button text and icon based on current state
                        if (btnTextEl && toggleBtn && toggleBtn.querySelector('i')) {
                            if (data.is_encrypted) {
                                // Grades are encrypted (hidden), so button should say "Show Grades"
                                btnTextEl.innerText = 'Show Grades';
                                toggleBtn.querySelector('i').className = 'fas fa-eye';
                            } else {
                                // Grades are decrypted (visible), so button should say "Hide Grades"
                                btnTextEl.innerText = 'Hide Grades';
                                toggleBtn.querySelector('i').className = 'fas fa-eye-slash';
                            }
                        }
                    } else {
                        statusEl.innerHTML = 'Error checking status';
                    }
                }
            })
            .catch(error => {
                console.error('Error updating grade encryption status:', error);
                const statusEl = document.getElementById('gradeEncryptionStatus');
                if (statusEl) statusEl.innerHTML = 'Error checking';
            });
        }

        // Toggle Grades Button
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleGradesBtn');

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    if (!currentSelectedClass) {
                        Swal.fire({
                            title: 'No Class Selected',
                            html: 'Please select a class first to manage grade visibility.',
                            icon: 'warning',
                            confirmButtonColor: '#2563eb'
                        });
                        return;
                    }

                    // Determine current state and perform opposite action
                    const statusEl = document.getElementById('gradeEncryptionStatus');
                    const currentStatus = statusEl ? statusEl.innerText : '';
                    const isHidden = currentStatus.includes('HIDDEN');

                    if (isHidden) {
                        // Currently hidden, so show grades
                        Swal.fire({
                            title: 'Show Grades to Students?',
                            html: '<strong> IMPORTANT:</strong><br>This will make ALL grades for this class VISIBLE to your students immediately.<br><br>Use this only when you\'re ready to release grades (e.g., after finals).',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#10b981',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Yes, Show Grades',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                performGradeAction('decrypt_all');
                            }
                        });
                    } else {
                        // Currently visible, so hide grades
                        Swal.fire({
                            title: 'Hide Grades from Students?',
                            html: '<strong>⚠️ CAUTION:</strong><br>This will make ALL grades for this class HIDDEN from your students.<br><br>Students will NOT be able to see their grades until you show them again.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#fbbf24',
                            confirmButtonTextColor: '#1e3a8a',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Yes, Hide Grades',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                performGradeAction('encrypt_all');
                            }
                        });
                    }
                });
            }
        });

        function performGradeAction(action) {
            if (!currentSelectedClass) return;

            Swal.fire({
                title: 'Processing...',
                html: 'Please wait while we ' + (action === 'decrypt_all' ? 'show' : 'hide') + ' grades...',
                icon: 'info',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?= $csrf_token ?>';

            const apiBase = (window.APP && window.APP.apiPath) ? window.APP.apiPath : '../faculty/ajax/';
            fetch(apiBase + 'encrypt_decrypt_grades.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=' + action + '&class_code=' + encodeURIComponent(currentSelectedClass) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button text and icon based on action
                    const btnTextEl = document.getElementById('toggleBtnText');
                    const toggleBtn = document.getElementById('toggleGradesBtn');
                    
                    if (action === 'decrypt_all') {
                        // Grades are now shown (decrypted), so button should say "Hide Grades"
                        if (btnTextEl) btnTextEl.innerText = 'Hide Grades';
                        if (toggleBtn) {
                            toggleBtn.querySelector('i').className = 'fas fa-eye-slash';
                        }
                    } else if (action === 'encrypt_all') {
                        // Grades are now hidden (encrypted), so button should say "Show Grades"
                        if (btnTextEl) btnTextEl.innerText = 'Show Grades';
                        if (toggleBtn) {
                            toggleBtn.querySelector('i').className = 'fas fa-eye';
                        }
                    }
                    
                    Swal.fire({
                        title: 'Success!',
                        html: ' ' + data.message,
                        icon: 'success',
                        confirmButtonColor: '#2563eb',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        updateGradeEncryptionStatus();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        html: '❌ ' + (data.error || 'Unknown error occurred'),
                        icon: 'error',
                        confirmButtonColor: '#2563eb'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    html: '❌ ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#2563eb'
                });
            });
        }

        <?php
        // Determine API path based on environment
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isLocalhost = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
        $apiPath = $isLocalhost ? '/automation_system/faculty/ajax/' : '/faculty/ajax/';

        // Debug information (remove after testing)
        echo "<!-- UPLOAD_TEST: If you see this message, the file is uploaded correctly! API Path will be: $apiPath -->";
        echo "<!-- DEBUG: HTTP_HOST='$host', isLocalhost=" . ($isLocalhost ? 'true' : 'false') . ", apiPath='$apiPath' -->";
        ?>

        // Global Configuration
        const APP = {
            facultyId: <?= $faculty_id ?>,
            facultyName: "<?= htmlspecialchars($faculty_name) ?>",
            csrfToken: '<?= $csrf_token ?>',
            apiPath: '<?= $apiPath ?>'
        };
        
        // Set global APP object
        window.APP = APP;
        
        // Backward compatibility
        window.csrfToken = APP.csrfToken;
        
        // Toast Notification (used by all modules)
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        
        window.Toast = Toast;
        
        // Helper Functions (Keep these for backward compatibility)
        function showLoading() {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) overlay.style.display = 'flex';
        }
        
        function hideLoading() {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) overlay.style.display = 'none';
        }
        
        function showToast(icon, title) {
            Toast.fire({ icon, title });
        }
        
        // Show PHP Messages
  document.addEventListener('DOMContentLoaded', function() {
    <?php if($success): ?>
    Toast.fire({ 
        icon: 'success', 
        title: '<?= addslashes($success) ?>' 
    });
    <?php endif; ?>

    <?php if($error): ?>
    Toast.fire({ 
        icon: 'error', 
        title: '<?= addslashes($error) ?>' 
    });
    <?php endif; ?>
    
    console.log('✓ Faculty Dashboard Configuration Loaded');
    console.log('✓ CSRF Token:', window.csrfToken ? 'Set ✓' : 'MISSING ✗');
    console.log('✓ API Path:', window.APP.apiPath);
    console.log('✓ All modules loaded successfully');
    // Load initial notification preferences
    loadInitialPreferences();
    
});
/**
 * Restore Last Selected Class (Persist after refresh)
 */
function restoreLastSelectedClass() {
    // Check if we're on the grading-system section
    const gradingSection = document.getElementById('grading-system');
    if (!gradingSection || !gradingSection.classList.contains('active')) {
        return; // Not on grading page
    }
    
    // Get saved values from localStorage
    const savedYear = localStorage.getItem('grading_academic_year');
    const savedTerm = localStorage.getItem('grading_term');
    const savedClass = localStorage.getItem('grading_class_code');
    
    if (savedYear && savedTerm && savedClass) {
        console.log('🔄 Restoring last class:', savedClass);
        
        // Restore the select values
        const yearSelect = document.getElementById('flex_grading_academic_year');
        const termSelect = document.getElementById('flex_grading_term');
        
        if (yearSelect && termSelect) {
            yearSelect.value = savedYear;
            termSelect.value = savedTerm;
            
            // Load classes, then select the saved class
            loadFlexibleClassesForGrading(savedYear, savedTerm).then(() => {
                const classSelect = document.getElementById('flex_grading_class');
                if (classSelect) {
                    classSelect.value = savedClass;
                    
                    // Auto-load after a brief delay
                    setTimeout(() => {
                        loadClassAndGrading();
                    }, 500);
                }
            });
        }
    }
}

// Save selected class when loading
const originalLoadClassAndGrading = window.loadClassAndGrading;
window.loadClassAndGrading = async function() {
    const year = document.getElementById('flex_grading_academic_year')?.value;
    const term = document.getElementById('flex_grading_term')?.value;
    const classCode = document.getElementById('flex_grading_class')?.value;
    
    if (year && term && classCode) {
        // Save to localStorage
        localStorage.setItem('grading_academic_year', year);
        localStorage.setItem('grading_term', term);
        localStorage.setItem('grading_class_code', classCode);
    }
    
    // Call original function
    return originalLoadClassAndGrading();
};
        function editTermWeights() {
    // Determine which tab is active
    const isMidterm = document.querySelector('[data-term="midterm"]').classList.contains('active');
    const termName = isMidterm ? 'Midterm' : 'Finals';
    const currentWeight = isMidterm ? 40 : 60; // Get from FGS if available
    const icon = isMidterm ? 'fa-bookmark' : 'fa-graduation-cap';
    const color = isMidterm ? '#3b82f6' : '#10b981';
    
    Swal.fire({
        title: `<i class="fas ${icon}"></i> Edit ${termName} Weight`,
        html: `
            <div style="text-align: left; padding: 20px;">
               <div class="modal-actions-bar">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">
                        <i class="fas ${icon}" style="color: ${color};"></i> ${termName} Weight (%)
                    </label>
                    <input type="number" id="swal-term-weight" 
                           class="swal2-input" 
                           value="${currentWeight}" 
                           min="0" max="100" step="0.01" 
                           style="width: 100%; padding: 12px; margin: 0; font-size: 18px; font-weight: 600;">
                </div>
                
                <div style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); padding: 15px; border-radius: 8px; border-left: 4px solid ${color};">
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <i class="fas fa-calculator" style="color: ${color}; font-size: 20px; margin-top: 2px;"></i>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #1f2937; margin-bottom: 8px;">Auto-Calculation:</div>
                            <div id="auto-calc-display" style="color: #6b7280; font-size: 14px; line-height: 1.6;">
                                ${isMidterm ? 'Midterm' : 'Finals'}: <strong id="calc-term1">${currentWeight}%</strong><br>
                                ${isMidterm ? 'Finals' : 'Midterm'}: <strong id="calc-term2">${100 - currentWeight}%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Save Weight',
        cancelButtonText: '<i class="fas fa-times"></i> Cancel',
        confirmButtonColor: color,
        cancelButtonColor: '#6b7280',
        width: '500px',
        didOpen: () => {
            const input = document.getElementById('swal-term-weight');
            const calc1 = document.getElementById('calc-term1');
            const calc2 = document.getElementById('calc-term2');
            
            // Update calculation on input
            input.addEventListener('input', function() {
                const value = parseFloat(this.value) || 0;
                const other = 100 - value;
                calc1.textContent = value.toFixed(1) + '%';
                calc2.textContent = other.toFixed(1) + '%';
                
                // Visual feedback
                if (value < 0 || value > 100) {
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = color;
                }
            });
        },
        preConfirm: () => {
            const weight = parseFloat(document.getElementById('swal-term-weight').value);
            
            if (isNaN(weight)) {
                Swal.showValidationMessage('Please enter a valid number');
                return false;
            }
            
            if (weight < 0 || weight > 100) {
                Swal.showValidationMessage('Weight must be between 0 and 100');
                return false;
            }
            
            return {
                midterm: isMidterm ? weight : (100 - weight),
                finals: isMidterm ? (100 - weight) : weight,
                editedTerm: termName
            };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            await saveTermWeights(result.value.midterm, result.value.finals);
        }
    });
}

/**
 * Save Term Weights to Database
 */
async function saveTermWeights(midterm, finals) {
    showLoading();
    
    const fd = new FormData();
    fd.append('action', 'update_term_weights');
    fd.append('class_code', typeof FGS !== 'undefined' ? FGS.currentClassCode : '');
    fd.append('midterm_weight', midterm);
    fd.append('finals_weight', finals);
    fd.append('csrf_token', window.csrfToken);
    
    try {
        const response = await fetch('/automation_system/faculty/ajax/manage_grading_components.php', {
            method: 'POST',
            body: fd
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (data.success) {
            Toast.fire({
                icon: 'success',
                title: `Term weights updated! (Midterm: ${midterm}% | Finals: ${finals}%)`
            });
            
            // Update button text on both tabs
            document.querySelectorAll('.flexible-controls .btn-primary').forEach(btn => {
                if (btn.textContent.includes('Term Weights')) {
                    btn.innerHTML = `<i class="fas fa-percentage"></i> Edit Term Weights (Midterm: ${midterm}% | Finals: ${finals}%)`;
                }
            });
            
            // Update FGS if available
            if (typeof FGS !== 'undefined') {
                FGS.midtermWeight = midterm;
                FGS.finalsWeight = finals;
                if (typeof updateLimit === 'function') updateLimit();
                if (typeof renderUI === 'function') renderUI();
            }
        } else {
            Toast.fire({
                icon: 'error',
                title: data.message || 'Failed to update weights'
            });
        }
    } catch (error) {
        hideLoading();
        console.error('Error:', error);
        Toast.fire({
            icon: 'error',
            title: 'Failed to update term weights'
        });
    }
}

// Profile Modal Functions
function openChangePasswordModal() {
    const modal = document.createElement('div');
    modal.id = 'changePasswordModal';
    modal.style.cssText = 'display:flex;position:fixed;z-index:1100;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:20px;';
    modal.innerHTML = `
        <div class="content-card" style="max-width:480px;width:100%;border-radius:20px;overflow:hidden;">
            <div class="card-header" style="background:linear-gradient(135deg,var(--primary-600),var(--primary-700));color:#fff;border-bottom:3px solid var(--gold-500);display:flex;align-items:center;justify-content:space-between;">
                <h3 style="color:#fff;margin:0;font-size:18px;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-key"></i> Change Password
                </h3>
                <button type="button" onclick="closeChangePasswordModal()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:36px;height:36px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="changePasswordForm" style="padding:28px;display:flex;flex-direction:column;gap:18px;">
                <div>
                    <label for="current_password" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px;">Current Password</label>
                    <input type="password" id="current_password" class="form-control" placeholder="Enter current password" required>
                </div>
                <div>
                    <label for="new_password" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px;">New Password</label>
                    <input type="password" id="new_password" class="form-control" placeholder="Min 8 chars, strong" required>
                    <div class="form-help"><i class="fas fa-info-circle"></i> Must include uppercase, lowercase & number</div>
                </div>
                <div>
                    <label for="confirm_password" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px;">Confirm Password</label>
                    <input type="password" id="confirm_password" class="form-control" placeholder="Repeat new password" required>
                </div>
                <input type="hidden" id="cp_csrf" value="<?= $csrf_token ?>">
                <div style="display:flex;gap:12px;margin-top:10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeChangePasswordModal()" style="flex:1;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" style="flex:1;">
                        <i class="fas fa-save"></i> Update Password
                    </button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    document.getElementById('changePasswordForm').addEventListener('submit', handleChangePassword);
}

async function handleChangePassword(event) {
    event.preventDefault();
    const currentPassword = document.getElementById('current_password').value.trim();
    const newPassword = document.getElementById('new_password').value.trim();
    const confirmPassword = document.getElementById('confirm_password').value.trim();
    const csrfToken = document.getElementById('cp_csrf').value;

    if (!currentPassword || !newPassword || !confirmPassword) {
        return Toast.fire({ icon:'error', title:'All fields required' });
    }
    if (newPassword !== confirmPassword) {
        return Toast.fire({ icon:'error', title:'Passwords do not match' });
    }
    if (newPassword.length < 8) {
        return Toast.fire({ icon:'error', title:'Minimum length 8 characters' });
    }
    if (!(/[A-Z]/.test(newPassword) && /[a-z]/.test(newPassword) && /[0-9]/.test(newPassword))) {
        return Toast.fire({ icon:'error', title:'Include upper, lower & number' });
    }

    showLoading();
    try {
        const body = new URLSearchParams({
            csrf_token: csrfToken,
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword,
            user_role: 'faculty'
        }).toString();
        const response = await fetch('../auth/process_change_password.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body
        });
        const data = await response.json();
        hideLoading();
        if (data.success) {
            Toast.fire({ icon:'success', title:data.message || 'Password updated' });
            closeChangePasswordModal();
        } else {
            Toast.fire({ icon:'error', title:data.message || 'Update failed' });
        }
    } catch (err){
        hideLoading();
        console.error(err);
        Toast.fire({ icon:'error', title:'Request error' });
    }
}

function closeChangePasswordModal(){
    const modal = document.getElementById('changePasswordModal');
    if(modal) modal.remove();
}

// Update preferences
async function updatePreference(preference) {
    const checkbox = document.getElementById(preference);
    const value = checkbox.checked ? 1 : 0;
    try {
        const response = await fetch('../ajax/update_preferences.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: `preference=${encodeURIComponent(preference)}&value=${value}`
        });
        const data = await response.json();
        if(data.success){
            Toast.fire({ icon:'success', title:`${preference.replace(/_/g,' ')} updated` });
        } else {
            Toast.fire({ icon:'error', title:data.message || 'Failed updating preference' });
            checkbox.checked = !checkbox.checked;
        }
    } catch (err){
        console.error('Error:', err);
        Toast.fire({ icon:'error', title:'Network error updating preference' });
        checkbox.checked = !checkbox.checked;
    }
}

// Initial preference loading
async function loadInitialPreferences(){
    try {
        const res = await fetch('../ajax/get_preferences.php');
        const data = await res.json();
        if(!data.success || !data.preferences) return;
        const prefs = data.preferences;
        ['email_notifications','dashboard_notifications'].forEach(key => {
            const el = document.getElementById(key);
            if(el) el.checked = prefs[key] == 1;
        });
    } catch (e){
        console.warn('Preference load failed', e);
    }
}

    </script>
 <!-- CAR Preparation Wizard Modal -->
<div id="car-wizard-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <!-- Modal Header -->
        <div class="modal-header">
            <div>
                <h3>
                    <i class="fas fa-file-alt"></i> 
                    <span id="car-wizard-title">CAR Preparation</span>
                </h3>
                <p style="color: #6b7280; font-size: 14px; margin: 5px 0 0 0;">
                    <i class="fas fa-chalkboard"></i> 
                    <span id="car-class-info">Loading...</span>
                </p>
            </div>
            <button class="modal-close" onclick="closeCARWizard()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Progress Bar -->
        <div class="wizard-progress">
            <div class="progress-steps">
                <div class="progress-step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Teaching Strategies</div>
                </div>
                <div class="progress-step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Interventions</div>
                </div>
                <div class="progress-step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Problems & Actions</div>
                </div>
                <div class="progress-step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-label">Proposed Actions</div>
                </div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" id="wizard-progress-fill" style="width: 20%;"></div>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="modal-body" style="min-height: 400px; max-height: 500px; overflow-y: auto;">
            <!-- Step 1: Teaching Strategies -->
            <div id="car-step-1" class="wizard-step active">
                <div class="wizard-step-header">
                    <h4><i class="fas fa-chalkboard-teacher"></i> Teaching Strategies Employed</h4>
                    <p>List the teaching methods and strategies you used in this course.</p>
                </div>
                <div class="form-group">
                    <label>Teaching Strategies *</label>
                    <textarea 
                        id="teaching-strategies" 
                        class="form-control" 
                        rows="10"
                        placeholder="Example:&#10;1. Positive Classroom Management – encouraged students to actively participate&#10;2. Individual Laboratory Activity – students work on their own projects&#10;3. Utilizing Technology in the Classroom – use of cloud ide for programming learning"
                    ></textarea>
                    <small class="form-help">
                        <i class="fas fa-info-circle"></i> Format: 1. Strategy Name – Brief description. Each strategy on a new line.
                    </small>
                </div>
            </div>

            <!-- Step 2: Interventions -->
            <!-- Step 2: Interventions -->
<div id="car-step-2" class="wizard-step">
    <div class="wizard-step-header">
        <h4><i class="fas fa-hands-helping"></i> Intervention & Enrichment Activities</h4>
        <p>Add activities conducted and number of students involved.</p>
    </div>
    
    <div style="margin-bottom: 15px;">
        <button type="button" class="btn btn-success btn-sm" onclick="addInterventionRow()">
            <i class="fas fa-plus"></i> Add Activity
        </button>
    </div>
    
    <div class="table-container" style="max-height: 350px; overflow-y: auto;">
        <table class="table" id="interventions-table">
            <thead>
                <tr>
                    <th style="width: 60%;">Intervention or Enrichment Activities Conducted</th>
                    <th style="width: 25%;">No. of Students Involved</th>
                    <th style="width: 15%; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody id="interventions-table-body">
                <!-- Rows added dynamically -->
            </tbody>
        </table>
    </div>
    
    <div id="interventions-empty-state" style="text-align: center; padding: 40px; color: #6b7280; display: block;">
        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
        <p>No activities added yet. Click "Add Activity" to begin.</p>
    </div>
</div>

            <!-- Step 3: Problems & Actions -->
            <div id="car-step-3" class="wizard-step">
                <div class="wizard-step-header">
                    <h4><i class="fas fa-exclamation-triangle"></i> Problems Encountered & Actions Taken</h4>
                    <p>Identify challenges faced and how you addressed them.</p>
                </div>
                <div class="form-group">
                    <label>Problems Encountered *</label>
                    <textarea 
                        id="problems-encountered" 
                        class="form-control" 
                        rows="5"
                        placeholder="Example:&#10;- Time constraints for laboratory activities&#10;- Limited resources for hands-on exercises&#10;- Student absences during critical topics"
                    ></textarea>
                </div>
                <div class="form-group">
                    <label>Actions Taken *</label>
                    <textarea 
                        id="actions-taken" 
                        class="form-control" 
                        rows="5"
                        placeholder="Example:&#10;- Extended lab hours for students who needed more time&#10;- Developed alternative exercises using free online tools&#10;- Provided recorded lectures and supplementary materials"
                    ></textarea>
                </div>
            </div>

            <!-- Step 4: Proposed Actions -->
            <div id="car-step-4" class="wizard-step">
                <div class="wizard-step-header">
                    <h4><i class="fas fa-lightbulb"></i> Proposed Actions for Improvement</h4>
                    <p>Suggest improvements for future offerings of this course.</p>
                </div>
                <div class="form-group">
                    <label>Proposed Actions *</label>
                    <textarea 
                        id="proposed-actions" 
                        class="form-control" 
                        rows="10"
                        placeholder="Example:&#10;1. Request additional laboratory equipment&#10;2. Develop more practice exercises and examples&#10;3. Implement early intervention for at-risk students&#10;4. Schedule regular consultation hours&#10;5. Update course materials with current industry practices"
                    ></textarea>
                    <small class="form-help">
                        <i class="fas fa-info-circle"></i> List actionable recommendations
                    </small>
                </div>
            </div>

        <!-- Modal Footer -->
        <div class="modal-footer" style="display: flex; justify-content: space-between; gap: 10px;">
            <button class="btn btn-secondary" id="wizard-prev-btn" onclick="previousWizardStep()" disabled>
                <i class="fas fa-arrow-left"></i> Previous
            </button>
            <button class="btn btn-outline" onclick="saveCARDraft()">
                <i class="fas fa-save"></i> Save Draft
            </button>
            <button class="btn btn-primary" id="wizard-next-btn" onclick="nextWizardStep()">
                Next <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>
</div>   

<script>
function toggleUserDropdown() {
    const menu = document.getElementById('userDropdownMenu');
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.user-dropdown-container');
    if (dropdown && !dropdown.contains(event.target)) {
        document.getElementById('userDropdownMenu').style.display = 'none';
    }
});
</script>

</body>
</html>