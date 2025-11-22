<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/encryption.php';
require_once '../includes/GradesModel.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Debug log
error_log("update_grade.php POST received: " . json_encode($_POST));

// Check if this is a component status update request
$action = $_POST['action'] ?? '';
if ($action === 'update_component_status') {
    $column_id = intval($_POST['column_id'] ?? 0);
    $student_id = $_POST['student_id'] ?? '';
    $class_code = $_POST['class_code'] ?? '';
    $status = $_POST['status'] ?? 'submitted';  // 'inc' or 'submitted'
    
    if ($column_id <= 0 || empty($student_id) || empty($class_code)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    try {
        // Update the status column in student_flexible_grades
        $stmt = $conn->prepare("
            UPDATE student_flexible_grades 
            SET status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE student_id = ? AND column_id = ? AND class_code = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('ssis', $status, $student_id, $column_id, $class_code);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Component status updated successfully',
            'status' => $status
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("Error updating component status: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Skip CSRF for testing - comment out after debugging
// if (!verifyCSRFToken()) {
//     ob_end_clean();
//     echo json_encode(['success' => false, 'message' => 'Invalid security token']);
//     exit;
// }

$column_id = intval($_POST['column_id'] ?? $_POST['grade_id'] ?? 0);
// Clean grade value - remove % symbol if present and convert to float
$grade_raw = $_POST['grade'] ?? 0;
if (is_string($grade_raw)) {
    $grade_raw = str_replace('%', '', trim($grade_raw));
}
$grade = floatval($grade_raw);
$student_id = $_POST['student_id'] ?? '';
$class_code = $_POST['class_code'] ?? '';

error_log("Parsed values - column_id: $column_id, grade: $grade, student_id: $student_id, class_code: $class_code");

if ($column_id <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid column ID: ' . $column_id]);
    exit;
}

if ($grade < 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Grade cannot be negative']);
    exit;
}
try {
    // Check if record exists, if not create it
    $checkStmt = $conn->prepare("SELECT id FROM student_flexible_grades WHERE student_id=? AND column_id=? AND class_code=? LIMIT 1");
    if (!$checkStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $checkStmt->bind_param('sis', $student_id, $column_id, $class_code);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    error_log("Existing record: " . json_encode($existing));
    
    if ($existing) {
        // Update existing
        $stmt = $conn->prepare("UPDATE student_flexible_grades SET raw_score = ? WHERE student_id=? AND column_id=? AND class_code=?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param('dsis', $grade, $student_id, $column_id, $class_code);
    } else {
        // Insert new - first get component_id from column
        $getCompStmt = $conn->prepare("SELECT component_id FROM grading_component_columns WHERE id=? LIMIT 1");
        if (!$getCompStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $getCompStmt->bind_param('i', $column_id);
        $getCompStmt->execute();
        $compRow = $getCompStmt->get_result()->fetch_assoc();
        $getCompStmt->close();
        
        if (!$compRow) {
            throw new Exception("Column not found");
        }
        
        $component_id = $compRow['component_id'];
        
        $stmt = $conn->prepare("INSERT INTO student_flexible_grades (student_id, column_id, component_id, class_code, raw_score) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param('siiss', $student_id, $column_id, $component_id, $class_code, $grade);
    }
    
    $updated = false;
    if ($stmt->execute()) { 
        $updated = true;
        error_log("Statement executed successfully");
    } else {
        error_log("Statement execute failed: " . $stmt->error);
    }
    $stmt->close();

    if (!$updated) {
        ob_end_clean();
        echo json_encode(['success'=>false,'message'=>'Failed to save grade']);
        exit;
    }

    // If we have context, recompute this student's term stats
    $recomputed = null;
    if ($student_id !== '' && $class_code !== '') {
        error_log("Starting recomputation for $student_id in $class_code");
        
        // Fetch term weights
        $wStmt = $conn->prepare("SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code=? LIMIT 1");
        if (!$wStmt) {
            error_log("Weight query prepare failed: " . $conn->error);
            $midtermWeight = 40.0;
            $finalsWeight = 60.0;
        } else {
            $wStmt->bind_param('s',$class_code); 
            $wStmt->execute(); 
            $wRow=$wStmt->get_result()->fetch_assoc(); 
            $wStmt->close();
            $midtermWeight = $wRow ? floatval($wRow['midterm_weight']) : 40.0;
            $finalsWeight  = $wRow ? floatval($wRow['finals_weight'])  : 60.0;
        }
        
        // Components
        $compStmt=$conn->prepare("SELECT id, percentage, term_type FROM grading_components WHERE class_code=?");
        $compStmt->bind_param('s',$class_code); 
        $compStmt->execute(); 
        $compRes=$compStmt->get_result(); 
        $components=[]; 
        while($c=$compRes->fetch_assoc()) $components[]=$c; 
        $compStmt->close();
        error_log("Found " . count($components) . " components");
        
        // Columns per component
        $colsStmt=$conn->prepare("SELECT gcc.id, gcc.component_id, gcc.max_score FROM grading_component_columns gcc JOIN grading_components gc ON gcc.component_id=gc.id WHERE gc.class_code=?");
        $colsStmt->bind_param('s',$class_code); 
        $colsStmt->execute(); 
        $colsRes=$colsStmt->get_result(); 
        $columnsByComponent=[]; 
        while($col=$colsRes->fetch_assoc()) $columnsByComponent[$col['component_id']][]=$col; 
        $colsStmt->close();
        
        // Scores for student from flexible grades
        $gradesStmt=$conn->prepare("SELECT COALESCE(g.raw_score, 0) as raw_score, gcc.component_id FROM student_flexible_grades g JOIN grading_component_columns gcc ON g.column_id=gcc.id WHERE g.class_code=? AND g.student_id=?");
        $gradesStmt->bind_param('ss',$class_code,$student_id); 
        $gradesStmt->execute(); 
        $gradesRes=$gradesStmt->get_result(); 
        $scores=[]; 
        while($gr=$gradesRes->fetch_assoc()){ 
            $cid=$gr['component_id']; 
            if(!isset($scores[$cid])) $scores[$cid]=['earned'=>0.0,'possible'=>0.0]; 
            $scores[$cid]['earned'] += floatval($gr['raw_score']); 
        } 
        $gradesStmt->close();
        error_log("Fetched scores: " . json_encode($scores));
        
        // Calculate possible scores
        foreach($components as $comp){ 
            $cid=$comp['id']; 
            if(!isset($scores[$cid])) $scores[$cid]=['earned'=>0.0,'possible'=>0.0]; 
            $possible=0.0; 
            if(isset($columnsByComponent[$cid])) foreach($columnsByComponent[$cid] as $col){ 
                $possible+=floatval($col['max_score']); 
            } 
            $scores[$cid]['possible']=$possible; 
        }
        
        // Calculate percentages
        $midtermWeightedSum=0.0; 
        $finalsWeightedSum=0.0; 
        $midtermWeightTotal=0.0; 
        $finalsWeightTotal=0.0; 
        $finalsHasScore=false;
        
        foreach($components as $comp){ 
            $cid=$comp['id']; 
            $earned=$scores[$cid]['earned']; 
            $possible=$scores[$cid]['possible']; 
            $rawPct=($possible>0?($earned/$possible)*100.0:0.0); 
            $pct=floatval($comp['percentage']); 
            if($comp['term_type']==='midterm'){ 
                $midtermWeightedSum+=$rawPct*$pct; 
                $midtermWeightTotal+=$pct; 
            } else { 
                $finalsWeightedSum+=$rawPct*$pct; 
                $finalsWeightTotal+=$pct; 
                if($earned>0) $finalsHasScore=true; 
            } 
        }
        
        $midterm_percentage=($midtermWeightTotal>0? $midtermWeightedSum/$midtermWeightTotal:0.0);
        $finals_percentage =($finalsWeightTotal>0? $finalsWeightedSum/$finalsWeightTotal:0.0);
        $term_percentage = ($midterm_percentage * ($midtermWeight/100.0)) + ($finals_percentage * ($finalsWeight/100.0));
        
        error_log("Percentages - midterm: $midterm_percentage, finals: $finals_percentage, term: $term_percentage");
        
        // Check for manual freeze
        $hasManualCol=false; 
        $colCheck=$conn->query("SHOW COLUMNS FROM grade_term LIKE 'status_manually_set'"); 
        if($colCheck && $colCheck->num_rows>0) $hasManualCol=true;
        
        $rowStmt=$conn->prepare("SELECT id, grade_status, term_grade, lacking_requirements".($hasManualCol?", status_manually_set":"")." FROM grade_term WHERE student_id=? AND class_code=? LIMIT 1");
        $rowStmt->bind_param('ss',$student_id,$class_code); 
        $rowStmt->execute(); 
        $existing=$rowStmt->get_result()->fetch_assoc(); 
        $rowStmt->close();
        
        $manualFrozen=$hasManualCol && $existing && strtolower($existing['status_manually_set'])==='yes'; 
        $lackingReq=$existing?$existing['lacking_requirements']:null;
        $midtermOnly = ($finalsWeightTotal==0)||!$finalsHasScore;
        
        // Determine status
        $grade_status=null; 
        $term_grade=null;
        
        if($manualFrozen && $existing){ 
            $grade_status=$existing['grade_status']; 
            $term_grade=$existing['term_grade']; 
        }
        else if($lackingReq==='yes'){ 
            $grade_status='incomplete'; 
        }
        else if($midtermOnly){ 
            $grade_status='incomplete'; 
        }
        else { 
            if($term_percentage<57.0)$grade_status='failed'; 
            elseif($term_percentage<60.0)$grade_status='incomplete'; 
            else $grade_status='passed'; 
        }
        
        // Calculate discrete grade
        if($grade_status==='passed' && !$manualFrozen){
            if($term_percentage>=96.0)$term_grade='4.0'; 
            elseif($term_percentage>=90.0)$term_grade='3.5'; 
            elseif($term_percentage>=84.0)$term_grade='3.0'; 
            elseif($term_percentage>=78.0)$term_grade='2.5'; 
            elseif($term_percentage>=72.0)$term_grade='2.0'; 
            elseif($term_percentage>=66.0)$term_grade='1.5'; 
            else $term_grade='1.0';
        } else if($manualFrozen && $existing){ 
            $term_grade=$existing['term_grade']; 
        } else { 
            $term_grade=null; 
        }
        
        // Safety check: passed can't be < 60%
        if($grade_status==='passed' && $term_percentage<60.0){ 
            $grade_status=($term_percentage<57.0?'failed':'incomplete'); 
            $term_grade=null; 
        }
        
        // Save recomputed grades
        $gm=new GradesModel($conn);
        $gradeData=[ 
            'student_id'=>$student_id,
            'class_code'=>$class_code,
            'midterm_percentage'=>number_format($midterm_percentage,2,'.',''),
            'finals_percentage'=>number_format($finals_percentage,2,'.',''),
            'term_percentage'=>number_format($term_percentage,2,'.',''),
            'grade_status'=>$grade_status,
            'term_grade'=>$term_grade,
            'lacking_requirements'=>$lackingReq??'' 
        ];
        
        if($manualFrozen && $existing){ 
            $gradeData['grade_status']=$existing['grade_status']; 
            $gradeData['term_grade']=$existing['term_grade']; 
        }
        
        try { 
            $gm->saveGrades($gradeData,$_SESSION['user_id']); 
            error_log("Grades saved successfully");
        } catch(Exception $e){ 
            error_log('update_grade recompute save error '.$e->getMessage()); 
        }
        
        $recomputed = $gradeData + ['manual_frozen'=>$manualFrozen,'midterm_only'=>$midtermOnly];
        error_log("Recomputed data: " . json_encode($recomputed));
    }

    ob_end_clean();
    $response = ['success'=>true,'message'=>'Grade updated successfully!','recomputed'=>$recomputed];
    error_log("Sending response: " . json_encode($response));
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Update grade error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Failed to update grade: ' . $e->getMessage()]);
}
?>