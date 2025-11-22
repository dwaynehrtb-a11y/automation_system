<?php
/**
 * Admin AJAX - Decrypt/Encrypt Student Data
 * Handles bulk decryption and encryption of student records
 */

define('SYSTEM_ACCESS', true);
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/encryption.php';

header('Content-Type: application/json');

// Require admin access
requireAdmin();

// Verify CSRF token
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid security token. Please refresh the page and try again.'
    ]);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'decrypt_all') {
        // Decrypt all encrypted student data in database
        Encryption::init();
        
        $result = $conn->query("SELECT student_id, first_name, last_name, email, birthday FROM student");
        
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        
        $count = 0;
        $errors = [];
        $skipped = 0;
        
        while ($student = $result->fetch_assoc()) {
            try {
                // Check if data is actually encrypted (valid base64)
                $is_encrypted = function($data) {
                    if (empty($data)) return false;
                    if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $data)) return false;
                    if ((strlen($data) % 4) != 0) return false;
                    $decoded = base64_decode($data, true);
                    return $decoded !== false;
                };
                
                // Skip if not encrypted
                if (!$is_encrypted($student['first_name']) && 
                    !$is_encrypted($student['last_name']) && 
                    !$is_encrypted($student['email']) && 
                    !$is_encrypted($student['birthday'])) {
                    $skipped++;
                    continue;
                }
                
                // Decrypt fields (only if they are encrypted)
                $first_name = (!empty($student['first_name']) && $is_encrypted($student['first_name'])) ? 
                    Encryption::decrypt($student['first_name']) : $student['first_name'];
                $last_name = (!empty($student['last_name']) && $is_encrypted($student['last_name'])) ? 
                    Encryption::decrypt($student['last_name']) : $student['last_name'];
                $email = (!empty($student['email']) && $is_encrypted($student['email'])) ? 
                    Encryption::decrypt($student['email']) : $student['email'];
                $birthday = (!empty($student['birthday']) && $is_encrypted($student['birthday'])) ? 
                    Encryption::decrypt($student['birthday']) : $student['birthday'];
                
                // Update database with decrypted values
                $stmt = $conn->prepare("UPDATE student SET first_name=?, last_name=?, email=?, birthday=? WHERE student_id=?");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("sssss", $first_name, $last_name, $email, $birthday, $student['student_id']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                
                $stmt->close();
                $count++;
                
            } catch (Exception $e) {
                $errors[] = "Student {$student['student_id']}: " . $e->getMessage();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => $count > 0 ? "Decrypted $count student records" : "All student records are already decrypted",
            'count' => $count,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
        
    } elseif ($action === 'encrypt_all') {
        // Re-encrypt all student data in database
        Encryption::init();
        
        $result = $conn->query("SELECT student_id, first_name, last_name, email, birthday FROM student");
        
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        
        $count = 0;
        $errors = [];
        
        while ($student = $result->fetch_assoc()) {
            try {
                // Encrypt fields
                $first_name = !empty($student['first_name']) ? Encryption::encrypt($student['first_name']) : null;
                $last_name = !empty($student['last_name']) ? Encryption::encrypt($student['last_name']) : null;
                $email = !empty($student['email']) ? Encryption::encrypt($student['email']) : null;
                $birthday = !empty($student['birthday']) ? Encryption::encrypt($student['birthday']) : null;
                
                // Update database with encrypted values
                $stmt = $conn->prepare("UPDATE student SET first_name=?, last_name=?, email=?, birthday=? WHERE student_id=?");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("sssss", $first_name, $last_name, $email, $birthday, $student['student_id']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                
                $stmt->close();
                $count++;
                
            } catch (Exception $e) {
                $errors[] = "Student {$student['student_id']}: " . $e->getMessage();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Encrypted $count student records",
            'count' => $count,
            'errors' => $errors
        ]);
        
    } elseif ($action === 'check_status') {
        // Check if data is encrypted or decrypted
        Encryption::init();
        
        $result = $conn->query("SELECT first_name FROM student WHERE first_name IS NOT NULL LIMIT 1");
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $sample = $row['first_name'];
            
            // Try to decrypt
            try {
                $decrypted = Encryption::decrypt($sample);
                $is_encrypted = true;
            } catch (Exception $e) {
                $is_encrypted = false;
            }
            
            echo json_encode([
                'success' => true,
                'is_encrypted' => $is_encrypted,
                'status' => $is_encrypted ? 'ENCRYPTED' : 'DECRYPTED',
                'sample' => substr($sample, 0, 50) . '...'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No students found'
            ]);
        }
        
    } else {
        throw new Exception("Invalid action: " . htmlspecialchars($action));
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
