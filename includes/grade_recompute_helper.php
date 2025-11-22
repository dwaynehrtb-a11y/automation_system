<?php
/**
 * Recompute helper for flexible grading term metrics.
 * Provides recompute_term_grade($conn,$student_id,$class_code) returning associative summary.
 * Statuses: passed|failed|incomplete (lowercase) matching DB enum.
 */
if(!function_exists('recompute_term_grade')){
    function recompute_term_grade($conn,$student_id,$class_code){
        // Fetch weights
        $wStmt = $conn->prepare("SELECT midterm_weight, finals_weight FROM class_term_weights WHERE class_code=? LIMIT 1");
        $wStmt->bind_param('s',$class_code); $wStmt->execute(); $wRow=$wStmt->get_result()->fetch_assoc(); $wStmt->close();
        $midtermWeight = $wRow ? floatval($wRow['midterm_weight']) : 40.0;
        $finalsWeight  = $wRow ? floatval($wRow['finals_weight'])  : 60.0;
        // Components
        $compStmt=$conn->prepare("SELECT id, percentage, term_type FROM grading_components WHERE class_code=?");
        $compStmt->bind_param('s',$class_code); $compStmt->execute(); $compRes=$compStmt->get_result(); $components=[]; while($c=$compRes->fetch_assoc()) $components[]=$c; $compStmt->close();
        // Columns per component
        $colsStmt=$conn->prepare("SELECT gcc.id, gcc.component_id, gcc.max_score FROM grading_component_columns gcc JOIN grading_components gc ON gcc.component_id=gc.id WHERE gc.class_code=?");
        $colsStmt->bind_param('s',$class_code); $colsStmt->execute(); $colsRes=$colsStmt->get_result(); $columnsByComponent=[]; while($col=$colsRes->fetch_assoc()) $columnsByComponent[$col['component_id']][]=$col; $colsStmt->close();
        // Student raw scores
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
        require_once __DIR__.'/GradesModel.php';
        $gm=new GradesModel($conn);
        $gradeData=[ 'student_id'=>$student_id,'class_code'=>$class_code,'midterm_percentage'=>number_format($midterm_percentage,2,'.',''),'finals_percentage'=>number_format($finals_percentage,2,'.',''),'term_percentage'=>number_format($term_percentage,2,'.',''),'grade_status'=>$grade_status,'term_grade'=>$term_grade,'lacking_requirements'=>$lackingReq??'' ];
        if($manualFrozen && $existing){ $gradeData['grade_status']=$existing['grade_status']; $gradeData['term_grade']=$existing['term_grade']; }
        try{ $gm->saveGrades($gradeData,null); }catch(Exception $e){ error_log('recompute helper save error '.$e->getMessage()); }
        return [
            'student_id'=>$student_id,
            'class_code'=>$class_code,
            'midterm_percentage'=>$midterm_percentage,
            'finals_percentage'=>$finals_percentage,
            'term_percentage'=>$term_percentage,
            'grade_status'=>$grade_status,
            'term_grade'=>$term_grade
        ];
    }
}
