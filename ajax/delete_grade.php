<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../includes/GradesModel.php';

header('Content-Type: application/json');

if (!verifyCSRFToken()) {
echo json_encode(['success' => false, 'message' => 'Invalid security token']);
exit;
}

$grade_id = intval($_GET['grade_id'] ?? $_POST['grade_id'] ?? 0);

// Fetch context (student_id, class_code) before deletion if this is a flexible grade
$gradeRow = null;
$ctxStmt = $conn->prepare("SELECT student_id, class_code FROM student_flexible_grades WHERE id = ? LIMIT 1");
if($ctxStmt){ $ctxStmt->bind_param('i',$grade_id); $ctxStmt->execute(); $gradeRow = $ctxStmt->get_result()->fetch_assoc(); $ctxStmt->close(); }

if ($grade_id <= 0) {
echo json_encode(['success' => false, 'message' => 'Invalid grade ID']);
exit;
}

try {
    // Attempt legacy grades table deletion first
    $stmt = $conn->prepare("DELETE FROM grades WHERE id = ?");
    $stmt->bind_param("i", $grade_id);
    $stmt->execute();
    $affected = $stmt->affected_rows; $stmt->close();

    if($affected === 0){
        // Try flexible grading table
        $stmt2 = $conn->prepare("DELETE FROM student_flexible_grades WHERE id = ?");
        $stmt2->bind_param('i',$grade_id);
        $stmt2->execute();
        $affected = $stmt2->affected_rows; $stmt2->close();
        if($affected>0 && $gradeRow){
            // Recompute term grades for this student/class
            recomputeSingle($conn,$gradeRow['student_id'],$gradeRow['class_code']);
        }
    } else if($affected>0 && $gradeRow){
        // If legacy deletion but we had context, recompute anyway
        recomputeSingle($conn,$gradeRow['student_id'],$gradeRow['class_code']);
    }

    if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Grade deleted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Grade not found or already deleted']);
    }
} catch (Exception $e) {
    error_log("Delete grade error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete grade']);
}

function recomputeSingle($conn,$student_id,$class_code){
    // Lightweight inline recompute (mirrors update logic, lowercase statuses)
    $wStmt = $conn->prepare("SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code=? LIMIT 1");
    $wStmt->bind_param('s',$class_code); $wStmt->execute(); $wRow=$wStmt->get_result()->fetch_assoc(); $wStmt->close();
    $midtermWeight = $wRow ? floatval($wRow['midterm_weight']) : 40.0;
    $finalsWeight  = $wRow ? floatval($wRow['finals_weight'])  : 60.0;
    $compStmt=$conn->prepare("SELECT id, percentage, term_type FROM grading_components WHERE class_code=?");
    $compStmt->bind_param('s',$class_code); $compStmt->execute(); $compRes=$compStmt->get_result(); $components=[]; while($c=$compRes->fetch_assoc()) $components[]=$c; $compStmt->close();
    $colsStmt=$conn->prepare("SELECT gcc.id, gcc.component_id, gcc.max_score FROM grading_component_columns gcc JOIN grading_components gc ON gcc.component_id=gc.id WHERE gc.class_code=?");
    $colsStmt->bind_param('s',$class_code); $colsStmt->execute(); $colsRes=$colsStmt->get_result(); $columnsByComponent=[]; while($col=$colsRes->fetch_assoc()) $columnsByComponent[$col['component_id']][]=$col; $colsStmt->close();
    $gradesStmt=$conn->prepare("SELECT g.raw_score, gcc.component_id FROM student_flexible_grades g JOIN grading_component_columns gcc ON g.column_id=gcc.id WHERE g.class_code=? AND g.student_id=?");
    $gradesStmt->bind_param('ss',$class_code,$student_id); $gradesStmt->execute(); $gradesRes=$gradesStmt->get_result(); $scores=[]; while($gr=$gradesRes->fetch_assoc()){ $cid=$gr['component_id']; if(!isset($scores[$cid])) $scores[$cid]=['earned'=>0.0,'possible'=>0.0]; $scores[$cid]['earned'] += ($gr['raw_score']===null?0.0:floatval($gr['raw_score'])); } $gradesStmt->close();
    foreach($components as $comp){ $cid=$comp['id']; if(!isset($scores[$cid])) $scores[$cid]=['earned'=>0.0,'possible'=>0.0]; $possible=0.0; if(isset($columnsByComponent[$cid])) foreach($columnsByComponent[$cid] as $col){ $possible+=floatval($col['max_score']); } $scores[$cid]['possible']=$possible; }
    $midtermWeightedSum=0.0; $finalsWeightedSum=0.0; $midtermWeightTotal=0.0; $finalsWeightTotal=0.0; $finalsHasScore=false;
    foreach($components as $comp){ $cid=$comp['id']; $earned=$scores[$cid]['earned']; $possible=$scores[$cid]['possible']; $rawPct=($possible>0?($earned/$possible)*100.0:0.0); $pct=floatval($comp['percentage']); if($comp['term_type']==='midterm'){ $midtermWeightedSum+=$rawPct*$pct; $midtermWeightTotal+=$pct; } else { $finalsWeightedSum+=$rawPct*$pct; $finalsWeightTotal+=$pct; if($earned>0) $finalsHasScore=true; } }
    $midterm_percentage=($midtermWeightTotal>0? $midtermWeightedSum/$midtermWeightTotal:0.0);
    $finals_percentage =($finalsWeightTotal>0? $finalsWeightedSum/$finalsWeightTotal:0.0);
    $term_percentage = ($midterm_percentage * ($midtermWeight/100.0)) + ($finals_percentage * ($finalsWeight/100.0));
    $hasManualCol=false; $colCheck=$conn->query("SHOW COLUMNS FROM grade_term LIKE 'status_manually_set'"); if($colCheck && $colCheck->num_rows>0) $hasManualCol=true;
    $rowStmt=$conn->prepare("SELECT id, grade_status, term_grade, lacking_requirements".($hasManualCol?", status_manually_set":"")." FROM grade_term WHERE student_id=? AND class_code=? LIMIT 1");
    $rowStmt->bind_param('ss',$student_id,$class_code); $rowStmt->execute(); $existing=$rowStmt->get_result()->fetch_assoc(); $rowStmt->close();
    $manualFrozen=$hasManualCol && $existing && strtolower($existing['status_manually_set'])==='yes'; $lackingReq=$existing?$existing['lacking_requirements']:null; $midtermOnly=($finalsWeightTotal==0)||!$finalsHasScore;
    $grade_status=null; $term_grade=null;
    if($manualFrozen && $existing){ $grade_status=$existing['grade_status']; $term_grade=$existing['term_grade']; }
    else if($lackingReq==='yes'){ $grade_status='incomplete'; }
    else if($midtermOnly){ $grade_status='incomplete'; }
    else { if($term_percentage<57.0)$grade_status='failed'; elseif($term_percentage<60.0)$grade_status='incomplete'; else $grade_status='passed'; }
    if($grade_status==='passed' && !$manualFrozen){
        if($term_percentage>=96.0)$term_grade='4.0'; elseif($term_percentage>=90.0)$term_grade='3.5'; elseif($term_percentage>=84.0)$term_grade='3.0'; elseif($term_percentage>=78.0)$term_grade='2.5'; elseif($term_percentage>=72.0)$term_grade='2.0'; elseif($term_percentage>=66.0)$term_grade='1.5'; else $term_grade='1.0';
    } else if($manualFrozen && $existing){ $term_grade=$existing['term_grade']; } else { $term_grade=null; }
    if($grade_status==='passed' && $term_percentage<60.0){ $grade_status=($term_percentage<57.0?'failed':'incomplete'); $term_grade=null; }
    $gm=new GradesModel($conn);
    $gradeData=[ 'student_id'=>$student_id,'class_code'=>$class_code,'midterm_percentage'=>number_format($midterm_percentage,2,'.',''),'finals_percentage'=>number_format($finals_percentage,2,'.',''),'term_percentage'=>number_format($term_percentage,2,'.',''),'grade_status'=>$grade_status,'term_grade'=>$term_grade,'lacking_requirements'=>$lackingReq??'' ];
    if($manualFrozen && $existing){ $gradeData['grade_status']=$existing['grade_status']; $gradeData['term_grade']=$existing['term_grade']; }
    try{ $gm->saveGrades($gradeData,null); }catch(Exception $e){ error_log('delete_grade recompute save error '.$e->getMessage()); }
}
?>