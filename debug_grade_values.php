<?php
/**
 * DEBUG: Grade Value Mismatch for Student 2025-276819
 */
require_once 'config/db.php';
require_once 'config/encryption.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

echo "=== GRADE VALUE MISMATCH DEBUG ===\n\n";

// Step 1: Check what's in grade_term table
echo "Step 1: Raw data from grade_term table\n";
$stmt = $conn->prepare("
    SELECT 
        id,
        student_id,
        class_code,
        term_grade,
        midterm_percentage,
        finals_percentage,
        term_percentage,
        is_encrypted,
        grade_status
    FROM grade_term
    WHERE student_id = ? AND class_code = ?
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "  ❌ NO RECORD FOUND\n";
} else {
    $row = $result->fetch_assoc();
    echo "  ✓ Record found:\n";
    echo "    term_grade (raw): " . htmlspecialchars($row['term_grade']) . "\n";
    echo "    midterm_percentage: " . htmlspecialchars($row['midterm_percentage']) . "\n";
    echo "    finals_percentage: " . htmlspecialchars($row['finals_percentage']) . "\n";
    echo "    term_percentage: " . htmlspecialchars($row['term_percentage']) . "\n";
    echo "    is_encrypted: " . $row['is_encrypted'] . "\n";
    echo "    grade_status: " . $row['grade_status'] . "\n";
    
    // Try to decrypt if encrypted
    if ($row['is_encrypted'] == 1) {
        echo "\n  Attempting decryption...\n";
        Encryption::init();
        try {
            $decrypted_term_grade = Encryption::decrypt($row['term_grade']);
            $decrypted_midterm = Encryption::decrypt($row['midterm_percentage']);
            $decrypted_finals = Encryption::decrypt($row['finals_percentage']);
            $decrypted_term_pct = Encryption::decrypt($row['term_percentage']);
            
            echo "    term_grade (decrypted): $decrypted_term_grade\n";
            echo "    midterm_percentage (decrypted): $decrypted_midterm\n";
            echo "    finals_percentage (decrypted): $decrypted_finals\n";
            echo "    term_percentage (decrypted): $decrypted_term_pct\n";
        } catch (Exception $e) {
            echo "    Decryption error: " . $e->getMessage() . "\n";
        }
    }
}
$stmt->close();

// Step 2: Check grade_component_items for this student
echo "\n\nStep 2: Grade component data\n";
$stmt = $conn->prepare("
    SELECT 
        gci.student_id,
        gc.component_name,
        gc.weight,
        gci.score,
        gci.max_score
    FROM grade_component_items gci
    JOIN grade_components gc ON gci.component_id = gc.id
    WHERE gci.student_id = ? AND gc.class_code = ?
    ORDER BY gc.category, gc.component_name
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "  No component records found\n";
} else {
    echo "  Found " . $result->num_rows . " components:\n";
    while ($row = $result->fetch_assoc()) {
        echo "    - {$row['component_name']} ({$row['weight']}%): {$row['score']}/{$row['max_score']}\n";
    }
}
$stmt->close();

// Step 3: Check what the API returns
echo "\n\nStep 3: What student API would return\n";
$stmt = $conn->prepare("
    SELECT 
        midterm_percentage,
        finals_percentage,
        term_percentage,
        term_grade,
        grade_status,
        is_encrypted
    FROM grade_term
    WHERE student_id = ? AND class_code = ?
");
$stmt->bind_param('ss', $student_id, $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    $is_encrypted = intval($row['is_encrypted']) === 1;
    
    if ($is_encrypted) {
        echo "  ❌ ENCRYPTED - Would return 0 values (hidden)\n";
    } else {
        echo "  ✓ DECRYPTED - Would return actual values:\n";
        
        // Function to convert percentage to grade
        function percentageToGrade($percentage) {
            $percentage = floatval($percentage);
            if ($percentage >= 96) return 4.0;
            if ($percentage >= 92) return 3.5;
            if ($percentage >= 88) return 3.0;
            if ($percentage >= 84) return 2.5;
            if ($percentage >= 80) return 2.0;
            if ($percentage >= 76) return 1.5;
            if ($percentage >= 72) return 1.0;
            if ($percentage >= 68) return 0.5;
            return 0.0;
        }
        
        $midterm_pct = floatval($row['midterm_percentage'] ?? 0);
        $finals_pct = floatval($row['finals_percentage'] ?? 0);
        $term_pct = floatval($row['term_percentage'] ?? 0);
        $term_grade = floatval($row['term_grade']);
        
        $midterm_grade = percentageToGrade($midterm_pct);
        $finals_grade = percentageToGrade($finals_pct);
        
        echo "    midterm_percentage: $midterm_pct% → grade: $midterm_grade\n";
        echo "    finals_percentage: $finals_pct% → grade: $finals_grade\n";
        echo "    term_percentage: $term_pct% → grade: " . percentageToGrade($term_pct) . "\n";
        echo "    term_grade (stored): $term_grade\n";
        echo "    grade_status: " . $row['grade_status'] . "\n";
    }
} else {
    echo "  No record found\n";
}
$stmt->close();

echo "\n\n=== DIAGNOSIS ===\n";
echo "Comparing faculty values vs database values:\n";
echo "Faculty shows: 74.17% (midterm) | 100% (finals) | 89.67% (term)\n";
echo "Database has: [check above]\n";
echo "Student sees: 23.33% (midterm) | 90% (finals) | 63.33% (term)\n";
echo "\nThe percentages are mismatched between what faculty saved and what database has.\n";

?>
