<?php
/**
 * Student Model with Encryption
 * Automatically encrypts/decrypts sensitive fields
 */

require_once __DIR__ . '/../config/encryption.php';
require_once __DIR__ . '/../config/decryption_access.php';

class StudentModel {
    private $conn;
    
    // Fields that should be encrypted
    private static $encryptedFields = [
        'first_name',
        'last_name',
        'email',
        'birthday'
    ];
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get student by ID (with decryption)
     * 
     * @param string $studentId
     * @param int|null $viewerId User ID requesting data
     * @param string|null $viewerRole User role
     * @return array Decrypted student data
     */
    public function getStudentById($studentId, $viewerId = null, $viewerRole = null) {
        try {
            // Check access control if viewer specified
            if ($viewerId !== null && $viewerRole !== null) {
                if (!DecryptionAccessControl::canDecryptStudentData($viewerId, $viewerRole, $studentId, 'profile', $this->conn)) {
                    DecryptionAccessControl::denyAccess('Access denied to student data');
                }
            }
            
            $stmt = $this->conn->prepare("
                SELECT student_id, first_name, last_name, middle_initial, email, birthday, status, enrollment_date
                FROM student 
                WHERE student_id = ?
            ");
            
            $stmt->bind_param('s', $studentId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($result) {
                $result = $this->decryptFields($result);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('StudentModel::getStudentById Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get all students with filters (with decryption)
     * 
     * @param array $filters Status, search terms, etc.
     * @param int $limit
     * @param int $offset
     * @param int|null $viewerId
     * @param string|null $viewerRole
     * @return array
     */
    public function getAllStudents($filters = [], $limit = 100, $offset = 0, $viewerId = null, $viewerRole = null) {
        try {
            // Only admins can view all students
            if ($viewerRole && $viewerRole !== 'admin') {
                return []; // Non-admins get empty list
            }
            
            $where = ['1=1'];
            $params = [];
            $types = '';
            
            if (!empty($filters['status'])) {
                $where[] = 'status = ?';
                $params[] = $filters['status'];
                $types .= 's';
            }
            
            $whereClause = implode(' AND ', $where);
            $query = "
                SELECT student_id, first_name, last_name, email, status, enrollment_date
                FROM student
                WHERE {$whereClause}
                ORDER BY last_name, first_name
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->conn->prepare($query);
            
            if (!empty($params)) {
                $params[] = $limit;
                $params[] = $offset;
                $types .= 'ii';
                $stmt->bind_param($types, ...$params);
            } else {
                $stmt->bind_param('ii', $limit, $offset);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $students = [];
            
            while ($row = $result->fetch_assoc()) {
                $students[] = $this->decryptFields($row);
            }
            
            $stmt->close();
            return $students;
            
        } catch (Exception $e) {
            error_log('StudentModel::getAllStudents Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create student with encryption
     * 
     * @param array $data Student data
     * @return bool
     */
    public function createStudent($data) {
        try {
            $data = $this->encryptFields($data);
            
            $stmt = $this->conn->prepare("
                INSERT INTO student (
                    student_id, first_name, last_name, middle_initial, email, birthday, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                'sssssss',
                $data['student_id'],
                $data['first_name'],
                $data['last_name'],
                $data['middle_initial'],
                $data['email'],
                $data['birthday'],
                $data['status']
            );
            
            if ($stmt->execute()) {
                error_log("Student {$data['student_id']} created with encrypted data");
                $stmt->close();
                return true;
            }
            
            throw new Exception("Insert failed: " . $stmt->error);
            
        } catch (Exception $e) {
            error_log('StudentModel::createStudent Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update student with encryption
     * 
     * @param string $studentId
     * @param array $data Data to update
     * @return bool
     */
    public function updateStudent($studentId, $data) {
        try {
            $data = $this->encryptFields($data);
            
            $updates = [];
            $params = [];
            $types = '';
            
            foreach ($data as $field => $value) {
                if ($field !== 'student_id') {
                    $updates[] = "{$field} = ?";
                    $params[] = $value;
                    $types .= 's';
                }
            }
            
            if (empty($updates)) {
                return false;
            }
            
            $params[] = $studentId;
            $types .= 's';
            
            $query = "UPDATE student SET " . implode(', ', $updates) . " WHERE student_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                error_log("Student {$studentId} updated with encrypted data");
                $stmt->close();
                return true;
            }
            
            throw new Exception("Update failed: " . $stmt->error);
            
        } catch (Exception $e) {
            error_log('StudentModel::updateStudent Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Encrypt specified fields
     */
    private function encryptFields($data) {
        $encrypted = $data;
        
        foreach (self::$encryptedFields as $field) {
            if (isset($encrypted[$field]) && !empty($encrypted[$field])) {
                try {
                    $encrypted[$field] = Encryption::encryptField($encrypted[$field]);
                } catch (Exception $e) {
                    error_log("Failed to encrypt {$field}: " . $e->getMessage());
                    throw $e;
                }
            }
        }
        
        return $encrypted;
    }
    
    /**
     * Decrypt specified fields
     */
    private function decryptFields($data) {
        $decrypted = $data;
        
        foreach (self::$encryptedFields as $field) {
            if (isset($decrypted[$field]) && !empty($decrypted[$field])) {
                try {
                    $decrypted[$field] = Encryption::decryptField($decrypted[$field]);
                } catch (Exception $e) {
                    error_log("Failed to decrypt {$field}: " . $e->getMessage());
                    // Log error but continue - don't expose encryption errors to users
                    $decrypted[$field] = '[DECRYPTION ERROR]';
                }
            }
        }
        
        return $decrypted;
    }
}

?>
