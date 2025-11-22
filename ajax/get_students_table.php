<?php
define('SYSTEM_ACCESS', true);
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/encryption.php';

// Validate AJAX request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Check session and CSRF
requireAdmin();
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF token validation failed']);
    exit;
}

try {
    Encryption::init();
    
    // Get search and filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    
    // Build the query
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
    ";
    
    // Add search filter
    if (!empty($search)) {
        $students_query .= " WHERE (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?)";
    }
    
    $students_query .= " GROUP BY s.student_id, s.first_name, s.last_name, s.middle_initial, s.email, s.birthday, s.status, s.account_status, s.must_change_password, s.first_login_at, s.enrollment_date
    ORDER BY s.enrollment_date DESC, s.last_name, s.first_name";
    
    // Prepare and execute query
    $stmt = $conn->prepare($students_query);
    
    if (!empty($search)) {
        $search_param = '%' . $search . '%';
        $stmt->bind_param('ssss', $search_param, $search_param, $search_param, $search_param);
    }
    
    $stmt->execute();
    $students_result = $stmt->get_result();
    
    // Helper function to check if data is encrypted
    $is_encrypted = function($data) {
        if (empty($data)) return false;
        if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $data)) return false;
        if ((strlen($data) % 4) != 0) return false;
        $decoded = base64_decode($data, true);
        return $decoded !== false;
    };
    
    $html = '';
    
    if ($students_result && $students_result->num_rows > 0) {
        while($student = $students_result->fetch_assoc()) {
            // Apply status filter
            if (!empty($status_filter)) {
                if ($status_filter === 'pending' && $student['must_change_password'] != 1) continue;
                if ($status_filter === 'active' && ($student['account_status'] !== 'active' || $student['must_change_password'] == 1)) continue;
                if ($status_filter === 'inactive' && $student['account_status'] === 'active') continue;
                if (($status_filter === 'graduated' || $status_filter === 'suspended') && $student['account_status'] !== $status_filter) continue;
            }
            
            // Decrypt fields if encrypted
            if ($is_encrypted($student['first_name'])) {
                try {
                    $student['first_name'] = Encryption::decrypt($student['first_name']);
                } catch (Exception $e) {
                    // Keep original if decryption fails
                }
            }
            
            if ($is_encrypted($student['last_name'])) {
                try {
                    $student['last_name'] = Encryption::decrypt($student['last_name']);
                } catch (Exception $e) {
                    // Keep original if decryption fails
                }
            }
            
            if ($is_encrypted($student['email'])) {
                try {
                    $student['email'] = Encryption::decrypt($student['email']);
                } catch (Exception $e) {
                    // Keep original if decryption fails
                }
            }
            
            if ($is_encrypted($student['birthday'])) {
                try {
                    $student['birthday'] = Encryption::decrypt($student['birthday']);
                } catch (Exception $e) {
                    // Keep original if decryption fails
                }
            }
            
            // Reconstruct full_name
            $student['full_name'] = $student['last_name'] . ', ' . $student['first_name'];
            if (!empty($student['middle_initial'])) {
                $student['full_name'] .= ' ' . $student['middle_initial'] . '.';
            }
            
            // Determine account status badge
            if ($student['account_status'] == 'inactive' || $student['account_status'] == 'suspended') {
                $account_status_badge = '<span class="badge badge-danger"><i class="fas fa-ban"></i> Inactive</span>';
            } elseif ($student['must_change_password'] == 1 && $student['first_login_at'] == null) {
                $account_status_badge = '<span class="badge badge-warning"><i class="fas fa-clock"></i> Pending Activation</span>';
            } elseif ($student['account_status'] == 'graduated') {
                $account_status_badge = '<span class="badge badge-info"><i class="fas fa-graduation-cap"></i> Graduated</span>';
            } elseif ($student['account_status'] == 'suspended') {
                $account_status_badge = '<span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Suspended</span>';
            } else {
                $account_status_badge = '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>';
                if (!empty($student['first_login_at'])) {
                    $account_status_badge .= '<div style="font-size: 0.85em; opacity: 0.8; margin-top: 4px;">First login: ' . date('M d, Y', strtotime($student['first_login_at'])) . '</div>';
                }
            }
            
            // Determine enrollment status
            if ($student['status'] == 'pending') {
                $enrollment_status_badge = '<span class="badge badge-info"><i class="fas fa-hourglass-start"></i> Pending</span>';
            } else {
                $enrollment_status_badge = '<span class="badge badge-success"><i class="fas fa-check"></i> Active</span>';
            }
            
            // Build row HTML
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($student['student_id']) . '</strong></td>
                <td>' . htmlspecialchars($student['full_name']) . '</td>
                <td><small>' . htmlspecialchars($student['email']) . '</small></td>
                <td>' . ($student['birthday'] !== 'Jan 1, 1970' ? date('M d, Y', strtotime($student['birthday'])) : 'Jan 1, 1970') . '</td>
                <td>' . $account_status_badge . '</td>
                <td>' . $enrollment_status_badge . '<br><small>' . htmlspecialchars($student['enrolled_classes']) . ' ' . ($student['enrolled_classes'] == 1 ? 'class' : 'classes') . '</small></td>
                <td>
                    <div style="display: flex; gap: 6px; justify-content: center;">
                        <button class="btn-sm" onclick="editStudent(\'' . htmlspecialchars($student['student_id']) . '\')" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-sm btn-danger" onclick="deleteStudent(\'' . htmlspecialchars($student['student_id']) . '\')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>';
        }
    } else {
        $html = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #999;">No students found</td></tr>';
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_students_table.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error loading students: ' . $e->getMessage()
    ]);
}
?>
