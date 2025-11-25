<?php
/**
 * GradesModel
 * Clean implementation with visibility-aware encryption using is_encrypted flag.
 */
require_once __DIR__ . '/../config/encryption.php';
require_once __DIR__ . '/../config/decryption_access.php';

class GradesModel {
    private $conn;

    private static $encryptedFields = [
        'term_grade',
        'midterm_percentage',
        'finals_percentage',
        'term_percentage',
        'lacking_requirements'
    ];

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getStudentGrades($studentId, $classCode, $viewerId = null, $viewerRole = null) {
        try {
            if ($viewerRole === 'student') {
                if (!DecryptionAccessControl::canDecryptOwnGrades($studentId, $classCode, $this->conn)) {
                    DecryptionAccessControl::denyAccess('Cannot access these grades');
                }
            } elseif ($viewerRole === 'faculty') {
                if (!DecryptionAccessControl::canDecryptClassGrades($viewerId, $classCode, $this->conn)) {
                    DecryptionAccessControl::denyAccess('Cannot access these grades');
                }
            } elseif ($viewerRole !== 'admin') {
                DecryptionAccessControl::denyAccess('Unauthorized');
            }

            $stmt = $this->conn->prepare("SELECT id, student_id, class_code, term_grade, midterm_percentage, finals_percentage, term_percentage, grade_status, lacking_requirements, computed_at, is_encrypted FROM grade_term WHERE student_id = ? AND class_code = ?");
            $stmt->bind_param('ss', $studentId, $classCode);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) { $row = $this->decryptFields($row); }
            return $row;
        } catch (Exception $e) {
            error_log('GradesModel::getStudentGrades Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getClassGrades($classCode, $facultyId, $limit = null, $offset = 0) {
        try {
            if (!DecryptionAccessControl::canDecryptClassGrades($facultyId, $classCode, $this->conn)) {
                DecryptionAccessControl::denyAccess('Cannot access grades for this class');
            }
            $query = "SELECT id, student_id, class_code, term_grade, midterm_percentage, finals_percentage, term_percentage, grade_status, lacking_requirements, computed_at, is_encrypted FROM grade_term WHERE class_code = ? ORDER BY student_id";
            if ($limit !== null) {
                $query .= " LIMIT ? OFFSET ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param('sii', $classCode, $limit, $offset);
            } else {
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param('s', $classCode);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $grades = [];
            while ($row = $res->fetch_assoc()) {
                $grades[] = $this->decryptFields($row);
            }
            $stmt->close();
            return $grades;
        } catch (Exception $e) {
            error_log('GradesModel::getClassGrades Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function saveGrades($gradeData, $facultyId = null) {
        try {
            $visibility = null;
            $visStmt = $this->conn->prepare("SELECT grade_visibility FROM grade_visibility_status WHERE class_code = ? LIMIT 1");
            if ($visStmt) {
                $visStmt->bind_param('s', $gradeData['class_code']);
                $visStmt->execute();
                $visRow = $visStmt->get_result()->fetch_assoc();
                if ($visRow) { $visibility = $visRow['grade_visibility']; }
                $visStmt->close();
            }
            $shouldEncrypt = ($visibility === 'hidden');

            // Include manual freeze flag if present
            $existsStmt = $this->conn->prepare("SELECT id, grade_status, term_grade, is_encrypted, status_manually_set FROM grade_term WHERE student_id = ? AND class_code = ?");
            $existsStmt->bind_param('ss', $gradeData['student_id'], $gradeData['class_code']);
            $existsStmt->execute();
            $existsRes = $existsStmt->get_result();
            $exists = $existsRes->num_rows > 0;
            $existingStatus = null; $existingTermGrade = null; $existingEncrypted = 0; $manualFrozen = false;
            if ($exists) {
                $row = $existsRes->fetch_assoc();
                $existingStatus = $row['grade_status'];
                $existingTermGrade = $row['term_grade'];
                $existingEncrypted = (int)$row['is_encrypted'];
                $manualFrozen = (isset($row['status_manually_set']) && strtolower($row['status_manually_set']) === 'yes');
            }
            $existsStmt->close();
            // Preserve frozen status & term_grade
            if ($exists) {
                if (!isset($gradeData['grade_status']) || $manualFrozen) { $gradeData['grade_status'] = $existingStatus; }
                if ($manualFrozen) { $gradeData['term_grade'] = $existingTermGrade; }
            }

            $isEncryptedFlag = $existingEncrypted;
            if ($shouldEncrypt && $existingEncrypted === 0) { $gradeData = $this->encryptFields($gradeData); $isEncryptedFlag = 1; }
            if (!$shouldEncrypt && $existingEncrypted === 1) { // visibility changed to visible; incoming data presumably plaintext
                $isEncryptedFlag = 0; // controller should have decrypted existing row already
            }

            if ($exists) {
                $stmt = $this->conn->prepare("UPDATE grade_term SET term_grade=?, midterm_percentage=?, finals_percentage=?, term_percentage=?, grade_status=?, lacking_requirements=?, is_encrypted=? WHERE student_id=? AND class_code=?");
                $stmt->bind_param('ssssssiss', $gradeData['term_grade'], $gradeData['midterm_percentage'], $gradeData['finals_percentage'], $gradeData['term_percentage'], $gradeData['grade_status'], $gradeData['lacking_requirements'], $isEncryptedFlag, $gradeData['student_id'], $gradeData['class_code']);
            } else {
                $stmt = $this->conn->prepare("INSERT INTO grade_term (student_id, class_code, term_grade, midterm_percentage, finals_percentage, term_percentage, grade_status, lacking_requirements, is_encrypted) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('ssssssssi', $gradeData['student_id'], $gradeData['class_code'], $gradeData['term_grade'], $gradeData['midterm_percentage'], $gradeData['finals_percentage'], $gradeData['term_percentage'], $gradeData['grade_status'], $gradeData['lacking_requirements'], $isEncryptedFlag);
            }
            if ($stmt->execute()) { error_log("Grades saved for {$gradeData['student_id']} in {$gradeData['class_code']} (is_encrypted={$isEncryptedFlag})" . ($facultyId ? " by faculty {$facultyId}" : '')); $stmt->close(); return true; }
            throw new Exception("Save failed: " . $stmt->error);
        } catch (Exception $e) {
            error_log('GradesModel::saveGrades Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function encryptFields($data) {
        $encrypted = $data;
        foreach (self::$encryptedFields as $field) {
            if (isset($encrypted[$field]) && $encrypted[$field] !== null && $encrypted[$field] !== '') {
                try { $encrypted[$field] = Encryption::encrypt($encrypted[$field]); } catch (Exception $e) { error_log("Failed to encrypt {$field}: " . $e->getMessage()); throw $e; }
            }
        }
        return $encrypted;
    }

    public function decryptFieldsPublic($data) {
        return $this->decryptFields($data);
    }

    private function decryptFields($data) {
        $decrypted = $data;
        $rowEncrypted = isset($data['is_encrypted']) && (int)$data['is_encrypted'] === 1;
        if (!$rowEncrypted) { return $decrypted; }
        foreach (self::$encryptedFields as $field) {
            if (isset($decrypted[$field]) && $decrypted[$field] !== null && $decrypted[$field] !== '') {
                try { $decrypted[$field] = Encryption::decrypt($decrypted[$field]); } catch (Exception $e) { error_log("Failed to decrypt {$field}: " . $e->getMessage()); $decrypted[$field] = '[DECRYPTION ERROR]'; }
            }
        }
        return $decrypted;
    }
}
?>
