<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/db.php';

echo "<h2>Finding class codes for student 2022-126653</h2>";
echo "<pre>";

try {
    $query = "SELECT DISTINCT class_code, midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' ORDER BY class_code";
    $result = $conn->query($query);
    
    if (!$result) {
        echo "Query error: " . $conn->error . "\n";
        exit;
    }
    
    if ($result->num_rows == 0) {
        echo "No records found for student 2022-126653\n";
    } else {
        echo "Found " . $result->num_rows . " records:\n";
        while ($row = $result->fetch_assoc()) {
            echo "---\n";
            echo "Class Code: " . $row['class_code'] . "\n";
            echo "Midterm: " . $row['midterm_percentage'] . "%\n";
            echo "Finals: " . $row['finals_percentage'] . "%\n";
            echo "Term %: " . $row['term_percentage'] . "%\n";
            echo "Term Grade: " . $row['term_grade'] . "\n";
        }
    }
    
    // Also check if the class code in database might be incomplete
    echo "\n\n=== All CCPRGG class codes in grade_term ===\n";
    $query2 = "SELECT DISTINCT class_code FROM grade_term WHERE class_code LIKE '%CCPRGG%' LIMIT 10";
    $result2 = $conn->query($query2);
    
    if ($result2->num_rows > 0) {
        while ($row = $result2->fetch_assoc()) {
            echo "- " . $row['class_code'] . "\n";
        }
    } else {
        echo "No CCPRGG classes found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "</pre>";
?>
