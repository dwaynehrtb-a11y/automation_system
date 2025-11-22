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
    $sheet->setTitle('Class Import Template');

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(8);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(25);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(20);

    // ROW 1: Warning Row
    $sheet->mergeCells('A1:H1');
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
    $headers = ['Section', 'Academic Year', 'Term', 'Course Code', 'Day', 'Time', 'Room', 'Faculty Employee ID'];
    $sheet->fromArray($headers, NULL, 'A2');
    $sheet->getStyle('A2:H2')->applyFromArray([
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
        ['INF221', '24', 'T1', 'CCDATRCL', 'Monday', '08:00 AM - 10:00 AM', 'Room 101', '2022-180250'],
        ['INF221', '24', 'T1', 'CCDATRCL', 'Wednesday', '08:00 AM - 10:00 AM', 'Room 101', '2022-180250'],
        ['INF222', '25', 'T2', 'CCDATRCL', 'Tuesday', '10:00 AM - 12:00 PM', 'Lab 632', '2023-182914']
    ];

    $sheet->fromArray($sampleData, NULL, 'A3');

    // Style sample data
    $sheet->getStyle('A3:H5')->applyFromArray([
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'E5E7EB']
            ]
        ]
    ]);

    foreach ([3, 4, 5] as $row) {
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    // Clear the output buffer
    ob_end_clean();

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="NU_Class_Import_Template.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    // Write to output
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}