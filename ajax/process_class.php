<?php
// Clean buffer and prevent any output before JSON
ob_start();

// Only show errors in development, not production
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../config/session.php';
require_once '../config/db.php';

// Clear any output that might have occurred from includes
ob_clean();

// Set JSON header first
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Ensure we're outputting JSON even on errors
function outputJSON($data) {
    ob_clean(); // Clear any previous output
    echo json_encode($data);
    ob_end_flush();
    exit;
}

// Check authentication
if (!isAuthenticated() || getCurrentUser()['role'] !== 'admin') {
    outputJSON(["success" => false, "message" => "Unauthorized access"]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    outputJSON(["success" => false, "message" => "Invalid request method"]);
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'add') {
        // Debug: Log what we received
        error_log("Received POST data: " . print_r($_POST, true));
        
        // Validate required fields
        $required_fields = ['section', 'academic_year', 'term', 'course_code', 'room', 'faculty_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                outputJSON(["success" => false, "message" => "Missing required field: " . ucfirst(str_replace('_', ' ', $field))]);
            }
        }
        
        // Get form data
        $section = trim($_POST['section']);
        $academic_year = trim($_POST['academic_year']);
        $term = trim($_POST['term']);
        $course_code = trim($_POST['course_code']);
        $room = trim($_POST['room']);
        $faculty_id = intval($_POST['faculty_id']);
        
        // Handle schedule data - ensure arrays exist
        $days = isset($_POST['day']) && is_array($_POST['day']) ? $_POST['day'] : [];
        $times = isset($_POST['time']) && is_array($_POST['time']) ? $_POST['time'] : [];
        
        // Debug: Log schedule data
        error_log("Days: " . print_r($days, true));
        error_log("Times: " . print_r($times, true));
        
        if (empty($days) || empty($times)) {
            outputJSON(["success" => false, "message" => "Please add at least one schedule"]);
        }
        
        // Filter out empty/invalid schedules
        $valid_schedules = [];
        for ($i = 0; $i < count($days); $i++) {
            if (isset($days[$i]) && isset($times[$i]) && 
                !empty(trim($days[$i])) && !empty(trim($times[$i])) && 
                trim($times[$i]) !== 'Not set') {
                $valid_schedules[] = [
                    'day' => trim($days[$i]),
                    'time' => trim($times[$i])
                ];
            }
        }
        
        if (empty($valid_schedules)) {
            outputJSON(["success" => false, "message" => "Please provide at least one valid schedule (day and time)"]);
        }
        
        // Check if faculty exists
        $faculty_check = $conn->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'faculty'");
        if (!$faculty_check) {
            outputJSON(["success" => false, "message" => "Database prepare error: " . $conn->error]);
        }
        
        $faculty_check->bind_param('i', $faculty_id);
        $faculty_check->execute();
        $result = $faculty_check->get_result();
        $faculty = $result->fetch_assoc();
        
        if (!$faculty) {
            outputJSON(["success" => false, "message" => "Invalid faculty member selected"]);
        }
        
        // Check if course_code exists in subjects table
        $course_check = $conn->prepare("SELECT course_code, course_title FROM subjects WHERE course_code = ?");
        if (!$course_check) {
            outputJSON(["success" => false, "message" => "Database prepare error: " . $conn->error]);
        }
        
        $course_check->bind_param('s', $course_code);
        $course_check->execute();
        $course_result = $course_check->get_result();
        $course_info = $course_result->fetch_assoc();
        
        if (!$course_info) {
            outputJSON(["success" => false, "message" => "Course code '$course_code' not found in subjects table"]);
        }
        
        $success_count = 0;
        $created_classes = [];
        $failed_schedules = [];
        $schedule_display_parts = [];
        
        // Begin transaction for data integrity
        $conn->begin_transaction();
        
        try {
            // Insert each schedule as a separate class
            foreach ($valid_schedules as $index => $schedule) {
                $day = $schedule['day'];
                $time = $schedule['time'];
                
                // DEBUG: Log what we're trying to insert
                error_log("Attempting to insert - Day: $day, Time: $time, Section: $section, Course: $course_code, Faculty: $faculty_id");
                
                // Prepare statement for each insert
                $stmt = $conn->prepare("INSERT INTO class (section, academic_year, term, course_code, day, time, room, faculty_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                if (!$stmt) {
                    error_log("Prepare failed: " . $conn->error);
                    $failed_schedules[] = "Day: $day, Time: $time - Prepare failed";
                    continue;
                }
                
                $stmt->bind_param('sssssssi', $section, $academic_year, $term, $course_code, $day, $time, $room, $faculty_id);
                $result = $stmt->execute();
                
                if ($result) {
                    $success_count++;
                    $class_id = $conn->insert_id;
                    
                    error_log("Successfully inserted class with ID: $class_id for day: $day, time: $time");
                    
                    $created_classes[] = [
                        'class_id' => $class_id,
                        'section' => $section,
                        'academic_year' => $academic_year,
                        'term' => $term,
                        'course_code' => $course_code,
                        'course_title' => $course_info['course_title'] ?? '',
                        'day' => $day,
                        'time' => $time,
                        'room' => $room,
                        'faculty_id' => $faculty_id,
                        'faculty_name' => $faculty['name']
                    ];
                    
                    // Build schedule display
                    $schedule_display_parts[] = $day . ' ' . $time;
                    
                } else {
                    $error_msg = $stmt->error;
                    error_log("Failed to insert class for day: $day, time: $time. Error: " . $error_msg);
                    $failed_schedules[] = "Day: $day, Time: $time - SQL Error";
                }
                
                $stmt->close();
            }
            
            if ($success_count > 0) {
                // Commit transaction
                $conn->commit();
                
                $message = "Successfully created $success_count class schedule(s)";
                if (!empty($failed_schedules)) {
                    $message .= ". Failed: " . implode(", ", $failed_schedules);
                }
                
                // Create consolidated schedule display
                $schedule_display = implode('<br>', $schedule_display_parts);
                
                // Build schedule data for edit functionality
                $schedule_data_parts = [];
                foreach ($created_classes as $class) {
                    $schedule_data_parts[] = $class['day'] . '|' . $class['time'] . '|' . $class['class_id'];
                }
                $schedule_data = implode('||', $schedule_data_parts);
                
                // Return data for table update (using first class as representative)
                $first_class = $created_classes[0];
                
                outputJSON([
                    "success" => true, 
                    "message" => $message,
                    "newItem" => [
                        'class_id' => $first_class['class_id'],
                        'section' => $first_class['section'],
                        'academic_year' => $first_class['academic_year'],
                        'term' => $first_class['term'],
                        'course_code' => $first_class['course_code'],
                        'course_title' => $first_class['course_title'],
                        'day' => $first_class['day'],
                        'time' => $first_class['time'],
                        'room' => $first_class['room'],
                        'faculty_id' => $first_class['faculty_id'],
                        'faculty_name' => $first_class['faculty_name'],
                        'schedule_display' => $schedule_display,
                        'schedule_data' => $schedule_data,
                        'created_count' => $success_count,
                        'schedule_count' => $success_count
                    ],
                    "created_count" => $success_count,
                    "total_schedules" => count($valid_schedules),
                    "all_created_classes" => $created_classes
                ]);
            } else {
                // Rollback transaction
                $conn->rollback();
                outputJSON([
                    "success" => false, 
                    "message" => "Failed to create any class schedules. Issues: " . implode(", ", $failed_schedules)
                ]);
            }
            
        } catch (Exception $e) {
            // Rollback transaction on any error
            $conn->rollback();
            throw $e;
        }
        
    } elseif ($action === 'update_group') {
        // NEW: Update entire class group with multiple schedules
        $primary_class_id = intval($_POST['primary_class_id'] ?? 0);
        $all_schedule_ids = isset($_POST['all_schedule_ids']) ? json_decode($_POST['all_schedule_ids'], true) : [];
        
        // Validate required fields  
        $required_fields = ['section', 'academic_year', 'term', 'course_code', 'room', 'faculty_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                outputJSON(["success" => false, "message" => "Missing required field: " . ucfirst(str_replace('_', ' ', $field))]);
            }
        }
        
        $section = trim($_POST['section']);
        $academic_year = trim($_POST['academic_year']);
        $term = trim($_POST['term']);
        $course_code = trim($_POST['course_code']);
        $room = trim($_POST['room']);
        $faculty_id = intval($_POST['faculty_id']);
        
        // Handle schedule data for group update
        $days = isset($_POST['day']) && is_array($_POST['day']) ? $_POST['day'] : [];
        $times = isset($_POST['time']) && is_array($_POST['time']) ? $_POST['time'] : [];
        
        if (empty($days) || empty($times)) {
            outputJSON(["success" => false, "message" => "Please provide at least one valid schedule"]);
        }
        
        // Filter out empty/invalid schedules
        $valid_schedules = [];
        for ($i = 0; $i < count($days); $i++) {
            if (isset($days[$i]) && isset($times[$i]) && 
                !empty(trim($days[$i])) && !empty(trim($times[$i])) && 
                trim($times[$i]) !== 'Not set') {
                $valid_schedules[] = [
                    'day' => trim($days[$i]),
                    'time' => trim($times[$i])
                ];
            }
        }
        
        if (empty($valid_schedules)) {
            outputJSON(["success" => false, "message" => "Please provide at least one valid schedule (day and time)"]);
        }
        
        if ($primary_class_id <= 0) {
            outputJSON(["success" => false, "message" => "Invalid class ID for update"]);
        }
        
        // Check if faculty exists
        $faculty_check = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'faculty'");
        if (!$faculty_check) {
            outputJSON(["success" => false, "message" => "Database prepare error: " . $conn->error]);
        }
        
        $faculty_check->bind_param('i', $faculty_id);
        $faculty_check->execute();
        $faculty_result = $faculty_check->get_result();
        $faculty = $faculty_result->fetch_assoc();
        
        if (!$faculty) {
            outputJSON(["success" => false, "message" => "Invalid faculty member selected"]);
        }
        
        // Begin transaction for group update
        $conn->begin_transaction();
        
        try {
            // Strategy: Delete all existing schedules for this group, then recreate
            // This is simpler and more reliable than trying to match/update individual schedules
            
            // First, get the existing group info to identify all related schedules
            $existing_check = $conn->prepare("SELECT section, academic_year, term, course_code, faculty_id, room FROM class WHERE class_id = ?");
            $existing_check->bind_param('i', $primary_class_id);
            $existing_check->execute();
            $existing_result = $existing_check->get_result();
            $existing_class = $existing_result->fetch_assoc();
            
            if (!$existing_class) {
                throw new Exception("Class not found");
            }
            
            // Delete all schedules for this class group
            $delete_stmt = $conn->prepare("DELETE FROM class WHERE section = ? AND academic_year = ? AND term = ? AND course_code = ? AND faculty_id = ? AND room = ?");
            $delete_stmt->bind_param('sssssi', 
                $existing_class['section'], 
                $existing_class['academic_year'], 
                $existing_class['term'], 
                $existing_class['course_code'], 
                $existing_class['faculty_id'], 
                $existing_class['room']
            );
            $delete_result = $delete_stmt->execute();
            $deleted_count = $conn->affected_rows;
            
            if (!$delete_result) {
                throw new Exception("Failed to delete existing schedules: " . $conn->error);
            }
            
            error_log("Deleted $deleted_count existing schedules for class group update");
            
            // Now insert new schedules
            $success_count = 0;
            $created_classes = [];
            $failed_schedules = [];
            
            foreach ($valid_schedules as $index => $schedule) {
                $day = $schedule['day'];
                $time = $schedule['time'];
                
                error_log("Inserting updated schedule - Day: $day, Time: $time");
                
                $stmt = $conn->prepare("INSERT INTO class (section, academic_year, term, course_code, day, time, room, faculty_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                if (!$stmt) {
                    error_log("Prepare failed: " . $conn->error);
                    $failed_schedules[] = "Day: $day, Time: $time - Prepare failed";
                    continue;
                }
                
                $stmt->bind_param('sssssssi', $section, $academic_year, $term, $course_code, $day, $time, $room, $faculty_id);
                $result = $stmt->execute();
                
                if ($result) {
                    $success_count++;
                    $class_id = $conn->insert_id;
                    
                    error_log("Successfully inserted updated class with ID: $class_id");
                    
                    $created_classes[] = [
                        'class_id' => $class_id,
                        'section' => $section,
                        'academic_year' => $academic_year,
                        'term' => $term,
                        'course_code' => $course_code,
                        'day' => $day,
                        'time' => $time,
                        'room' => $room,
                        'faculty_id' => $faculty_id,
                        'faculty_name' => $faculty['name']
                    ];
                } else {
                    $error_msg = $stmt->error;
                    error_log("Failed to insert updated schedule for day: $day, time: $time. Error: " . $error_msg);
                    $failed_schedules[] = "Day: $day, Time: $time - SQL Error";
                }
                
                $stmt->close();
            }
            
            if ($success_count > 0) {
                // Commit transaction
                $conn->commit();
                
                $message = "Successfully updated class group with $success_count schedule(s)";
                if (!empty($failed_schedules)) {
                    $message .= ". Some issues: " . implode(", ", $failed_schedules);
                }
                
                // Get course title
                $course_stmt = $conn->prepare("SELECT course_title FROM subjects WHERE course_code = ?");
                if ($course_stmt) {
                    $course_stmt->bind_param('s', $course_code);
                    $course_stmt->execute();
                    $course_result = $course_stmt->get_result();
                    $course = $course_result->fetch_assoc();
                }
                
                outputJSON([
                    "success" => true, 
                    "message" => $message,
                    "updatedItem" => [
                        "class_id" => $created_classes[0]['class_id'],
                        "section" => $section,
                        "academic_year" => $academic_year,
                        "term" => $term,
                        "course_code" => $course_code,
                        "course_title" => $course['course_title'] ?? '',
                        "room" => $room,
                        "faculty_id" => $faculty_id,
                        "faculty_name" => $faculty['name'],
                        "schedule_count" => $success_count,
                        "deleted_count" => $deleted_count
                    ],
                    "updated_schedules" => $created_classes
                ]);
            } else {
                // Rollback transaction
                $conn->rollback();
                throw new Exception("Failed to create any updated schedules: " . implode(", ", $failed_schedules));
            }
            
        } catch (Exception $e) {
            // Rollback transaction on any error
            $conn->rollback();
            throw $e;
        }
        
    } elseif ($action === 'update') {
        // Original single schedule update (kept for backward compatibility)
        $class_id = intval($_POST['class_id'] ?? 0);
        
        // Validate required fields  
        $required_fields = ['section', 'academic_year', 'term', 'course_code', 'room', 'faculty_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                outputJSON(["success" => false, "message" => "Missing required field: " . ucfirst(str_replace('_', ' ', $field))]);
            }
        }
        
        $section = trim($_POST['section']);
        $academic_year = trim($_POST['academic_year']);
        $term = trim($_POST['term']);
        $course_code = trim($_POST['course_code']);
        $room = trim($_POST['room']);
        $faculty_id = intval($_POST['faculty_id']);
        
        // Handle schedule data for update
        $days = isset($_POST['day']) && is_array($_POST['day']) ? $_POST['day'] : [];
        $times = isset($_POST['time']) && is_array($_POST['time']) ? $_POST['time'] : [];
        
        if (empty($days) || empty($times) || empty($days[0]) || empty($times[0])) {
            outputJSON(["success" => false, "message" => "Please provide valid schedule information"]);
        }
        
        $day = trim($days[0]);
        $time = trim($times[0]);
        
        if ($class_id <= 0) {
            outputJSON(["success" => false, "message" => "Invalid class ID for update"]);
        }
        
        // Check if faculty exists
        $faculty_check = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'faculty'");
        if (!$faculty_check) {
            outputJSON(["success" => false, "message" => "Database prepare error: " . $conn->error]);
        }
        
        $faculty_check->bind_param('i', $faculty_id);
        $faculty_check->execute();
        $faculty_result = $faculty_check->get_result();
        $faculty = $faculty_result->fetch_assoc();
        
        if (!$faculty) {
            outputJSON(["success" => false, "message" => "Invalid faculty member selected"]);
        }
        
        // Update the class
        $stmt = $conn->prepare("UPDATE class SET section = ?, academic_year = ?, term = ?, course_code = ?, day = ?, time = ?, room = ?, faculty_id = ? WHERE class_id = ?");
        if (!$stmt) {
            outputJSON(["success" => false, "message" => "Database prepare error: " . $conn->error]);
        }
        
        $stmt->bind_param('sssssssii', $section, $academic_year, $term, $course_code, $day, $time, $room, $faculty_id, $class_id);
        $result = $stmt->execute();
        
        if ($result) {
            // Get course title
            $course_stmt = $conn->prepare("SELECT course_title FROM subjects WHERE course_code = ?");
            if ($course_stmt) {
                $course_stmt->bind_param('s', $course_code);
                $course_stmt->execute();
                $course_result = $course_stmt->get_result();
                $course = $course_result->fetch_assoc();
            }
            
            outputJSON([
                "success" => true, 
                "message" => "Class updated successfully",
                "updatedItem" => [
                    "class_id" => $class_id,
                    "section" => $section,
                    "academic_year" => $academic_year,
                    "term" => $term,
                    "course_code" => $course_code,
                    "course_title" => $course['course_title'] ?? '',
                    "day" => $day,
                    "time" => $time,
                    "room" => $room,
                    "faculty_id" => $faculty_id,
                    "faculty_name" => $faculty['name']
                ]
            ]);
        } else {
            outputJSON(["success" => false, "message" => "Failed to update class: " . $conn->error]);
        }
        
    } elseif ($action === 'delete') {
        // Delete single class and all related records
        $class_id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
        
        if ($class_id <= 0) {
            outputJSON(["success" => false, "message" => "Invalid class ID"]);
        }

        // Start transaction for data integrity
        $conn->begin_transaction();
        
        try {
            // First, get the class_code to delete related records
            $getClassStmt = $conn->prepare("SELECT class_code FROM class WHERE class_id = ?");
            $getClassStmt->bind_param('i', $class_id);
            $getClassStmt->execute();
            $classResult = $getClassStmt->get_result()->fetch_assoc();
            $getClassStmt->close();
            
            if (!$classResult) {
                $conn->rollback();
                outputJSON(["success" => false, "message" => "Class not found"]);
            }
            
            $class_code = $classResult['class_code'];
            
            // Delete related records in order of foreign key dependencies
            $tables_to_clean = [
                'student_flexible_grades' => 'DELETE FROM student_flexible_grades WHERE column_id IN (SELECT id FROM grading_component_columns WHERE component_id IN (SELECT id FROM grading_components WHERE class_code = ?))',
                'grading_component_columns' => 'DELETE FROM grading_component_columns WHERE component_id IN (SELECT id FROM grading_components WHERE class_code = ?)',
                'grading_components' => 'DELETE FROM grading_components WHERE class_code = ?',
                'grade_term' => 'DELETE FROM grade_term WHERE class_code = ?',
                'class_enrollments' => 'DELETE FROM class_enrollments WHERE class_code = ?',
                'class' => 'DELETE FROM class WHERE class_id = ?'
            ];
            
            foreach ($tables_to_clean as $table => $query) {
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare error for $table: " . $conn->error);
                }
                
                if ($table === 'class') {
                    $stmt->bind_param('i', $class_id);
                } else {
                    $stmt->bind_param('s', $class_code);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute error for $table: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            outputJSON(["success" => true, "message" => "Class and all related records deleted successfully"]);
            
        } catch (Exception $e) {
            $conn->rollback();
            outputJSON(["success" => false, "message" => "Error deleting class: " . $e->getMessage()]);
        }
        
    } elseif ($action === 'delete_group') {
        // Delete all schedules for a class group and related records
        $section = $_GET['section'] ?? $_POST['section'] ?? '';
        $academic_year = $_GET['academic_year'] ?? $_POST['academic_year'] ?? '';
        $term = $_GET['term'] ?? $_POST['term'] ?? '';
        $course_code = $_GET['course_code'] ?? $_POST['course_code'] ?? '';
        $faculty_id = intval($_GET['faculty_id'] ?? $_POST['faculty_id'] ?? 0);
        
        if (empty($section) || empty($academic_year) || empty($term) || empty($course_code) || $faculty_id <= 0) {
            outputJSON(["success" => false, "message" => "Missing required parameters for group delete"]);
        }

        $conn->begin_transaction();
        
        try {
            // Get all class codes for this group
            $getClassesStmt = $conn->prepare("SELECT class_code FROM class WHERE section = ? AND academic_year = ? AND term = ? AND course_code = ? AND faculty_id = ?");
            $getClassesStmt->bind_param('ssssi', $section, $academic_year, $term, $course_code, $faculty_id);
            $getClassesStmt->execute();
            $result = $getClassesStmt->get_result();
            $classes = [];
            while ($row = $result->fetch_assoc()) {
                $classes[] = $row['class_code'];
            }
            $getClassesStmt->close();
            
            // Delete related records for each class
            foreach ($classes as $cc) {
                // Delete student_flexible_grades
                $stmt = $conn->prepare("DELETE FROM student_flexible_grades WHERE column_id IN (SELECT id FROM grading_component_columns WHERE component_id IN (SELECT id FROM grading_components WHERE class_code = ?))");
                $stmt->bind_param('s', $cc);
                $stmt->execute();
                $stmt->close();
                
                // Delete grading_component_columns
                $stmt = $conn->prepare("DELETE FROM grading_component_columns WHERE component_id IN (SELECT id FROM grading_components WHERE class_code = ?)");
                $stmt->bind_param('s', $cc);
                $stmt->execute();
                $stmt->close();
                
                // Delete grading_components
                $stmt = $conn->prepare("DELETE FROM grading_components WHERE class_code = ?");
                $stmt->bind_param('s', $cc);
                $stmt->execute();
                $stmt->close();
                
                // Delete grade_term
                $stmt = $conn->prepare("DELETE FROM grade_term WHERE class_code = ?");
                $stmt->bind_param('s', $cc);
                $stmt->execute();
                $stmt->close();
                
                // Delete class_enrollments
                $stmt = $conn->prepare("DELETE FROM class_enrollments WHERE class_code = ?");
                $stmt->bind_param('s', $cc);
                $stmt->execute();
                $stmt->close();
            }
            
            // Finally delete all classes in the group
            $stmt = $conn->prepare("DELETE FROM class WHERE section = ? AND academic_year = ? AND term = ? AND course_code = ? AND faculty_id = ?");
            $stmt->bind_param('ssssi', $section, $academic_year, $term, $course_code, $faculty_id);
            $stmt->execute();
            $deleted_count = $conn->affected_rows;
            $stmt->close();
            
            $conn->commit();
            outputJSON(["success" => true, "message" => "Deleted $deleted_count schedule(s) and all related records successfully"]);
            
        } catch (Exception $e) {
            $conn->rollback();
            outputJSON(["success" => false, "message" => "Error deleting schedules: " . $e->getMessage()]);
        }
        
    } else {
        outputJSON(["success" => false, "message" => "Invalid action: " . $action]);
    }
    
} catch (Exception $e) {
    // Rollback any transaction
    if ($conn && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    error_log("Class processing error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    outputJSON(["success" => false, "message" => "Database error occurred. Please try again."]);
}
?>