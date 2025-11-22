<?php
define('SYSTEM_ACCESS', true);
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$response = ['success' => false, 'message' => '', 'data' => []];

try {
    switch($type) {
        case 'faculty_workload':
            $query = "
                SELECT 
                    u.name as faculty_name,
                    COUNT(DISTINCT c.class_id) as class_count
                FROM users u
                LEFT JOIN class c ON u.id = c.faculty_id
                WHERE u.role = 'faculty'
                GROUP BY u.id, u.name
                ORDER BY class_count DESC, u.name
                LIMIT 10
            ";
            $result = $conn->query($query);
            
            if ($result) {
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'faculty_name' => $row['faculty_name'],
                        'class_count' => (int)$row['class_count']
                    ];
                }
                $response['success'] = true;
                $response['data'] = $data;
            } else {
                $response['message'] = 'Query failed: ' . $conn->error;
            }
            break;

        case 'enrollment_trends':
            $query = "
                SELECT 
                    DATE_FORMAT(enrollment_date, '%Y-%m') as month,
                    COUNT(*) as student_count
                FROM student
                WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(enrollment_date, '%Y-%m')
                ORDER BY month ASC
            ";
            $result = $conn->query($query);
            
            if ($result) {
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'month' => $row['month'],
                        'student_count' => (int)$row['student_count']
                    ];
                }
                $response['success'] = true;
                $response['data'] = $data;
            } else {
                $response['message'] = 'Query failed: ' . $conn->error;
            }
            break;

        default:
            $response['message'] = 'Invalid analytics type';
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Analytics error: " . $e->getMessage());
}

echo json_encode($response);
?>