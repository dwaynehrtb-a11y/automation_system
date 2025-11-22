<?php
require 'config/db.php';

// Create grade_visibility_status table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS `grade_visibility_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `class_code` varchar(100) NOT NULL,
  `grade_visibility` enum('visible','hidden') DEFAULT 'visible',
  `visibility_changed_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `changed_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_visibility` (`student_id`, `class_code`),
  KEY `idx_class_code` (`class_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($conn->query($sql)) {
    echo "Table created successfully";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
