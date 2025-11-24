<?php
require_once 'config/db.php';

echo "<h2>Complete Database Investigation - CCDATRCL Quiz Grades</h2>";

// 1. Check if Quiz component exists
echo "<h3>1️⃣ Quiz Component Configuration</h3>";
$query = "SELECT * FROM grading_components WHERE class_code = 'CCDATRCL' AND component_name = 'Quiz'";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $comp = $result->fetch_assoc();
    echo "<p>✅ Component ID: <strong>{$comp['id']}</strong> | Weight: {$comp['percentage']}% | Term: {$comp['term_type']}</p>";
    $componentId = $comp['id'];
} else {
    die("<p>❌ No Quiz component found for CCDATRCL</p>");
}

// 2. Check Quiz columns (items)
echo "<h3>2️⃣ Quiz Items/Columns</h3>";
$query = "SELECT * FROM grading_component_columns WHERE component_id = $componentId ORDER BY order_index";
$result = $conn->query($query);
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Column ID</th><th>Name</th><th>Max Score</th><th>Order</th></tr>";
$columnIds = [];
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['id']}</td><td><strong>{$row['column_name']}</strong></td><td>{$row['max_score']}</td><td>{$row['order_index']}</td></tr>";
    $columnIds[] = $row['id'];
}
echo "</table>";

// 3. Check enrolled students
echo "<h3>3️⃣ Enrolled Students</h3>";
$query = "SELECT s.student_id, CONCAT(s.last_name, ', ', s.first_name) as name 
          FROM enrollment e 
          INNER JOIN student s ON e.student_id = s.student_id 
          WHERE e.class_code = 'CCDATRCL'";
$result = $conn->query($query);
$students = [];
echo "<ul>";
while ($row = $result->fetch_assoc()) {
    echo "<li>{$row['student_id']} - {$row['name']}</li>";
    $students[] = $row['student_id'];
}
echo "</ul>";

// 4. Check student_flexible_grades table for these students and columns
echo "<h3>4️⃣ Student Flexible Grades Records</h3>";
if (!empty($columnIds)) {
    $columnList = implode(',', $columnIds);
    $studentList = "'" . implode("','", $students) . "'";
    
    $query = "SELECT 
                sfg.*,
                gcc.column_name,
                gcc.max_score,
                s.last_name, s.first_name
              FROM student_flexible_grades sfg
              INNER JOIN grading_component_columns gcc ON sfg.column_id = gcc.id
              INNER JOIN student s ON sfg.student_id = s.student_id
              WHERE sfg.column_id IN ($columnList)
              AND sfg.student_id IN ($studentList)
              ORDER BY s.student_id, gcc.order_index";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #ddd;'><th>ID</th><th>Student ID</th><th>Student Name</th><th>Column</th><th>Max Score</th><th>Raw Score</th><th>Updated</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $bg = ($row['raw_score'] > $row['max_score'] && $row['raw_score'] <= 100) ? '#ffe6e6' : '#e6ffe6';
            echo "<tr style='background: $bg;'>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['student_id']}</td>";
            echo "<td>{$row['last_name']}, {$row['first_name']}</td>";
            echo "<td>{$row['column_name']}</td>";
            echo "<td>{$row['max_score']}</td>";
            echo "<td><strong>{$row['raw_score']}</strong></td>";
            echo "<td>{$row['updated_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ NO RECORDS FOUND in student_flexible_grades table!</p>";
        echo "<p>This explains why the grading table shows no data - grades haven't been saved to this table yet.</p>";
    }
}

// 5. Check if there are ANY records in student_flexible_grades at all
echo "<h3>5️⃣ Total Records in student_flexible_grades Table</h3>";
$query = "SELECT COUNT(*) as total FROM student_flexible_grades";
$result = $conn->query($query);
$row = $result->fetch_assoc();
echo "<p>Total records in entire table: <strong>{$row['total']}</strong></p>";

// 6. Check grade_term table for comparison
echo "<h3>6️⃣ Grade Term Table (for comparison)</h3>";
$query = "SELECT * FROM grade_term WHERE class_code = 'CCDATRCL' LIMIT 5";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Student ID</th><th>Midterm %</th><th>Finals %</th><th>Term %</th><th>Grade Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['student_id']}</td>";
        echo "<td>{$row['midterm_percentage']}</td>";
        echo "<td>{$row['finals_percentage']}</td>";
        echo "<td>{$row['term_percentage']}</td>";
        echo "<td>{$row['grade_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No grade_term records found</p>";
}

$conn->close();
?>
