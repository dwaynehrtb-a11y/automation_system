<?php
/**
 * Decryption Access Control Middleware
 * Ensures only authorized users can decrypt sensitive data
 */

require_once __DIR__ . '/encryption.php';

class DecryptionAccessControl {
    
    /**
     * Check if user can decrypt student data
     * 
     * @param int $userId Current user ID
     * @param string $userRole Current user role (admin, faculty, student)
     * @param string $studentId Target student ID
     * @param string $dataType Type of data (profile, grades, all)
     * @return bool
     */
    public static function canDecryptStudentData($userId, $userRole, $studentId, $dataType = 'profile', $conn = null) {
        // ADMIN: Can decrypt any student's data
        if ($userRole === 'admin') {
            self::logDecryptionAccess($userId, 'admin', $studentId, $dataType, 'ALLOWED');
            return true;
        }
        
        // STUDENT: Can only decrypt their own data
        if ($userRole === 'student') {
            if ($studentId === (string)$_SESSION['student_id'] ?? null) {
                self::logDecryptionAccess($userId, 'student', $studentId, $dataType, 'ALLOWED');
                return true;
            }
            self::logDecryptionAccess($userId, 'student', $studentId, $dataType, 'DENIED');
            return false;
        }
        
        // FACULTY: Can decrypt students in their enrolled classes
        if ($userRole === 'faculty' && $conn) {
            // Check if student is enrolled in any of faculty's classes
            $stmt = $conn->prepare("
                SELECT ce.student_id
                FROM class_enrollments ce
                INNER JOIN class c ON ce.class_code = c.class_code
                WHERE ce.student_id = ? AND c.faculty_id = ? AND ce.status = 'enrolled'
                LIMIT 1
            ");
            $stmt->bind_param('si', $studentId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            if ($result->num_rows > 0) {
                self::logDecryptionAccess($userId, 'faculty', $studentId, $dataType, 'ALLOWED');
                return true;
            }
            
            self::logDecryptionAccess($userId, 'faculty', $studentId, $dataType, 'DENIED');
            return false;
        }
        
        // Fallback
        self::logDecryptionAccess($userId, $userRole, $studentId, $dataType, 'DENIED');
        return false;
    }
    
    /**
     * Check if faculty can decrypt grades for their class
     * 
     * @param int $facultyId Faculty ID
     * @param string $classCode Class code
     * @param mysqli $conn Database connection
     * @return bool
     */
    public static function canDecryptClassGrades($facultyId, $classCode, $conn) {
        try {
            // Verify faculty owns this class
            $stmt = $conn->prepare("SELECT faculty_id FROM class WHERE class_code = ? LIMIT 1");
            $stmt->bind_param('s', $classCode);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($result && $result['faculty_id'] == $facultyId) {
                self::logDecryptionAccess($facultyId, 'faculty', $classCode, 'grades', 'ALLOWED');
                return true;
            }
            
            self::logDecryptionAccess($facultyId, 'faculty', $classCode, 'grades', 'DENIED');
            return false;
            
        } catch (Exception $e) {
            error_log('Access check error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if student can decrypt their own grades
     * 
     * @param string $studentId Student ID
     * @param string $classCode Class code
     * @param mysqli $conn Database connection
     * @return bool
     */
    public static function canDecryptOwnGrades($studentId, $classCode, $conn) {
        try {
            // Verify student is enrolled in this class
            $stmt = $conn->prepare("
                SELECT COUNT(*) as enrolled 
                FROM class_enrollments 
                WHERE student_id = ? AND class_code = ? AND status = 'enrolled'
            ");
            $stmt->bind_param('ss', $studentId, $classCode);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($result['enrolled'] > 0) {
                self::logDecryptionAccess($studentId, 'student', $classCode, 'own_grades', 'ALLOWED');
                return true;
            }
            
            self::logDecryptionAccess($studentId, 'student', $classCode, 'own_grades', 'DENIED');
            return false;
            
        } catch (Exception $e) {
            error_log('Access check error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log all decryption access for audit trail
     * 
     * @param mixed $userId User attempting decryption
     * @param string $userRole User role
     * @param string $targetId Student ID or Class code
     * @param string $dataType Type of data being accessed
     * @param string $status ALLOWED or DENIED
     */
    private static function logDecryptionAccess($userId, $userRole, $targetId, $dataType, $status) {
        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $message = "[{$timestamp}] [{$status}] User ID: {$userId} ({$userRole}) accessed {$dataType} for {$targetId} from {$ipAddress}";
        
        error_log($message, 0);
        
        // Optional: Store in database for compliance
        // $this->logToDatabase($message);
    }
    
    /**
     * Throw unauthorized exception
     */
    public static function denyAccess($message = 'Unauthorized access to decrypted data') {
        http_response_code(403);
        throw new Exception($message);
    }
}

?>
