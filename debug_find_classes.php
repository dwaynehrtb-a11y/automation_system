<?php
require_once 'config/db.php';

echo "<h2>All Available Classes</h2>";

// Get all classes
$query = "SELECT * FROM class ORDER BY class_code LIMIT 50";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #1e40af; color: white;'>";
    echo "<th>Class Code</th><th>Course Code</th><th>Section</th><th>Year Level</th><th>Academic Year</th><th>Term</th><th>Faculty ID</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>{$row['class_code']}</strong></td>";
        echo "<td>{$row['course_code']}</td>";
        echo "<td>{$row['section']}</td>";
        echo "<td>{$row['year_level']}</td>";
        echo "<td>{$row['academic_year']}</td>";
        echo "<td>{$row['term']}</td>";
        echo "<td>{$row['faculty_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No classes found</p>";
}

echo "<hr>";
echo "<h2>Classes with 'INF221' Course Code</h2>";

$query = "SELECT * FROM class WHERE course_code LIKE '%INF221%' OR class_code LIKE '%INF221%'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div style='padding: 10px; border: 2px solid #3b82f6; margin: 10px 0; background: #eff6ff;'>";
        echo "<strong>Class Code:</strong> {$row['class_code']}<br>";
        echo "<strong>Course Code:</strong> {$row['course_code']}<br>";
        
        // Count students
        $classCode = $row['class_code'];
        $studentQuery = "SELECT COUNT(*) as count FROM enrollment WHERE class_code = '$classCode'";
        $studentResult = $conn->query($studentQuery);
        $studentCount = $studentResult->fetch_assoc()['count'];
        echo "<strong>Students:</strong> {$studentCount}<br>";
        
        // Check components
        $compQuery = "SELECT * FROM grading_components WHERE class_code = '$classCode'";
        $compResult = $conn->query($compQuery);
        if ($compResult && $compResult->num_rows > 0) {
            echo "<strong>Components:</strong> ";
            $comps = [];
            while ($comp = $compResult->fetch_assoc()) {
                $comps[] = $comp['component_name'] . " (" . $comp['percentage'] . "%)";
            }
            echo implode(', ', $comps);
        } else {
            echo "<strong>Components:</strong> <span style='color: red;'>None configured</span>";
        }
        echo "</div>";
    }
} else {
    echo "<p>No classes found with INF221</p>";
}

echo "<hr>";
echo "<h2>Classes with CCDATRCL Pattern</h2>";

$query = "SELECT * FROM class WHERE class_code LIKE '%CCDATRCL%' OR course_code LIKE '%CCDATRCL%'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div style='padding: 10px; border: 2px solid #10b981; margin: 10px 0; background: #d1fae5;'>";
        echo "<strong>Class Code:</strong> {$row['class_code']}<br>";
        echo "<strong>Course Code:</strong> {$row['course_code']}<br>";
        echo "</div>";
    }
} else {
    echo "<p>No classes found with CCDATRCL pattern</p>";
}

$conn->close();
?>
