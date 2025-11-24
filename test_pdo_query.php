<?php
// Direct inline test - no includes except minimal
$_SESSION = ['user_id' => 114, 'role' => 'faculty'];
$class_code = '24_T2_CCPRGG1L_INF222';

// Direct PDO connection
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=automation_system;charset=utf8mb4",
        "root",
        ""
    );
    
    echo "Connected to database\n\n";
    
    // Quick test query
    $sql = "SELECT co.co_number, gc.component_name, sfg.raw_score
            FROM course_outcomes co
            LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))
            LEFT JOIN grading_components gc ON gc.id = gcc.component_id
            LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
            WHERE co.course_code = 'CCPRGG1L'
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $count++;
        echo "CO:" . $row['co_number'] . " | Assessment:" . $row['component_name'] . " | Score:" . $row['raw_score'] . "\n";
    }
    
    echo "\n✓ Query executed successfully! Found $count rows\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
