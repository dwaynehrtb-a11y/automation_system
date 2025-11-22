<?php
// compute_single_term_grade.php
// Recompute term percentages & status for one student in a class (real-time update)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../config/encryption.php';
require_once '../../config/helpers.php';
require_once '../../config/middleware.php';
require_once '../../includes/GradesModel.php';
require_once '../../config/audit_logger.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
  echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
  echo json_encode(['success'=>false,'message'=>'Invalid CSRF']); exit; }

$faculty_id = $_SESSION['user_id'];
$class_code = $_POST['class_code'] ?? '';
$student_id = $_POST['student_id'] ?? '';
if ($class_code==='' || $student_id==='') { echo json_encode(['success'=>false,'message'=>'Missing class_code or student_id']); exit; }

// Ownership check
$own = $conn->prepare("SELECT class_id FROM class WHERE class_code=? AND faculty_id=? LIMIT 1");
$own->bind_param('si',$class_code,$faculty_id); $own->execute();
if ($own->get_result()->num_rows===0){ echo json_encode(['success'=>false,'message'=>'Class access denied']); exit; }
$own->close();

// Term weights
$wStmt = $conn->prepare("SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code=? LIMIT 1");
$wStmt->bind_param('s',$class_code); $wStmt->execute(); $wRow = $wStmt->get_result()->fetch_assoc(); $wStmt->close();
$midtermWeight = $wRow ? floatval($wRow['midterm_weight']) : 40.0;
$finalsWeight  = $wRow ? floatval($wRow['finals_weight'])  : 60.0;

// Components
$compStmt = $conn->prepare("SELECT id, component_name, percentage, term_type FROM grading_components WHERE class_code=? ORDER BY term_type, order_index, id");
$compStmt->bind_param('s',$class_code); $compStmt->execute(); $compRes=$compStmt->get_result();
$components=[]; while($c=$compRes->fetch_assoc()) $components[]=$c; $compStmt->close();

// Columns per component
$colsStmt=$conn->prepare("SELECT gcc.id, gcc.component_id, gcc.max_score FROM grading_component_columns gcc JOIN grading_components gc ON gcc.component_id=gc.id WHERE gc.class_code=?");
$colsStmt->bind_param('s',$class_code); $colsStmt->execute(); $colsRes=$colsStmt->get_result();
$columnsByComponent=[]; while($col=$colsRes->fetch_assoc()) $columnsByComponent[$col['component_id']][]=$col; $colsStmt->close();

// Scores for this student
$gradesStmt=$conn->prepare("SELECT g.column_id, g.raw_score, gcc.component_id FROM student_flexible_grades g JOIN grading_component_columns gcc ON g.column_id=gcc.id WHERE g.class_code=? AND g.student_id=?");
$gradesStmt->bind_param('ss',$class_code,$student_id); $gradesStmt->execute(); $gradesRes=$gradesStmt->get_result();
$scores=[]; while($gr=$gradesRes->fetch_assoc()){ $cid=$gr['component_id']; if(!isset($scores[$cid])) $scores[$cid]=['earned'=>0.0,'possible'=>0.0]; $scores[$cid]['earned'] += ($gr['raw_score']===null?0.0:floatval($gr['raw_score'])); }
$gradesStmt->close();
// Add possibles
foreach($components as $comp){ $cid=$comp['id']; if(!isset($scores[$cid])) $scores[$cid]=['earned'=>0.0,'possible'=>0.0]; $possible=0.0; if(isset($columnsByComponent[$cid])){ foreach($columnsByComponent[$cid] as $col){ $possible += floatval($col['max_score']); } } $scores[$cid]['possible']=$possible; }

// Totals per term
$midtermWeightedSum=0.0; $finalsWeightedSum=0.0; $midtermWeightTotal=0.0; $finalsWeightTotal=0.0; $finalsHasScore=false;
foreach($components as $comp){ $cid=$comp['id']; $earned=$scores[$cid]['earned']; $possible=$scores[$cid]['possible']; $rawPct=($possible>0?($earned/$possible)*100.0:0.0); $pct=floatval($comp['percentage']); if($comp['term_type']==='midterm'){ $midtermWeightedSum+=$rawPct*$pct; $midtermWeightTotal+=$pct; } else { $finalsWeightedSum+=$rawPct*$pct; $finalsWeightTotal+=$pct; if($earned>0) $finalsHasScore=true; } }
$midterm_percentage = ($midtermWeightTotal>0? $midtermWeightedSum/$midtermWeightTotal : 0.0);
$finals_percentage  = ($finalsWeightTotal>0? $finalsWeightedSum/$finalsWeightTotal : 0.0);

