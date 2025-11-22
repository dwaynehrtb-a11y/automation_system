<?php
// This file has been deprecated. Profile functionality is now integrated into faculty_dashboard.php
header('Location: faculty_dashboard.php#profile');
exit;
?>

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Fetch user details
$stmt = $conn->prepare("
    SELECT 
        id, 
        name, 
        email, 
        employee_id, 
        role, 
        phone,
        department,
        office_location,
        created_at,
        last_login
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found");
}

// For faculty, get assigned subjects/courses
$subjects = [];
if ($user_role === 'faculty') {
    $subject_stmt = $conn->prepare("
        SELECT id, course_code, course_title 
        FROM subjects 
        WHERE faculty_id = ? 
        ORDER BY course_code
    ");
    $subject_stmt->bind_param("i", $user_id);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    while ($row = $subject_result->fetch_assoc()) {
        $subjects[] = $row;
    }
    $subject_stmt->close();
}

// Get notification preferences (if they exist)
$preferences = [];
$pref_stmt = $conn->prepare("
    SELECT 
        email_notifications, 
        dashboard_notifications, 
        class_alerts 
    FROM user_preferences 
    WHERE user_id = ?
");
$pref_stmt->bind_param("i", $user_id);
$pref_stmt->execute();
$pref_result = $pref_stmt->get_result();
if ($pref_result->num_rows > 0) {
    $preferences = $pref_result->fetch_assoc();
} else {
    // Default preferences
    $preferences = [
        'email_notifications' => 1,
        'dashboard_notifications' => 1,
        'class_alerts' => 1
    ];
}
$pref_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Automation System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../faculty/assets/css/faculty_dashboard.css">
    <link rel="stylesheet" href="../faculty/assets/css/flexible_grading.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
            padding: 20px;
            display: block;
        }

        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
            display: block;
        }

        .profile-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .profile-header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
        }

        .profile-info h1 {
            color: #003082;
            margin-bottom: 5px;
        }

        .profile-role {
            color: #666;
            font-size: 14px;
            text-transform: capitalize;
        }

        .profile-back-btn {
            background: #003082;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }

        .profile-back-btn:hover {
            background: #002060;
        }

        .profile-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }

        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-header-left {
                flex-direction: column;
            }
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: block;
            width: 100%;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header h2 {
            color: #003082;
            font-size: 18px;
            margin: 0;
        }

        .card-header i {
            color: #667eea;
            font-size: 20px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f5f5f5;
        }

        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
        }

        .info-value {
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .subjects-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .subject-item {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }

        .subject-code {
            font-weight: 600;
            color: #003082;
        }

        .subject-title {
            color: #666;
            font-size: 13px;
            margin-top: 3px;
        }

        .settings-group {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f5f5f5;
        }

        .settings-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .setting-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
            font-weight: 500;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #667eea;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #003082;
            color: white;
        }

        .btn-primary:hover {
            background: #002060;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .empty-state {
            text-align: center;
            color: #999;
            padding: 20px;
        }

        .empty-state i {
            font-size: 30px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-header h2 {
            color: #003082;
            margin: 0;
            font-size: 20px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-actions button {
            flex: 1;
        }

        /* Button Styles */
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #003082;
            color: white;
        }

        .btn-primary:hover {
            background: #002060;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 48, 130, 0.3);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        /* Action Buttons Container */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        /* Toggle Switch */
        .toggle-switch {
            display: inline-block;
            position: relative;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #667eea;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        /* Settings Row */
        .setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .setting-row:last-child {
            border-bottom: none;
        }

        .setting-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
            font-size: 14px;
            flex: 1;
        }

        .setting-label i {
            color: #667eea;
            width: 18px;
            text-align: center;
        }
    </style>
</head>
<body>
    
    <div class="profile-container">
        <!-- Header with Branding (matching dashboard style) -->
        <div style="background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <h2 style="color: #003082; margin: 0; font-size: 24px;">
                    <i class="fas fa-graduation-cap"></i> NU LIPA - Grading System
                </h2>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="javascript:history.back()" style="text-decoration: none; color: #003082; padding: 8px 15px; border-radius: 5px; border: 1px solid #003082; transition: all 0.3s; cursor: pointer;" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='transparent'">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Header -->
        <div class="profile-header">
            <div class="profile-header-left">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($user['name']) ?></h1>
                    <p class="profile-role"><?= htmlspecialchars($user['role']) ?></p>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="profile-content">
            <!-- Personal Information Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user"></i>
                    <h2>Personal Information</h2>
                </div>
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?= htmlspecialchars($user['name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value">
                        <i class="fas fa-envelope" style="color: #667eea;"></i>
                        <?= htmlspecialchars($user['email']) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Employee ID</span>
                    <span class="info-value"><?= htmlspecialchars($user['employee_id']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Role</span>
                    <span class="info-value">
                        <span class="badge badge-info">
                            <i class="fas fa-user-tie"></i> <?= htmlspecialchars(ucfirst($user['role'])) ?>
                        </span>
                    </span>
                </div>
            </div>

            <!-- Account Status Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-clock"></i>
                    <h2>Account Status</h2>
                </div>
                <div class="info-row">
                    <span class="info-label">Account Created</span>
                    <span class="info-value">
                        <i class="fas fa-calendar" style="color: #667eea;"></i>
                        <?= date('M d, Y', strtotime($user['created_at'])) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Last Login</span>
                    <span class="info-value">
                        <i class="fas fa-sign-in-alt" style="color: #667eea;"></i>
                        <?= $user['last_login'] ? date('M d, Y \a\t H:i', strtotime($user['last_login'])) : 'Never' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="badge badge-success">
                            <i class="fas fa-check-circle"></i> Active
                        </span>
                    </span>
                </div>
            </div>

            <!-- Contact Information Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-phone"></i>
                    <h2>Contact Information</h2>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value">
                        <?= $user['phone'] ? htmlspecialchars($user['phone']) : '<span style="color: #999;">Not provided</span>' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Department</span>
                    <span class="info-value">
                        <?= $user['department'] ? htmlspecialchars($user['department']) : '<span style="color: #999;">Not assigned</span>' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Office Location</span>
                    <span class="info-value">
                        <?= $user['office_location'] ? htmlspecialchars($user['office_location']) : '<span style="color: #999;">Not assigned</span>' ?>
                    </span>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-secondary" onclick="openEditContactModal()">
                        <i class="fas fa-edit"></i> Edit Contact
                    </button>
                </div>
            </div>

            <!-- Department/Assignment Card (Faculty Only) -->
            <?php if ($user_role === 'faculty'): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-book"></i>
                    <h2>Assigned Subjects</h2>
                </div>
                <?php if (!empty($subjects)): ?>
                    <div class="subjects-list">
                        <?php foreach ($subjects as $subject): ?>
                        <div class="subject-item">
                            <div class="subject-code"><?= htmlspecialchars($subject['course_code']) ?></div>
                            <div class="subject-title"><?= htmlspecialchars($subject['course_title']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No subjects assigned</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Account Settings Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-cog"></i>
                    <h2>Notification Preferences</h2>
                </div>
                
                <div class="settings-group">
                    <h4 style="color: #333; margin-bottom: 12px; font-size: 13px; font-weight: 600;">Notifications</h4>
                    
                    <div class="setting-row">
                        <span class="setting-label">
                            <i class="fas fa-envelope"></i> Email Notifications
                        </span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="email_notifications" 
                                <?= ($preferences['email_notifications'] ?? 1) ? 'checked' : '' ?> 
                                onchange="updatePreference('email_notifications')">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="setting-row">
                        <span class="setting-label">
                            <i class="fas fa-bell"></i> Dashboard Notifications
                        </span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="dashboard_notifications" 
                                <?= ($preferences['dashboard_notifications'] ?? 1) ? 'checked' : '' ?> 
                                onchange="updatePreference('dashboard_notifications')">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="setting-row">
                        <span class="setting-label">
                            <i class="fas fa-exclamation-circle"></i> Class Alerts
                        </span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="class_alerts" 
                                <?= ($preferences['class_alerts'] ?? 1) ? 'checked' : '' ?> 
                                onchange="updatePreference('class_alerts')">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Security & Password Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-lock"></i>
                    <h2>Security</h2>
                </div>
                <div class="info-row">
                    <span class="info-label">Password</span>
                    <span class="info-value">
                        <span style="color: #999;">••••••••</span>
                    </span>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openChangePasswordModal()">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                    <a href="../auth/logout.php" class="btn btn-secondary" style="text-decoration: none;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-key"></i> Change Password</h2>
                <button class="modal-close" onclick="closeChangePasswordModal()">×</button>
            </div>
            <form onsubmit="handleChangePassword(event)">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" id="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" id="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" id="confirm_password" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeChangePasswordModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Contact Modal -->
    <div id="editContactModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Contact Information</h2>
                <button class="modal-close" onclick="closeEditContactModal()">×</button>
            </div>
            <form onsubmit="handleUpdateContact(event)">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" id="department" value="<?= htmlspecialchars($user['department'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Office Location</label>
                    <input type="text" id="office_location" value="<?= htmlspecialchars($user['office_location'] ?? '') ?>">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditContactModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openChangePasswordModal() {
            document.getElementById('changePasswordModal').classList.add('show');
        }

        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').classList.remove('show');
        }

        async function handleChangePassword(event) {
            event.preventDefault();

            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                alert('New passwords do not match!');
                return;
            }

            if (newPassword.length < 8) {
                alert('New password must be at least 8 characters long!');
                return;
            }

            try {
                const response = await fetch('../auth/process_change_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `current_password=${encodeURIComponent(currentPassword)}&new_password=${encodeURIComponent(newPassword)}`
                });

                const data = await response.json();

                if (data.success) {
                    alert('Password changed successfully!');
                    closeChangePasswordModal();
                    document.getElementById('current_password').value = '';
                    document.getElementById('new_password').value = '';
                    document.getElementById('confirm_password').value = '';
                } else {
                    alert(data.message || 'Failed to change password');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while changing password');
            }
        }

        // Edit Contact Modal
        function openEditContactModal() {
            document.getElementById('editContactModal').classList.add('show');
        }

        function closeEditContactModal() {
            document.getElementById('editContactModal').classList.remove('show');
        }

        async function handleUpdateContact(event) {
            event.preventDefault();

            const phone = document.getElementById('phone').value;
            const department = document.getElementById('department').value;
            const officeLocation = document.getElementById('office_location').value;

            try {
                const response = await fetch('../ajax/update_profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `phone=${encodeURIComponent(phone)}&department=${encodeURIComponent(department)}&office_location=${encodeURIComponent(officeLocation)}`
                });

                const data = await response.json();

                if (data.success) {
                    alert('Contact information updated successfully!');
                    closeEditContactModal();
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update contact information');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while updating contact information');
            }
        }

        // Update preferences
        async function updatePreference(preference) {
            const checkbox = document.getElementById(preference);
            const value = checkbox.checked ? 1 : 0;

            try {
                const response = await fetch('../ajax/update_preferences.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `preference=${encodeURIComponent(preference)}&value=${value}`
                });

                const data = await response.json();

                if (!data.success) {
                    console.error('Failed to update preference:', data.message);
                    checkbox.checked = !checkbox.checked; // Revert change
                }
            } catch (error) {
                console.error('Error:', error);
                checkbox.checked = !checkbox.checked; // Revert change
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const changePasswordModal = document.getElementById('changePasswordModal');
            const editContactModal = document.getElementById('editContactModal');

            if (event.target === changePasswordModal) {
                closeChangePasswordModal();
            }
            if (event.target === editContactModal) {
                closeEditContactModal();
            }
        }
    </script>
</body>
</html>
