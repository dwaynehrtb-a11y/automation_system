<?php
// Simulate what the student AJAX would return
define('SYSTEM_ACCESS', true);
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/encryption.php';
require_once '../includes/GradesModel.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Simulating Student AJAX Response</h2>";
echo "<pre>";

$student_id = '2022-126653';
$class_code_search = '%CCPRGG1L%';

// Find the exact class code
$find = "SELECT class_code FROM class_enrollments ce JOIN class c ON ce.class_code = c.class_code WHERE ce.student_id=? AND c.course_code LIKE ?";
$stmt = $conn->prepare($find);
$stmt->bind_param("ss", $student_id, $class_code_search);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo "No class found\n";
    exit;
}

$class_code = $row['class_code'];
echo "Class: $class_code\n";
echo "Student: $student_id\n\n";

// Now query exactly what get_grades.php would query
$query = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade, grade_status, is_encrypted FROM grade_term WHERE student_id = ? AND class_code = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$grade_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$grade_row) {
    echo "No grade record found\n";
} else {
    echo "=== RAW DATABASE VALUES ===\n";
    echo "Midterm %: " . $grade_row['midterm_percentage'] . "\n";
    echo "Finals %: " . $grade_row['finals_percentage'] . "\n";
    echo "Term %: " . $grade_row['term_percentage'] . "\n";
    echo "Term Grade: " . $grade_row['term_grade'] . "\n";
    echo "Status: " . $grade_row['grade_status'] . "\n";
    echo "Encrypted: " . $grade_row['is_encrypted'] . "\n\n";
    
    // Process like get_grades.php does
    $is_encrypted = intval($grade_row['is_encrypted']) === 1;
    
    if ($is_encrypted) {
        echo "⚠️ Grades are encrypted!\n";
    } else {
        echo "✅ Grades are NOT encrypted\n\n";
        
        $midterm_pct = floatval($grade_row['midterm_percentage'] ?? 0);
        $finals_pct = floatval($grade_row['finals_percentage'] ?? 0);
        $term_pct = floatval($grade_row['term_percentage'] ?? 0);
        $term_grade = ($grade_row['term_grade'] !== null && $grade_row['term_grade'] !== '') ? floatval($grade_row['term_grade']) : 0;
        
        // Convert percentages to grades
        function percentageToGrade($percentage) {
            $p = floatval($percentage);
            if ($p >= 96.0) return 4.0;
            if ($p >= 90.0) return 3.5;
            if ($p >= 84.0) return 3.0;
            if ($p >= 78.0) return 2.5;
            if ($p >= 72.0) return 2.0;
            if ($p >= 66.0) return 1.5;
            if ($p >= 60.0) return 1.0;
            return 0.0;
        }
        
        $midterm_grade = percentageToGrade($midterm_pct);
        $finals_grade = percentageToGrade($finals_pct);
        
        echo "=== CONVERTED GRADES ===\n";
        echo "Midterm: " . $midterm_pct . "% → Grade " . $midterm_grade . "\n";
        echo "Finals: " . $finals_pct . "% → Grade " . $finals_grade . "\n";
        echo "Term: " . $term_pct . "% → Grade " . $term_grade . "\n\n";
        
        echo "=== WHAT AJAX WOULD RETURN ===\n";
        $response = [
            'success' => true,
            'midterm_grade' => $midterm_grade,
            'midterm_percentage' => $midterm_pct,
            'finals_grade' => $finals_grade,
            'finals_percentage' => $finals_pct,
            'term_percentage' => $term_pct,
            'term_grade' => $term_grade,
            'grade_status' => $grade_row['grade_status'] ?? 'pending',
            'term_grade_hidden' => false,
            'message' => 'Grades have been released'
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
        
        // Check if correct
        if ($midterm_pct == 62.03 && $term_pct == 84.81 && $term_grade == 3.0) {
            echo "✅ ✅ ✅ CORRECT VALUES RETURNED!\n";
        } elseif ($midterm_pct == 55.94 && $term_pct == 82.38 && $term_grade == 2.5) {
            echo "❌ OLD VALUES - Database not updated!\n";
        } else {
            echo "⚠️ Unexpected values\n";
        }
    }
}

$conn->close();
?>