// Existing row
$hasManualCol=false; $colCheck=$conn->query("SHOW COLUMNS FROM grade_term LIKE 'status_manually_set'"); if($colCheck && $colCheck->num_rows>0) $hasManualCol=true;
$rowStmt=$conn->prepare("SELECT id, grade_status, term_grade, lacking_requirements, is_encrypted".($hasManualCol?", status_manually_set":"")." FROM grade_term WHERE student_id=? AND class_code=? LIMIT 1");
$rowStmt->bind_param('ss',$student_id,$class_code); $rowStmt->execute(); $existing=$rowStmt->get_result()->fetch_assoc(); $rowStmt->close();
$manualFrozen = $hasManualCol && $existing && strtolower($existing['status_manually_set'])==='yes';
$lackingReq = $existing ? $existing['lacking_requirements'] : null;
$midtermOnly = ($finalsWeightTotal==0) || !$finalsHasScore;

// Provisional term percentage for status band
$term_percentage = ($midterm_percentage * ($midtermWeight/100.0)) + ($finals_percentage * ($finalsWeight/100.0));
// Align statuses with DB enum (passed, failed, incomplete, dropped)
$grade_status=null; $term_grade=null;
if($manualFrozen && $existing){ $grade_status=$existing['grade_status']; $term_grade=$existing['term_grade']; }
else if($lackingReq==='yes'){ $grade_status='incomplete'; }
else if($midtermOnly){ $grade_status='incomplete'; }
else {
  if($term_percentage < 57.0) $grade_status='failed';
  else if($term_percentage < 60.0) $grade_status='incomplete';
  else $grade_status='passed';
}
if($grade_status==='passed' && !$manualFrozen){
  if($term_percentage >= 96.0) $term_grade='4.0';
  else if($term_percentage >= 90.0) $term_grade='3.5';
  else if($term_percentage >= 84.0) $term_grade='3.0';
  else if($term_percentage >= 78.0) $term_grade='2.5';
  else if($term_percentage >= 72.0) $term_grade='2.0';
  else if($term_percentage >= 66.0) $term_grade='1.5';
  else $term_grade='1.0';
} else if($manualFrozen && $existing){ $term_grade=$existing['term_grade']; } else { $term_grade=null; }

// Sanity override (prevent passed below 60%)
if($grade_status==='passed' && $term_percentage < 60.0){ $grade_status = ($term_percentage<57.0?'failed':'incomplete'); $term_grade=null; }

// Persist
$gm=new GradesModel($conn);
$gradeData=[ 'student_id'=>$student_id,'class_code'=>$class_code,'midterm_percentage'=>number_format($midterm_percentage,2,'.',''),'finals_percentage'=>number_format($finals_percentage,2,'.',''),'term_percentage'=>number_format($term_percentage,2,'.',''),'grade_status'=>$grade_status,'term_grade'=>$term_grade,'lacking_requirements'=>$lackingReq??'' ];
if($manualFrozen && $existing){ $gradeData['grade_status']=$existing['grade_status']; $gradeData['term_grade']=$existing['term_grade']; }
try { $gm->saveGrades($gradeData,$faculty_id); } catch(Exception $e){ error_log('single save error '.$e->getMessage()); }

audit_log($faculty_id,'faculty','compute_single_term_grade',$class_code,['student_id'=>$student_id],1,0);
echo json_encode(['success'=>true,'student_id'=>$student_id,'midterm_percentage'=>$gradeData['midterm_percentage'],'finals_percentage'=>$gradeData['finals_percentage'],'term_percentage'=>$gradeData['term_percentage'],'grade_status'=>$gradeData['grade_status'],'term_grade'=>$gradeData['term_grade'],'manual_frozen'=>$manualFrozen,'midterm_only'=>$midtermOnly]);
exit;
?>