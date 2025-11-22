<?php
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Create new Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Student Import');

// Row 1: Warning message (RED)
$sheet->setCellValue('A1', 'DO NOT EDIT OR CHANGE ROW 2');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 14,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'FF0000']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);
$sheet->getRowDimension(1)->setRowHeight(30);

// Row 2: Headers (BLUE)
$headers = ['Student ID', 'Last Name', 'First Name', 'Middle Initial', 'Email', 'Birthday', 'Status'];
$sheet->fromArray($headers, NULL, 'A2');

$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];
$sheet->getStyle('A2:G2')->applyFromArray($headerStyle);
$sheet->getRowDimension(2)->setRowHeight(25);

// Row 3-5: Example data
$exampleData = [
    ['2024-123456', 'Dela Cruz', 'Juan', 'A', 'juan.delacruz@example.com', '2004-01-15', 'active'],
    ['2024-123457', 'Santos', 'Maria', 'B', 'maria.santos@example.com', '2003-12-20', 'active'],
    ['2024-123458', 'Reyes', 'Pedro', 'C', 'pedro.reyes@example.com', '2004-03-10', 'active']
];

$sheet->fromArray($exampleData, NULL, 'A3');

// Style example data rows
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];
$sheet->getStyle('A3:G5')->applyFromArray($dataStyle);

// Set column widths
$sheet->getColumnDimension('A')->setWidth(15); // Student ID
$sheet->getColumnDimension('B')->setWidth(18); // Last Name
$sheet->getColumnDimension('C')->setWidth(18); // First Name
$sheet->getColumnDimension('D')->setWidth(12); // Middle Initial
$sheet->getColumnDimension('E')->setWidth(30); // Email
$sheet->getColumnDimension('F')->setWidth(12); // Birthday
$sheet->getColumnDimension('G')->setWidth(12); // Status

// Add instructions in a comment/note on cell A1
$sheet->getComment('A1')->getText()->createTextRun(
    "STUDENT BULK IMPORT TEMPLATE\n\n" .
    "Instructions:\n" .
    "1. Do NOT edit or delete Row 2 (headers)\n" .
    "2. Start entering data from Row 3\n" .
    "3. Delete the example rows (3-5) before importing\n\n" .
    "Required Fields:\n" .
    "- Student ID: Format YYYY-XXXXXX (e.g., 2024-123456)\n" .
    "- Last Name, First Name, Email\n\n" .
    "Optional Fields:\n" .
    "- Middle Initial: 1-2 characters\n" .
    "- Birthday: Format YYYY-MM-DD\n" .
    "- Status: active, inactive, graduated, transferred, suspended, or pending\n\n" .
    "Notes:\n" .
    "- All students will receive activation emails\n" .
    "- Maximum 500 students per upload\n" .
    "- Email and Student ID must be unique"
);

// Set comment size
$sheet->getComment('A1')->setWidth('400px');
$sheet->getComment('A1')->setHeight('300px');

// Output file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="NU_Student_Import_Template.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>