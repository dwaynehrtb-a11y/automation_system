<?php
/**
 * Fix Percentage Grades - Convert any percentage values to raw scores
 * 
 * This script checks for grades that appear to be stored as percentages
 * and should be raw scores based on their column's max_score.
 * 
 * Run this once if you have existing data showing percentages instead of raw scores.
 */

require_once 'config/db.php';

echo "<h2>Grade Data Cleanup Tool</h2>";
echo "<p>Checking for grades stored as percentages that should be raw scores...</p>";

// Find grades where the raw_score appears to be a percentage
// (value between 0-100 but the max_score of the column is something like 10)
$query = "
    SELECT 
        sfg.id,
        sfg.student_id,
        sfg.column_id,
        sfg.raw_score,
        gcc.column_name,
        gcc.max_score,
        sfg.class_code
    FROM student_flexible_grades sfg
    INNER JOIN grading_component_columns gcc ON sfg.column_id = gcc.id
    WHERE sfg.raw_score IS NOT NULL
    AND sfg.raw_score > 0
    AND sfg.raw_score > gcc.max_score
    AND sfg.raw_score <= 100
    ORDER BY sfg.class_code, sfg.student_id, gcc.column_name
";

$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$issues = [];
while ($row = $result->fetch_assoc()) {
    $issues[] = $row;
}

if (count($issues) === 0) {
    echo "<p style='color: green;'><strong>✓ No issues found!</strong> All grades appear to be stored correctly as raw scores.</p>";
} else {
    echo "<p style='color: orange;'><strong>⚠ Found " . count($issues) . " grade(s) that may need correction:</strong></p>";
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
    echo "<thead><tr style='background: #f0f0f0;'>
            <th>Class Code</th>
            <th>Student ID</th>
            <th>Column Name</th>
            <th>Current Value</th>
            <th>Max Score</th>
            <th>Suggested Fix</th>
            <th>Action</th>
          </tr></thead>";
    echo "<tbody>";
    
    foreach ($issues as $issue) {
        $current = $issue['raw_score'];
        $max = $issue['max_score'];
        
        // If the value looks like a percentage (e.g., 60 when max is 10)
        // suggest converting it to raw score
        $suggested = null;
        $reasoning = "";
        
        if ($current <= 100 && $current > $max) {
            // Likely stored as percentage - convert to raw score
            $suggested = round(($current / 100) * $max, 2);
            $reasoning = "Convert percentage to raw score: ({$current}/100) × {$max} = {$suggested}";
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($issue['class_code']) . "</td>";
        echo "<td>" . htmlspecialchars($issue['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($issue['column_name']) . "</td>";
        echo "<td style='color: red; font-weight: bold;'>{$current}</td>";
        echo "<td>{$max}</td>";
        echo "<td style='color: green;'>" . ($suggested !== null ? "{$suggested}<br><small>{$reasoning}</small>" : "Manual review needed") . "</td>";
        echo "<td>";
        if ($suggested !== null) {
            echo "<a href='?fix=" . $issue['id'] . "&new_value=" . $suggested . "' 
                     style='background: #3b82f6; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px;'
                     onclick='return confirm(\"Fix this grade?\")'>Fix</a>";
        }
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
    
    echo "<br><form method='GET' onsubmit='return confirm(\"Fix ALL grades listed above? This cannot be undone!\");'>";
    echo "<button type='submit' name='fix_all' value='1' style='background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold;'>Fix All Grades</button>";
    echo "</form>";
}

// Handle fix action
if (isset($_GET['fix']) && isset($_GET['new_value'])) {
    $grade_id = intval($_GET['fix']);
    $new_value = floatval($_GET['new_value']);
    
    $stmt = $conn->prepare("UPDATE student_flexible_grades SET raw_score = ? WHERE id = ?");
    $stmt->bind_param('di', $new_value, $grade_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Grade updated successfully!'); window.location.href = window.location.pathname;</script>";
    } else {
        echo "<script>alert('Error updating grade: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// Handle fix all action
if (isset($_GET['fix_all']) && $_GET['fix_all'] == '1') {
    $fixed_count = 0;
    $error_count = 0;
    
    foreach ($issues as $issue) {
        $current = $issue['raw_score'];
        $max = $issue['max_score'];
        
        if ($current <= 100 && $current > $max) {
            $suggested = round(($current / 100) * $max, 2);
            
            $stmt = $conn->prepare("UPDATE student_flexible_grades SET raw_score = ? WHERE id = ?");
            $stmt->bind_param('di', $suggested, $issue['id']);
            
            if ($stmt->execute()) {
                $fixed_count++;
            } else {
                $error_count++;
            }
            $stmt->close();
        }
    }
    
    echo "<script>alert('Fixed {$fixed_count} grade(s). Errors: {$error_count}'); window.location.href = window.location.pathname;</script>";
}

echo "<hr>";
echo "<p><a href='dashboards/faculty_dashboard.php' style='color: #3b82f6;'>← Back to Faculty Dashboard</a></p>";

$conn->close();
?>
