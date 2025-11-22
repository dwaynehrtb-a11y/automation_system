<?php
/**
 * CRITICAL: Recalculate grade_term from student_flexible_grades
 * This script rebuilds all term percentages from actual raw_score values
 * Run this ONCE to clean up corrupted percentage data
 */

require_once 'config/db.php';
require_once 'config/session.php';

// Only admin can run this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Admin only.');
}

echo "Starting grade_term recalculation...\n\n";

try {
    // Get all unique student-class combinations
    $query = "
        SELECT DISTINCT 
            student_id, 
            class_code
        FROM student_flexible_grades
        ORDER BY student_id, class_code
    ";
    
    $result = $conn->query($query);
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $processed = 0;
    $updated = 0;
    
    while ($row = $result->fetch_assoc()) {
        $student_id = $row['student_id'];
        $class_code = $row['class_code'];
        $processed++;
        
        // Get all components for this class and their weights
        $comp_query = "
            SELECT 
                gc.id,
                gc.component_name,
                gc.percentage,
                gc.term_type
            FROM grading_components gc
            WHERE gc.class_code = ?
        ";
        
        $comp_stmt = $conn->prepare($comp_query);
        if (!$comp_stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $comp_stmt->bind_param('s', $class_code);
        $comp_stmt->execute();
        $comp_result = $comp_stmt->get_result();
        
        $midterm_percentage = 0;
        $finals_percentage = 0;
        
        while ($comp_row = $comp_result->fetch_assoc()) {
            $component_id = $comp_row['id'];
            $percentage = $comp_row['percentage'];
            $term_type = $comp_row['term_type'];
            
            // Calculate component average from raw scores
            $score_query = "
                SELECT 
                    COUNT(sfg.id) as item_count,
                    SUM(sfg.raw_score) as total_score,
                    SUM(gcc.max_score) as total_max
                FROM student_flexible_grades sfg
                INNER JOIN grading_component_columns gcc ON sfg.column_id = gcc.id
                WHERE sfg.student_id = ?
                    AND sfg.class_code = ?
                    AND gcc.component_id = ?
            ";
            
            $score_stmt = $conn->prepare($score_query);
            if (!$score_stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $score_stmt->bind_param('ssi', $student_id, $class_code, $component_id);
            $score_stmt->execute();
            $score_row = $score_stmt->get_result()->fetch_assoc();
            $score_stmt->close();
            
            $item_count = $score_row['item_count'] ?? 0;
            $total_score = $score_row['total_score'] ?? 0;
            $total_max = $score_row['total_max'] ?? 0;
            
            // Calculate component percentage
            $component_pct = ($total_max > 0) ? ($total_score / $total_max * 100) : 0;
            $weighted_pct = ($component_pct * $percentage) / 100;
            
            echo "  Student: $student_id | Component: {$comp_row['component_name']} | Raw: $total_score/$total_max | Pct: " . number_format($component_pct, 2) . "% | Weighted: " . number_format($weighted_pct, 2) . "%\n";
            
            if ($term_type === 'midterm') {
                $midterm_percentage += $weighted_pct;
            } else {
                $finals_percentage += $weighted_pct;
            }
        }
        $comp_stmt->close();
        
        // Calculate final term percentage based on class weights
        $weight_query = "SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code = ? LIMIT 1";
        $weight_stmt = $conn->prepare($weight_query);
        if (!$weight_stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $weight_stmt->bind_param('s', $class_code);
        $weight_stmt->execute();
        $weight_row = $weight_stmt->get_result()->fetch_assoc();
        $weight_stmt->close();
        
        $mid_weight = $weight_row['midterm_weight'] ?? 40;
        $fin_weight = $weight_row['finals_weight'] ?? 60;
        
        // Final term percentage
        $term_percentage = ($midterm_percentage * $mid_weight / 100) + ($finals_percentage * $fin_weight / 100);
        
        echo "  FINAL: Midterm({$mid_weight}%): " . number_format($midterm_percentage, 2) . "% | Finals({$fin_weight}%): " . number_format($finals_percentage, 2) . "% | Term: " . number_format($term_percentage, 2) . "%\n\n";
        
        // Update or insert into grade_term
        $check_query = "SELECT id FROM grade_term WHERE student_id = ? AND class_code = ?";
        $check_stmt = $conn->prepare($check_query);
        if (!$check_stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $check_stmt->bind_param('ss', $student_id, $class_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_stmt->close();
        
        if ($check_result->num_rows > 0) {
            // UPDATE
            $update_query = "
                UPDATE grade_term 
                SET midterm_percentage = ?,
                    finals_percentage = ?,
                    term_percentage = ?,
                    grade_status = 'passed'
                WHERE student_id = ? AND class_code = ?
            ";
            $update_stmt = $conn->prepare($update_query);
            if (!$update_stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $update_stmt->bind_param('dddss', $midterm_percentage, $finals_percentage, $term_percentage, $student_id, $class_code);
            if ($update_stmt->execute()) {
                $updated++;
            }
            $update_stmt->close();
        } else {
            // INSERT
            $insert_query = "
                INSERT INTO grade_term (student_id, class_code, midterm_percentage, finals_percentage, term_percentage, grade_status)
                VALUES (?, ?, ?, ?, ?, 'passed')
            ";
            $insert_stmt = $conn->prepare($insert_query);
            if (!$insert_stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $insert_stmt->bind_param('ssddd', $student_id, $class_code, $midterm_percentage, $finals_percentage, $term_percentage);
            if ($insert_stmt->execute()) {
                $updated++;
            }
            $insert_stmt->close();
        }
    }
    
    echo "\n=== RECALCULATION COMPLETE ===\n";
    echo "Processed: $processed student-class combinations\n";
    echo "Updated: $updated records\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Recalculation error: " . $e->getMessage());
}

$conn->close();
?>
