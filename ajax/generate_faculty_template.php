<?php
// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Start fresh output buffer
ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

try {
    // Create new Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Faculty Import Template');

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(30);
    $sheet->getColumnDimension('D')->setWidth(25);
    $sheet->getColumnDimension('E')->setWidth(30);

    // ROW 1: Warning Row
    $sheet->mergeCells('A1:E1');
    $sheet->setCellValue('A1', 'DO NOT EDIT OR CHANGE ROW 2');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 14,
            'color' => ['rgb' => 'DC2626']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FEF3C7']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THICK,
                'color' => ['rgb' => 'F59E0B']
            ]
        ]
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // ROW 2: Header Row
    $headers = ['Employee ID', 'Full Name', 'Email Address', 'Phone Number', 'Department'];
    $sheet->fromArray($headers, NULL, 'A2');
    $sheet->getStyle('A2:E2')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 12,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '003082']
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
    ]);
    $sheet->getRowDimension(2)->setRowHeight(25);

    // ROW 3-5: Sample Data
    $sampleData = [
        ['2022-180250', 'Dr. Juan Santos', 'juan.santos@university.edu', '09-555-1234', 'Computer Science'],
        ['2022-180251', 'Dr. Maria Cruz', 'maria.cruz@university.edu', '09-555-1235', 'Information Technology'],
        ['2023-182914', 'Dr. Robert Lopez', 'robert.lopez@university.edu', '09-555-1236', 'Computer Science']
    ];

    $sheet->fromArray($sampleData, NULL, 'A3');

    // Style sample data
    $sheet->getStyle('A3:E5')->applyFromArray([
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ]
    ]);

    // Set row heights for sample data
    for ($i = 3; $i <= 5; $i++) {
        $sheet->getRowDimension($i)->setRowHeight(20);
    }

    // ROW 7: Instructions
    $sheet->mergeCells('A7:E7');
    $sheet->setCellValue('A7', 'INSTRUCTIONS:');
    $sheet->getStyle('A7')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1F2937']]
    ]);

    $instructions = [
        '1. Employee ID must be unique and will be used to link faculty to classes',
        '2. Full Name is required and will be displayed in the system',
        '3. Email Address must be a valid email format',
        '4. Phone Number is optional (format: 09-XXX-XXXX)',
        '5. Department is optional but helps organize faculty records',
        '6. Do not change any columns in the header row (Row 2)',
        '7. Start adding faculty data from Row 3 onwards',
        '8. Each faculty member will receive login credentials automatically'
    ];

    $row = 8;
    foreach ($instructions as $instruction) {
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->setCellValue('A' . $row, $instruction);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '4B5563']],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP]
        ]);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;
    }

    // Clean output buffer before sending file
    ob_clean();

    // Generate file
    $fileName = 'Faculty_Import_Template_' . date('Y-m-d_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    // Clean output buffer before error
    ob_clean();
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error generating template: ' . $e->getMessage()
    ]);
    exit;
}
?>
