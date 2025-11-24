<?php
require_once 'config/db.php';

echo "<h2>All Components for 24_T1_CCDATRCL_INF221</h2>";

// Check all components for this class
$query = "SELECT * FROM grading_components WHERE class_code = '24_T1_CCDATRCL_INF221'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #1e40af; color: white;'>";
    echo "<th>ID</th><th>Component Name</th><th>Percentage</th><th>Term Type</th><th>Created</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td><strong>{$row['component_name']}</strong></td>";
        echo "<td>{$row['percentage']}%</td>";
        echo "<td>{$row['term_type']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Now checking columns and grades for each component...</h3>";
    
    // Reset result pointer
    $result->data_seek(0);
    
    while ($comp = $result->fetch_assoc()) {
        $compId = $comp['id'];
        $compName = $comp['component_name'];
        
        echo "<hr><h3>📋 Component: {$compName} (ID: {$compId})</h3>";
        
        // Get columns
        $colQuery = "SELECT * FROM grading_component_columns WHERE component_id = $compId ORDER BY order_index";
        $colResult = $conn->query($colQuery);
        
        if ($colResult && $colResult->num_rows > 0) {
            echo "<h4>Columns/Items:</h4>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Column ID</th><th>Name</th><th>Max Score</th><th>Order</th></tr>";
            
            $columnIds = [];
            while ($col = $colResult->fetch_assoc()) {
                echo "<tr><td>{$col['id']}</td><td><strong>{$col['column_name']}</strong></td><td>{$col['max_score']}</td><td>{$col['order_index']}</td></tr>";
                $columnIds[] = $col['id'];
            }
            echo "</table>";
            
            // Check grades for these columns
            if (!empty($columnIds)) {
                $columnList = implode(',', $columnIds);
                $gradeQuery = "SELECT 
                                sfg.student_id,
                                sfg.column_id,
                                sfg.raw_score,
                                gcc.column_name,
                                gcc.max_score,
                                CONCAT(s.last_name, ', ', s.first_name) as student_name
                              FROM student_flexible_grades sfg
                              INNER JOIN grading_component_columns gcc ON sfg.column_id = gcc.id
                              INNER JOIN student s ON sfg.student_id = s.student_id
                              WHERE sfg.column_id IN ($columnList)
                              ORDER BY s.student_id, gcc.order_index";
                
                $gradeResult = $conn->query($gradeQuery);
                
                if ($gradeResult && $gradeResult->num_rows > 0) {
                    echo "<h4>Grades Found:</h4>";
                    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                    echo "<tr style='background: #ddd;'><th>Student ID</th><th>Student Name</th><th>Column</th><th>Max</th><th>Raw Score</th><th>Analysis</th></tr>";
                    
                    while ($grade = $gradeResult->fetch_assoc()) {
                        $rawScore = $grade['raw_score'];
                        $maxScore = $grade['max_score'];
                        $analysis = '';
                        $bgColor = '#fff';
                        
                        if ($rawScore !== null) {
                            if ($rawScore > $maxScore && $rawScore <= 100) {
                                $analysis = '❌ STORED AS PERCENTAGE';
                                $bgColor = '#fee2e2';
                            } elseif ($rawScore <= $maxScore) {
                                $analysis = '✅ Raw score';
                                $bgColor = '#d1fae5';
                            }
                        }
                        
                        echo "<tr style='background: $bgColor;'>";
                        echo "<td>{$grade['student_id']}</td>";
                        echo "<td>{$grade['student_name']}</td>";
                        echo "<td>{$grade['column_name']}</td>";
                        echo "<td>{$maxScore}</td>";
                        echo "<td><strong>{$rawScore}</strong></td>";
                        echo "<td>{$analysis}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p style='color: #dc2626;'>❌ No grades found in student_flexible_grades for this component</p>";
                }
            }
        } else {
            echo "<p style='color: #dc2626;'>❌ No columns/items defined for this component</p>";
        }
    }
    
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ No grading components found for class CCDATRCL!</p>";
    
    // Check if the class exists
    echo "<h3>Checking if class exists...</h3>";
    $classQuery = "SELECT * FROM class WHERE class_code = '24_T1_CCDATRCL_INF221'";
    $classResult = $conn->query($classQuery);
    if ($classResult && $classResult->num_rows > 0) {
        $class = $classResult->fetch_assoc();
        echo "<p>✅ Class exists: {$class['class_code']} - {$class['course_code']}</p>";
    } else {
        echo "<p>❌ Class 24_T1_CCDATRCL_INF221 not found in class table</p>";
    }
}

$conn->close();
?>
