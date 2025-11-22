<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    // Check which table exists
    $check_sections = $conn->query("SHOW TABLES LIKE 'sections'");
    if ($check_sections && $check_sections->num_rows > 0) {
        $sections_query = "SELECT section_id, section_code FROM sections ORDER BY section_code";
    } else {
        $sections_query = "SELECT section_id, section_code FROM section ORDER BY section_code";
    }
    
    $result = $conn->query($sections_query);
    $sections = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'sections' => $sections
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading sections: ' . $e->getMessage()
    ]);
}
?>