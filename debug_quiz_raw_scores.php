<?php
require_once 'config/db.php';

echo "<h2>Debug: Student Flexible Grades - Raw Score Analysis</h2>";
echo "<p><strong>Class:</strong> CCDATRCL | <strong>Component:</strong> Quiz</p>";

// Get Quiz component data from student_flexible_grades
$query = "
    SELECT 
        gcc.id as column_id,
        gcc.column_name,
        gcc.max_score,
        gcc.order_index,
        gc.component_name,
        gc.id as component_id,
        gc.class_code,
        s.student_id,
        CONCAT(s.last_name, ', ', s.first_name) as student_name,
        sfg.raw_score,
        sfg.id as grade_id,
        sfg.updated_at
    FROM grading_component_columns gcc
    INNER JOIN grading_components gc ON gcc.component_id = gc.id
    LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
    LEFT JOIN student s ON sfg.student_id = s.student_id
    WHERE gc.component_name = 'Quiz'
    AND gc.class_code = 'CCDATRCL'
    ORDER BY s.student_id, gcc.order_index
";

$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error);
}

echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 13px;'>";
echo "<thead>";
echo "<tr style='background: #1e40af; color: white;'>";
echo "<th style='padding: 10px;'>Student ID</th>";
echo "<th style='padding: 10px;'>Student Name</th>";
echo "<th style='padding: 10px;'>Quiz Item</th>";
echo "<th style='padding: 10px;'>Max Score</th>";
echo "<th style='padding: 10px;'>Raw Score (DB Value)</th>";
echo "<th style='padding: 10px;'>Expected Range</th>";
echo "<th style='padding: 10px;'>Analysis</th>";
echo "<th style='padding: 10px;'>Updated At</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

$count = 0;
$issueCount = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    $studentId = $row['student_id'] ?? 'N/A';
    $studentName = $row['student_name'] ?? 'N/A';
    $columnName = $row['column_name'];
    $maxScore = floatval($row['max_score']);
    $rawScore = $row['raw_score'];
    $updatedAt = $row['updated_at'] ?? 'N/A';
    
    // Determine status
    $status = '';
    $bgColor = '#fff';
    $expectedRange = "0 - $maxScore";
    
    if ($rawScore === null || $rawScore === '') {
        $status = '⚪ No grade entered';
        $bgColor = '#f3f4f6';
    } else {
        $numVal = floatval($rawScore);
        
        if ($numVal > $maxScore && $numVal <= 100) {
            $status = "❌ <strong>STORED AS PERCENTAGE</strong><br>Value: $numVal (should be 0-$maxScore)<br>Likely percentage: " . number_format(($numVal / 100) * $maxScore, 2) . "/$maxScore";
            $bgColor = '#fee2e2';
            $issueCount++;
        } elseif ($numVal <= $maxScore && $numVal >= 0) {
            $status = '✅ Correct raw score';
            $bgColor = '#d1fae5';
        } elseif ($numVal > 100) {
            $status = "⚠️ Value ($numVal) exceeds 100 - unusual";
            $bgColor = '#fef3c7';
        } else {
            $status = "⚠️ Negative value: $numVal";
            $bgColor = '#fef3c7';
        }
    }
    
    echo "<tr style='background: $bgColor;'>";
    echo "<td style='padding: 8px;'>$studentId</td>";
    echo "<td style='padding: 8px;'>$studentName</td>";
    echo "<td style='padding: 8px;'><strong>$columnName</strong></td>";
    echo "<td style='padding: 8px; text-align: center;'>$maxScore</td>";
    echo "<td style='padding: 8px; text-align: center; font-weight: bold; font-size: 15px;'>$rawScore</td>";
    echo "<td style='padding: 8px; text-align: center;'>$expectedRange</td>";
    echo "<td style='padding: 8px;'>$status</td>";
    echo "<td style='padding: 8px; font-size: 11px;'>$updatedAt</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";

echo "<div style='margin-top: 20px; padding: 15px; background: #f9fafb; border-left: 4px solid #3b82f6;'>";
echo "<p><strong>📊 Summary:</strong></p>";
echo "<ul>";
echo "<li>Total grade records: <strong>$count</strong></li>";
echo "<li>Issues found (values stored as percentages): <strong style='color: " . ($issueCount > 0 ? '#dc2626' : '#10b981') . ";'>$issueCount</strong></li>";
echo "</ul>";

if ($issueCount > 0) {
    echo "<p style='color: #dc2626; font-weight: bold;'>⚠️ ACTION REQUIRED: Some values are stored as percentages instead of raw scores!</p>";
    echo "<p>The frontend is correctly reading from <code>student_flexible_grades.raw_score</code>, but the <strong>database contains percentage values</strong> instead of raw scores.</p>";
} else {
    echo "<p style='color: #10b981; font-weight: bold;'>✅ All values are stored correctly as raw scores.</p>";
}
echo "</div>";

$result->close();
$conn->close();
?>
