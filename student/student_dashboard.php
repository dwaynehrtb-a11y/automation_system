<?php
define('SYSTEM_ACCESS', true);
require_once '../config/session.php';
require_once '../config/db.php';

// Check if user is logged in and is a student
startSecureSession();

if (!isAuthenticated() || !isStudent()) {
    redirectToLogin('Please login as a student to access this page.');
    exit();
}

// Get current student info
$current_user = getCurrentUser();
$student_id = $current_user['student_id'];

// Get student details
$student_query = "
    SELECT 
        student_id,
        CONCAT(first_name, ' ', last_name) as full_name,
        first_name,
        last_name,
        middle_initial,
        email,
        birthday,
        status,
        enrollment_date
    FROM student 
    WHERE student_id = ?
";
$stmt = $conn->prepare($student_query);

if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student record not found");
}

// Get enrolled classes with grade information
$classes_query = "
    SELECT DISTINCT
        c.class_code,
        c.course_code,
        s.course_title,
        s.course_desc,
        s.units,
        c.section,
        c.academic_year,
        c.term,
        c.room,
        u.name as faculty_name,
        ce.status as enrollment_status,
        ce.enrollment_id,
        GROUP_CONCAT(
            CONCAT(c.day, ' ', c.time) 
            ORDER BY FIELD(c.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
            SEPARATOR ', '
        ) as schedule
    FROM class_enrollments ce
INNER JOIN class c ON ce.class_code = c.class_code
    LEFT JOIN subjects s ON c.course_code = s.course_code
    LEFT JOIN users u ON c.faculty_id = u.id
    WHERE ce.student_id = ? AND ce.status = 'enrolled'
    GROUP BY c.class_code, c.course_code, s.course_title, s.course_desc, s.units, 
             c.section, c.academic_year, c.term, c.room, u.name, ce.status, ce.enrollment_id
    ORDER BY c.academic_year DESC, c.term, c.section
";
$stmt = $conn->prepare($classes_query);

if (!$stmt) {
    die("Classes Query Error: " . $conn->error);
}

$stmt->bind_param("s", $student_id);
$stmt->execute();
$enrolled_classes = $stmt->get_result();
$stmt->close();

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>NU Student Portal - <?= htmlspecialchars($student['full_name']) ?></title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../student/assets/css/student_dashboard.css?v=<?= time() ?>">
</head>
<body>
    <!-- Header -->
    <header class="student-header" style="background: linear-gradient(135deg, #003082 0%, #0047ab 100%); box-shadow: 0 4px 12px rgba(0, 48, 130, 0.2); position: sticky; top: 0; z-index: 1000;">
        <div class="header-container" style="padding: 1rem 0; display: flex; justify-content: space-between; align-items: center; gap: 1.5rem;">
            <!-- NU Branding -->
            <div class="nu-brand" style="display: flex; align-items: center; gap: 1rem; color: white; padding-left: 2rem;">
                <div class="nu-logo-circle" style="width: 48px; height: 48px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <img src="/assets/images/nu_logo.png" alt="NU Logo" class="nu-logo-img" style="width: 40px; height: 40px;">
                </div>
                <div class="nu-brand-text" style="display: flex; flex-direction: column;">
                    <span class="nu-title-main" style="font-size: 16px; font-weight: 700; color: white;">NATIONAL UNIVERSITY</span>
                    <span class="nu-title-sub" style="font-size: 13px; color: rgba(255,255,255,0.9);">GRADING SYSTEM</span>
                </div>
            </div>

            <!-- User Dropdown Menu -->
            <div class="user-dropdown-container" style="position: relative; margin-right: 2rem;">
                <button class="user-dropdown-trigger" onclick="toggleUserDropdown()" style="background: linear-gradient(135deg, rgba(0, 48, 130, 0.08) 0%, rgba(0, 71, 171, 0.08) 100%); border: 1.5px solid rgba(255, 255, 255, 0.25); display: flex; align-items: center; gap: 1.25rem; color: white; cursor: pointer; padding: 1rem 1.5rem; border-radius: 16px; transition: all 0.3s;" onmouseover="this.style.background='linear-gradient(135deg, rgba(0, 48, 130, 0.15) 0%, rgba(0, 71, 171, 0.15) 100%)'; this.style.borderColor='rgba(255, 255, 255, 0.35)'" onmouseout="this.style.background='linear-gradient(135deg, rgba(0, 48, 130, 0.08) 0%, rgba(0, 71, 171, 0.08) 100%)'; this.style.borderColor='rgba(255, 255, 255, 0.25)'">
                    <div class="user-avatar" style="width: 48px; height: 48px; background: #D4AF37; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #003082; font-size: 18px; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);">
                        <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                    </div>
                    <div class="user-details" style="display: flex; flex-direction: column; text-align: left;">
                        <div class="user-name" style="font-weight: 700; font-size: 15px; color: white;"><?= htmlspecialchars($student['full_name']) ?></div>
                        <div class="user-role" style="font-size: 12px; opacity: 0.85; color: white;">Student</div>
                    </div>
                </button>

                <!-- Dropdown Menu -->
                <div class="user-dropdown-menu" id="userDropdownMenu" style="position: absolute; top: calc(100% + 0.75rem); right: 0; background: white; border-radius: 12px; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12); min-width: 260px; display: none; overflow: hidden; z-index: 1001;">
                    <!-- Profile Info Card -->
                    <div class="dropdown-profile-card" style="padding: 1.25rem; background: linear-gradient(135deg, rgba(0, 48, 130, 0.05) 0%, rgba(0, 71, 171, 0.05) 100%); border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 1rem;">
                        <div class="profile-avatar" style="width: 48px; height: 48px; background: #D4AF37; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #003082; font-size: 18px; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);">
                            <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                        </div>
                        <div class="profile-info" style="flex: 1;">
                            <div class="profile-name" style="font-weight: 700; font-size: 15px; color: #111827;"><?= htmlspecialchars($student['full_name']) ?></div>
                            <div class="profile-role" style="font-size: 12px; color: #6b7280; margin-top: 0.25rem;">Student</div>
                        </div>
                    </div>

                    <!-- Logout Option -->
                    <a href="../auth/logout.php" class="dropdown-logout-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.25rem; color: #374151; text-decoration: none; transition: all 0.3s; border: none;" onmouseover="this.style.background='#f9fafb'; this.style.color='#003082'" onmouseout="this.style.background='transparent'; this.style.color='#374151'">
                        <i class="fas fa-sign-out-alt" style="width: 16px; text-align: center; color: inherit;"></i>
                        <span style="font-weight: 500; font-size: 13px;">Logout</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- NU Gold Bar -->
        <div class="nu-gold-bar" style="background: linear-gradient(90deg, #D4AF37 0%, #e6c558 100%); padding: 0.5rem 0; display: flex; align-items: center; padding-left: 2rem; box-shadow: inset 0 -2px 4px rgba(0,0,0,0.1);">
            <span class="nu-campus" style="font-weight: 700; color: #003082; font-size: 12px; letter-spacing: 0.5px;">NU LIPA</span>
        </div>
    </header>

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

    <!-- Main Content -->
    <main class="main-content" style="max-width: 1400px; margin: 0 auto; padding: 2rem; min-height: calc(100vh - 180px);">
        <!-- Welcome Section -->
        <section class="welcome-section" style="margin-bottom: 2rem;">
            <div class="welcome-card" style="background: linear-gradient(135deg, #003082 0%, #0047ab 100%); border-radius: 12px; padding: 2rem; color: white; box-shadow: 0 4px 12px rgba(0, 48, 130, 0.15); display: flex; align-items: center; gap: 1.5rem;">
                <div class="welcome-icon" style="font-size: 48px; opacity: 0.9;">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="welcome-content">
                    <h1 class="welcome-title" style="font-size: 28px; font-weight: 700; margin-bottom: 0.5rem;">Welcome back, <?= htmlspecialchars($student['first_name']) ?>! 👋</h1>
                    <p class="welcome-subtitle" style="font-size: 14px; opacity: 0.95;">Track your academic progress and view your detailed grade breakdown</p>
                </div>
            </div>
        </section>

        <!-- Stats Cards -->
        <section class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $enrolled_classes->num_rows ?></div>
                        <div class="stat-label">Enrolled Classes</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" id="stat-passed">-</div>
                        <div class="stat-label">Passed</div>
                    </div>
                </div>
                
                <div class="stat-card" onclick="openPendingActivitiesModal()" style="cursor: pointer; transition: all 0.3s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.05)'">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" id="stat-pending">-</div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" id="stat-gpa">-</div>
                        <div class="stat-label">GPA</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Enrolled Classes Section -->
        <section class="classes-section" style="margin-top: 2rem;">
            <div class="section-header" style="margin-bottom: 1.5rem;">
                <h2 class="section-title" style="font-size: 20px; font-weight: 700; color: #003082; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-clipboard-list"></i>
                    My Enrolled Classes
                </h2>
            </div>

            <?php if ($enrolled_classes->num_rows > 0): ?>
                <div class="classes-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
                    <?php 
                    $enrolled_classes->data_seek(0);
                    while($class = $enrolled_classes->fetch_assoc()): 
                    ?>
                    <div class="class-card" data-class-code="<?= htmlspecialchars($class['class_code']) ?>" style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); overflow: hidden; transition: all 0.3s ease; border-left: 4px solid #003082;">
                        <div class="class-card-header" style="background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%); padding: 1rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                            <div class="class-code-badge" style="background: #003082; color: white; padding: 0.5rem 1rem; border-radius: 8px; font-size: 13px; font-weight: 600;">
                                <?= htmlspecialchars($class['course_code']) ?>
                            </div>
                            <div class="class-units" style="background: #D4AF37; color: #003082; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                <?= $class['units'] ?> unit<?= $class['units'] > 1 ? 's' : '' ?>
                            </div>
                        </div>
                        
                        <div class="class-card-body" style="padding: 1.25rem;">
                            <h3 class="class-title" style="font-size: 16px; font-weight: 600; color: #003082; margin-bottom: 0.75rem; line-height: 1.4;"><?= htmlspecialchars($class['course_title']) ?></h3>
                            
                            <div class="class-info" style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 13px; color: #6b7280;">
                                <div class="class-info-item" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-users" style="color: #003082; width: 16px;"></i>
                                    <span><strong>Section:</strong> <?= htmlspecialchars($class['section']) ?></span>
                                </div>
                                <div class="class-info-item" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-chalkboard-teacher" style="color: #003082; width: 16px;"></i>
                                    <span><strong>Faculty:</strong> <?= htmlspecialchars($class['faculty_name']) ?></span>
                                </div>
                                <div class="class-info-item" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-door-open" style="color: #003082; width: 16px;"></i>
                                    <span><strong>Room:</strong> <?= htmlspecialchars($class['room']) ?></span>
                                </div>
                                <div class="class-info-item" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-calendar" style="color: #003082; width: 16px;"></i>
                                    <span><strong>Schedule:</strong> <?= htmlspecialchars($class['schedule']) ?></span>
                                </div>
                            </div>

                            <!-- Grade Preview (will be populated by JS) -->
                            <div class="grade-preview" id="grade-preview-<?= htmlspecialchars($class['class_code']) ?>" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                                <div class="grade-preview-content" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; text-align: center;">
                                    <!-- Midterm -->
                                    <div class="grade-item" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px;">
                                        <div style="font-size: 11px; color: #6b7280; font-weight: 600; margin-bottom: 0.75rem; letter-spacing: 0.5px;">MIDTERM</div>
                                        <div style="font-size: 13px; color: #9ca3af; font-weight: 500; margin-bottom: 0.5rem;">(40%)</div>
                                        <div class="midterm-percentage" style="font-size: 16px; font-weight: 700; color: #003082; margin-bottom: 0.25rem;">-</div>
                                        <div class="midterm-grade" style="font-size: 13px; font-weight: 600; color: #6b7280;">-</div>
                                    </div>
                                    
                                    <!-- Finals -->
                                    <div class="grade-item" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px;">
                                        <div style="font-size: 11px; color: #6b7280; font-weight: 600; margin-bottom: 0.75rem; letter-spacing: 0.5px;">FINALS</div>
                                        <div style="font-size: 13px; color: #9ca3af; font-weight: 500; margin-bottom: 0.5rem;">(60%)</div>
                                        <div class="finals-percentage" style="font-size: 16px; font-weight: 700; color: #003082; margin-bottom: 0.25rem;">-</div>
                                        <div class="finals-grade" style="font-size: 13px; font-weight: 600; color: #6b7280;">-</div>
                                    </div>
                                    
                                    <!-- Term Grade -->
                                    <div class="grade-item" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px;">
                                        <div style="font-size: 11px; color: #6b7280; font-weight: 600; margin-bottom: 0.75rem; letter-spacing: 0.5px;">TERM GRADE</div>
                                        <div class="term-grade-status" style="font-size: 13px; color: #9ca3af; font-weight: 500; margin-bottom: 0.5rem;">-</div>
                                        <div class="term-grade-percentage" style="font-size: 16px; font-weight: 700; color: #003082; margin-bottom: 0.25rem;">-</div>
                                        <div class="term-grade-value" style="font-size: 13px; font-weight: 600; color: #6b7280;">-</div>
                                    </div>
                                </div>
                                
                                <div class="grade-preview-loading" style="display: none; text-align: center; padding: 1rem; color: #6b7280; font-size: 13px;">
                                    <i class="fas fa-spinner fa-spin" style="margin-right: 0.5rem;"></i>
                                    <span>Loading grades...</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="class-card-footer" style="padding: 0 1.25rem 1.25rem; display: flex; gap: 10px; flex-wrap: wrap;">
                            <button class="btn-view-grades" onclick="viewClassGrades('<?= htmlspecialchars($class['class_code']) ?>', '<?= htmlspecialchars(addslashes($class['course_title'])) ?>')" style="flex: 1; background: linear-gradient(135deg, #003082 0%, #0047ab 100%); color: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.5rem;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0, 48, 130, 0.3)'" onmouseout="this.style.boxShadow='none'">
                                <i class="fas fa-chart-bar"></i>
                                View Detailed Grades
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="background: white; border-radius: 12px; padding: 3rem; text-align: center; color: #6b7280;">
                    <div class="empty-icon" style="font-size: 48px; margin-bottom: 1rem; opacity: 0.5;">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3 class="empty-title" style="font-size: 18px; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">No Enrolled Classes</h3>
                    <p class="empty-description">You are not currently enrolled in any classes</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Grade Details Modal -->
    <div id="gradeModal" class="modal">
        <div class="modal-content grade-modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalClassTitle">Class Grades</h2>
                <button class="modal-close" onclick="closeGradeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" id="modalGradeContent">
                <!-- Content will be loaded via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Pending Activities Modal -->
    <div id="pendingActivitiesModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 class="modal-title" style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-hourglass-half" style="color: #f59e0b;"></i>
                    Pending Activities
                </h2>
                <button class="modal-close" onclick="closePendingActivitiesModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" id="pendingActivitiesContent">
                <div style="text-align: center; padding: 2rem; color: #6b7280;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 1rem;"></i>
                    <p>Loading pending activities...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Pass PHP data to JavaScript
        window.studentData = {
            studentId: '<?= htmlspecialchars($student_id) ?>',
            csrfToken: '<?= $_SESSION['csrf_token'] ?>'
        };
    </script>
    <script src="assets/js/student_dashboard.js?v=<?= time() ?>"></script>
    <script>
    console.log('CSRF Token:', window.studentData.csrfToken);
    console.log('Student ID:', window.studentData.studentId);
</script>
</body>
</html>