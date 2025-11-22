<?php
// get_rating_sheet_data.php
// Returns JSON data for building a Rating Sheet PDF client-side.
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}
$faculty_id = $_SESSION['user_id'];
$class_code = $_GET['class_code'] ?? '';
if ($class_code==='') { echo json_encode(['success'=>false,'message'=>'class_code required']); exit(); }

// Verify ownership
$own = $conn->prepare("SELECT class_id, section, academic_year, term, course_code FROM class WHERE class_code=? AND faculty_id=? LIMIT 1");
$own->bind_param('si',$class_code,$faculty_id);
$own->execute();
$classRow = $own->get_result()->fetch_assoc();
$own->close();
if(!$classRow){ echo json_encode(['success'=>false,'message'=>'Class not found or access denied']); exit(); }

// Grades
$stmt = $conn->prepare("SELECT tg.student_id, s.first_name, s.last_name, tg.midterm_percentage, tg.finals_percentage, tg.term_percentage, tg.grade_status, tg.term_grade, tg.lacking_requirements, tg.status_manually_set, tg.is_encrypted FROM grade_term tg JOIN student s ON tg.student_id=s.student_id WHERE tg.class_code=? ORDER BY tg.student_id");
$stmt->bind_param('s',$class_code);
$stmt->execute();
$res = $stmt->get_result();
$rows=[]; $passed=0; $failed=0; $inc=0; $enc=0;
while($r=$res->fetch_assoc()){
    $status = $r['grade_status'];
    if($status==='PASSED') $passed++; elseif($status==='FAILED') $failed++; elseif($status==='INC') $inc++;
    if($r['is_encrypted']==='1' || strtolower($r['is_encrypted'])==='yes') $enc++;
    $rows[] = [
        'student_id'=>$r['student_id'],
        'name'=>trim($r['last_name'].', '.$r['first_name']),
        'midterm_percentage'=>$r['midterm_percentage'],
        'finals_percentage'=>$r['finals_percentage'],
        'term_percentage'=>$r['term_percentage'],
        'grade_status'=>$status,
        'term_grade'=>($r['term_grade']!==null && $r['term_grade']!=='') ? $r['term_grade'] : '-',
        'lacking_requirements'=>$r['lacking_requirements'],
        'frozen'=>(isset($r['status_manually_set']) && strtolower($r['status_manually_set'])==='yes') ? 'YES' : '',
        'encrypted'=>($r['is_encrypted']==='1' || strtolower($r['is_encrypted'])==='yes') ? 'YES' : ''
    ];
}
$stmt->close();

echo json_encode([
    'success'=>true,
    'class'=>$classRow,
    'rows'=>$rows,
    'summary'=>[
        'passed'=>$passed,
        'failed'=>$failed,
        'inc'=>$inc,
        'encrypted'=>$enc,
        'total'=>count($rows)
    ]
]);
exit();
?>