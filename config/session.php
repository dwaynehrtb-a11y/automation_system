<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); 
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
}

// Session timeout (in seconds) - 2 hours
define('SESSION_TIMEOUT', 7200);

/**
 * Start secure session
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    startSecureSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['name']) && isset($_SESSION['role']);
}

/**
 * Check if user has admin role
 */
function isAdmin() {
    return isAuthenticated() && $_SESSION['role'] === 'admin';
}

/**
 * Check if user has faculty role
 */
function isFaculty() {
    return isAuthenticated() && $_SESSION['role'] === 'faculty';
}

/**
 * Check if user has student role
 */
function isStudent() {
    return isAuthenticated() && $_SESSION['role'] === 'student';
}

/**
 * FIXED: Check session timeout with proper race condition handling
 */
function checkSessionTimeout() {
    startSecureSession();
    
    if (isset($_SESSION['last_activity'])) {
        $current_time = time();
        $time_diff = $current_time - $_SESSION['last_activity'];
        
        if ($time_diff > SESSION_TIMEOUT) {
            destroySession();
            return false;
        }
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Require admin authentication
 */
function requireAdmin() {
    startSecureSession();
    
    if (!checkSessionTimeout()) {
        redirectToLogin('Session expired. Please login again.');
        return;
    }
    
    if (!isAdmin()) {
        redirectToLogin('Access denied. Admin privileges required.');
        return;
    }
}

/**
 * Require faculty authentication
 */
function requireFaculty() {
    startSecureSession();
    
    if (!checkSessionTimeout()) {
        redirectToLogin('Session expired. Please login again.');
        return;
    }
    
    if (!isFaculty()) {
        redirectToLogin('Access denied. Faculty privileges required.');
        return;
    }
}

/**
 * Require any authentication
 */
function requireAuth() {
    startSecureSession();
    
    if (!checkSessionTimeout()) {
        redirectToLogin('Session expired. Please login again.');
        return;
    }
    
    if (!isAuthenticated()) {
        redirectToLogin('Please login to access this page.');
        return;
    }
}

/**
 * ENHANCED: Redirect to login page with better path detection
 */
function redirectToLogin($message = '') {
    $loginPath = '';
    $current_uri = $_SERVER['REQUEST_URI'] ?? '';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Determine correct path to login based on current location
    if (strpos($current_uri, '/admin/') !== false || strpos($script_name, '/admin/') !== false) {
        $loginPath = '../auth/login.php';
    } elseif (strpos($current_uri, '/dashboards/') !== false || strpos($script_name, '/dashboards/') !== false) {
        $loginPath = '../auth/login.php';
    } elseif (strpos($current_uri, '/reports/') !== false || strpos($script_name, '/reports/') !== false) {
        $loginPath = '../auth/login.php';
    } elseif (strpos($current_uri, '/api/') !== false || strpos($script_name, '/api/') !== false) {
        $loginPath = '../auth/login.php';
    } else {
        $loginPath = 'auth/login.php';
    }
    
    if (!empty($message)) {
        $loginPath .= '?error=' . urlencode($message);
    }
    
    header("Location: $loginPath");
    exit();
}

/**
 * Create user session
 */
function createUserSession($user_data) {
    startSecureSession();
    
    // Regenerate session ID on login for security
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['name'] = $user_data['name'];
    $_SESSION['role'] = $user_data['role'];
    $_SESSION['email'] = $user_data['email'] ?? '';
    $_SESSION['employee_id'] = $user_data['employee_id'] ?? '';
    $_SESSION['student_id'] = $user_data['student_id'] ?? '';
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * ENHANCED: Destroy session and logout with complete cleanup
 */
function destroySession() {
    startSecureSession();
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['name'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'employee_id' => $_SESSION['employee_id'] ?? '',
        'student_id' => $_SESSION['student_id'] ?? '',
        'login_time' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'login_ip' => $_SESSION['login_ip'] ?? 'unknown'
    ];
}
/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token input field
 */
function getCSRFTokenInput() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Get CSRF token value
 */
function getCSRFToken() {
    return generateCSRFToken();
}
?>