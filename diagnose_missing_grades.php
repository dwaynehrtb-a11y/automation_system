<?php
/**
 * CRITICAL DIAGNOSIS - Missing Grade Records
 */
require_once 'config/db.php';

$class_code = 'CCPRGG1L';

echo "=== CRITICAL ISSUE: Missing Grade Records ===\n\n";

// Step 1: Check all students enrolled in class
echo "Step 1: Students enrolled in class $class_code\n";
$stmt = $conn->prepare("
    SELECT DISTINCT ce.student_id, s.first_name, s.last_name
    FROM class_enrollments ce
    LEFT JOIN student s ON ce.student_id = s.student_id
    WHERE ce.class_code = ? AND ce.status = 'enrolled'
    ORDER BY ce.student_id
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

$enrolled_students = [];
while ($row = $result->fetch_assoc()) {
    $enrolled_students[] = $row['student_id'];
    echo "  - {$row['student_id']} ({$row['first_name']} {$row['last_name']})\n";
}
$stmt->close();

echo "\nTotal enrolled: " . count($enrolled_students) . "\n";

// Step 2: Check which have grade records
echo "\n\nStep 2: Which enrolled students HAVE grade records\n";
$stmt = $conn->prepare("
    SELECT DISTINCT gt.student_id, COUNT(*) as grade_count
    FROM grade_term gt
    WHERE gt.class_code = ?
    GROUP BY gt.student_id
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

$with_grades = [];
while ($row = $result->fetch_assoc()) {
    $with_grades[] = $row['student_id'];
    echo "  - {$row['student_id']}: {$row['grade_count']} grade record(s)\n";
}
$stmt->close();

// Step 3: Find missing grade records
echo "\n\nStep 3: Students MISSING grade records\n";
$missing = array_diff($enrolled_students, $with_grades);

if (empty($missing)) {
    echo "  ✅ All enrolled students have grade records\n";
} else {
    echo "  ❌ MISSING grade records for:\n";
    foreach ($missing as $student_id) {
        echo "    - $student_id\n";
    }
    echo "\n  Total missing: " . count($missing) . " out of " . count($enrolled_students) . "\n";
}

// Step 4: Check specific student
echo "\n\nStep 4: Specific check for 2025-276819\n";
$stmt = $conn->prepare("
    SELECT 
        ce.enrollment_id,
        ce.status,
        ce.enrollment_date,
        gt.id as grade_id
    FROM class_enrollments ce
    LEFT JOIN grade_term gt ON ce.student_id = gt.student_id AND ce.class_code = gt.class_code
    WHERE ce.student_id = '2025-276819' AND ce.class_code = ?
");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "  Enrollment status: {$row['status']}\n";
    echo "  Enrolled on: {$row['enrollment_date']}\n";
    echo "  Grade record ID: " . ($row['grade_id'] ? $row['grade_id'] : "❌ NULL (MISSING)") . "\n";
} else {
    echo "  ❌ Student is NOT even enrolled in this class!\n";
}
$stmt->close();

// Step 5: Recommendations
echo "\n\n=== DIAGNOSIS ===\n\n";

if (empty($missing)) {
    echo "✅ All students have grade records\n";
    echo "The issue might be with grade values, not missing records\n";
} else {
    echo "❌ CRITICAL ISSUE: Missing grade records for " . count($missing) . " students\n";
    echo "\nThis means:\n";
    echo "1. Grades were never created/entered for these students\n";
    echo "2. OR grades were deleted accidentally\n";
    echo "3. Student API will always return 'Grades have not been released yet'\n";
    echo "\n⚠️ ACTION NEEDED:\n";
    echo "Faculty must enter grades for these students using the flexible grading system\n";
}

?>
