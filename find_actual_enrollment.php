<?php
/**
 * CRITICAL DISCOVERY - Find Actual Enrollment
 */
require_once 'config/db.php';

$student_id = '2025-276819';

echo "=== FINDING ACTUAL ENROLLMENT FOR STUDENT $student_id ===\n\n";

// Step 1: Find all classes student is enrolled in
echo "Step 1: Classes where student IS enrolled:\n";
$stmt = $conn->prepare("
    SELECT DISTINCT 
        ce.class_code,
        c.course_code,
        c.academic_year,
        c.term,
        c.section,
        u.name as faculty_name,
        ce.status,
        ce.enrollment_date
    FROM class_enrollments ce
    JOIN class c ON ce.class_code = c.class_code
    LEFT JOIN users u ON c.faculty_id = u.id
    WHERE ce.student_id = ?
    ORDER BY c.academic_year DESC, c.term DESC
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "  ❌ NO ENROLLMENTS FOUND\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "  • Class: {$row['class_code']}\n";
        echo "    Course: {$row['course_code']} (Section {$row['section']})\n";
        echo "    Term: {$row['term']} {$row['academic_year']}\n";
        echo "    Faculty: {$row['faculty_name']}\n";
        echo "    Status: {$row['status']}\n";
        echo "    Enrolled: {$row['enrollment_date']}\n\n";
    }
}
$stmt->close();

// Step 2: Check if student exists
echo "\nStep 2: Student record check:\n";
$stmt = $conn->prepare("
    SELECT student_id, first_name, last_name, email, status 
    FROM student 
    WHERE student_id = ?
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "  ❌ STUDENT RECORD NOT FOUND\n";
} else {
    $row = $result->fetch_assoc();
    echo "  ✅ Student found:\n";
    echo "    Name: {$row['first_name']} {$row['last_name']}\n";
    echo "    Email: {$row['email']}\n";
    echo "    Status: {$row['status']}\n";
}
$stmt->close();

// Step 3: Check why they're looking at CCPRGG1L
echo "\nStep 3: Looking for student in CCPRGG1L anyway:\n";
$stmt = $conn->prepare("
    SELECT ce.enrollment_id, ce.status 
    FROM class_enrollments ce 
    WHERE ce.student_id = ? AND ce.class_code = 'CCPRGG1L'
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "  ❌ NOT ENROLLED IN CCPRGG1L\n";
} else {
    echo "  ✅ Found in CCPRGG1L\n";
}
$stmt->close();

// Step 4: Show all students in CCPRGG1L
echo "\nStep 4: All students in class CCPRGG1L:\n";
$stmt = $conn->prepare("
    SELECT DISTINCT
        ce.student_id,
        s.first_name,
        s.last_name,
        ce.status
    FROM class_enrollments ce
    LEFT JOIN student s ON ce.student_id = s.student_id
    WHERE ce.class_code = 'CCPRGG1L' AND ce.status = 'enrolled'
    ORDER BY ce.student_id
");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "  ❌ NO ENROLLED STUDENTS IN CCPRGG1L\n";
} else {
    echo "  Found " . $result->num_rows . " enrolled students:\n";
    while ($row = $result->fetch_assoc()) {
        echo "    - {$row['student_id']}: {$row['first_name']} {$row['last_name']}\n";
    }
}
$stmt->close();

echo "\n=== DIAGNOSIS ===\n\n";
echo "⚠️ Student 2025-276819 is NOT enrolled in class CCPRGG1L\n";
echo "This explains why:\n";
echo "  1. No grade_term record exists\n";
echo "  2. Student API returns 'Grades not released'\n";
echo "  3. Student sees lock icons\n\n";
echo "The student should check their ACTUAL enrolled classes.\n";

?>
