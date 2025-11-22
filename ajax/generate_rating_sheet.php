<?php
// generate_rating_sheet.php
// Exports NU Rating Sheet for a class as Excel
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Auth: must be faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo 'Unauthorized';
    exit();
}
$faculty_id = $_SESSION['user_id'];
$class_code = $_GET['class_code'] ?? '';
if ($class_code === '') { http_response_code(400); echo 'class_code required'; exit(); }

// Verify ownership
$own = $conn->prepare("SELECT class_id, section, academic_year, term, course_code FROM class WHERE class_code=? AND faculty_id=? LIMIT 1");
$own->bind_param('si', $class_code, $faculty_id);
$own->execute();
$classRow = $own->get_result()->fetch_assoc();
$own->close();
if (!$classRow) { http_response_code(404); echo 'Class not found or access denied'; exit(); }

// Fetch grades joined with student info
$stmt = $conn->prepare("SELECT tg.student_id, s.first_name, s.last_name, tg.midterm_percentage, tg.finals_percentage, tg.term_percentage, tg.grade_status, tg.term_grade, tg.lacking_requirements, tg.status_manually_set, tg.is_encrypted FROM grade_term tg JOIN student s ON tg.student_id=s.student_id WHERE tg.class_code=? ORDER BY tg.student_id");
$stmt->bind_param('s', $class_code);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();

// Spreadsheet setup
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rating Sheet');

// Column widths
$widths = [12,24,12,12,12,12,12,16,14,12];
$cols = range('A','Z');
foreach ($widths as $i=>$w) { $sheet->getColumnDimension($cols[$i])->setWidth($w); }

// Header block (merged)
$sheet->mergeCells('A1:J1');
$title = 'NU RATING SHEET - '.$class_code.' ('.$classRow['course_code'].')';
$sheet->setCellValue('A1', $title);
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold'=>true,'size'=>16,'color'=>['rgb'=>'ffffff']],
    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'003082']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(34);

// Class metadata row
$meta = 'Section: '.$classRow['section'].'   AY: '.$classRow['academic_year'].'   Term: '.$classRow['term'].'   Generated: '.date('Y-m-d H:i');
$sheet->mergeCells('A2:J2');
$sheet->setCellValue('A2', $meta);
$sheet->getStyle('A2')->applyFromArray([
    'font' => ['italic'=>true,'size'=>11,'color'=>['rgb'=>'1f2937']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'f5f7fa']],
]);
$sheet->getRowDimension(2)->setRowHeight(20);

// Table header row
$headers = ['Student ID','Student Name','Midterm %','Finals %','Term %','Status','Discrete Grade','Lacking Req','Frozen','Encrypted'];
$sheet->fromArray($headers,NULL,'A3');
$sheet->getStyle('A3:J3')->applyFromArray([
    'font'=>['bold'=>true,'color'=>['rgb'=>'ffffff']],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'003082']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'000000']]]
]);
$sheet->getRowDimension(3)->setRowHeight(22);

// Data rows
$rowNum = 4; $passedCount=0; $failedCount=0; $incCount=0; $encCount=0;
foreach ($rows as $r) {
    $name = trim($r['last_name'].', '.$r['first_name']);
    $frozen = (isset($r['status_manually_set']) && strtolower($r['status_manually_set'])==='yes') ? 'YES' : '';
    $encrypted = ($r['is_encrypted']==='1' || $r['is_encrypted']==='yes') ? 'YES' : '';
    if ($encrypted==='YES') { $encCount++; }
    switch ($r['grade_status']) {
        case 'PASSED': $passedCount++; break;
        case 'FAILED': $failedCount++; break;
        case 'INC': $incCount++; break;
    }
    $sheet->fromArray([
        $r['student_id'],
        $name,
        $r['midterm_percentage'],
        $r['finals_percentage'],
        $r['term_percentage'],
        $r['grade_status'],
        ($r['term_grade']!==null && $r['term_grade']!=='') ? $r['term_grade'] : '-',
        $r['lacking_requirements'],
        $frozen,
        $encrypted
    ],NULL,'A'.$rowNum);
    // Style
    $sheet->getStyle('A'.$rowNum.':J'.$rowNum)->applyFromArray([
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'e5e7eb']]]
    ]);
    // Left align name
    $sheet->getStyle('B'.$rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    // Status coloring
    $statusCell = 'F'.$rowNum;
    $status = $r['grade_status'];
    $color = '1e293b';
    if ($status==='PASSED') $color = '059669'; elseif ($status==='FAILED') $color='dc2626'; elseif ($status==='INC') $color='d97706';
    $sheet->getStyle($statusCell)->applyFromArray([
        'font'=>['bold'=>true,'color'=>['rgb'=>'ffffff']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$color]]
    ]);
    if ($rowNum %2 ===0) {
        $sheet->getStyle('A'.$rowNum.':J'.$rowNum)->applyFromArray([
            'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'f8fafc']]
        ]);
    }
    $rowNum++;
}

// Summary footer
$sheet->mergeCells('A'.$rowNum.':J'.($rowNum+1));
$summary = 'PASSED: '.$passedCount.'   FAILED: '.$failedCount.'   INC: '.$incCount.'   ENCRYPTED: '.$encCount.'   TOTAL: '.count($rows);
$sheet->setCellValue('A'.$rowNum, $summary);
$sheet->getStyle('A'.$rowNum)->applyFromArray([
    'font'=>['bold'=>true,'size'=>12,'color'=>['rgb'=>'003082']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'fde68a']]
]);
$sheet->getRowDimension($rowNum)->setRowHeight(26);

// Output
$fileName = 'NU_Rating_Sheet_'.$class_code.'_'.date('Ymd_His').'.xlsx';
$tempFile = sys_get_temp_dir().'/'.$fileName;
$writer = new Xlsx($spreadsheet);
$writer->save($tempFile);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$fileName.'"');
header('Content-Length: '.filesize($tempFile));
readfile($tempFile);
unlink($tempFile);
exit;
?>