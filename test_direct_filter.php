<?php
// Direct test without dependencies
session_start();

// Simulate POST data
$_POST['academic_year'] = '24';
$_POST['term'] = 'T2';

echo "Starting test...\n";

// Check session
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 21; // Set a test user
    echo "Session set\n";
}

// Include db
try {
    require_once 'config/db.php';
    echo "DB included\n";
} catch (Exception $e) {
    die("DB Error: " . $e->getMessage());
}

// Check connection
if (!isset($conn)) {
    die("No connection\n");
}
echo "Connection exists\n";

// Check class table structure
echo "\n<h3>Class table columns:</h3>\n";
$columns = $conn->query("DESCRIBE class");
while ($col = $columns->fetch_assoc()) {
    echo $col['Field'] . " (" . $col['Type'] . ")<br>\n";
}
echo "\n";

// Get parameters
$academic_year = $_POST['academic_year'] ?? '';
$term = $_POST['term'] ?? '';

echo "Academic Year: $academic_year\n";
echo "Term: $term\n";

if (empty($academic_year) || empty($term)) {
    die("Parameters empty\n");
}

// Try the query
try {
    $query = "SELECT * FROM class WHERE academic_year = ? AND term = ? LIMIT 1";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error . "\n");
    }
    echo "Query prepared\n";
    
    $stmt->bind_param("ss", $academic_year, $term);
    echo "Params bound\n";
    
    $stmt->execute();
    echo "Query executed\n";
    
    $result = $stmt->get_result();
    echo "Results fetched: " . $result->num_rows . " rows\n\n";
    
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = [
            'class_id' => $row['class_id'],
            'class_code' => $row['class_code'],
            'subject_code' => $row['course_code'] ?? 'N/A',
            'subject_name' => $row['course_title'] ?? 'Unknown Subject',
            'section' => $row['section'] ?? 'N/A',
            'schedule' => $row['schedule'] ?? 'N/A',
            'room' => $row['room'] ?? 'N/A',
            'faculty_name' => $row['faculty_name'] ?? 'TBA'
        ];
    }
    
    echo "JSON output:\n";
    echo json_encode([
        'success' => true,
        'classes' => $classes,
        'count' => count($classes)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
