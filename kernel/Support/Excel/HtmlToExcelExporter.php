<?php

namespace MacropaySolutions\Kernel\Support\Excel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Html;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RuntimeException;

class HtmlToExcelExporter
{
    /**
     * Streams an HTML string as an Excel download to the browser.
     *
     * IMPORTANT: Unlike the IteratorExporter, this HTML parser loads the entire
     * HTML structure and the resulting Spreadsheet model into memory. This is
     * excellent for styled reports but is NOT suitable for massive dataset exports.
     *
     * @throws \Throwable
     * 
     * @example for $configureSpreadsheet
     *
     *    function (Spreadsheet $spreadsheet) {
     *        $sheet = $spreadsheet->getActiveSheet();
     *        $columnWidths = [
     *            'A' => 20,
     *            'B' => 22,
     *        ];
     *
     *        foreach ($columnWidths as $col => $width) {
     *            $sheet->getColumnDimension($col)->setWidth($width);
     *        }
     *
     *        $stylesMap = [
     *            // Header row (Row 1): White bold text, dark gray background, thick borders
     *            'A1:P1' => [
     *                'font' => [
     *                    'bold' => true,
     *                    'color' => ['argb' => 'FFFFFF'],
     *                    'size' => 11,
     *                ],
     *                'fill' => [
     *                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
     *                    'color' => ['argb' => '595959'],
     *                ],
     *                'alignment' => [
     *                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
     *                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
     *                ],
     *                'borders' => [
     *                    'allBorders' => [
     *                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
     *                        'color' => ['argb' => '000000'],
     *                    ],
     *                ],
     *            ],
     *
     *            // Data rows range (e.g., E2 to O10): Subtotal row styling
     *            'E2:O10' => [
     *                'font' => [
     *                    'bold' => true,
     *                ],
     *                'fill' => [
     *                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
     *                    'color' => ['argb' => '00FF7F'], // Light green
     *                ],
     *                'borders' => [
     *                    'outline' => [
     *                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
     *                    ],
     *                ],
     *                'alignment' => [
     *                    'wrapText' => true,
     *                ],
     *            ],
     *
     *            // Specific cell range with muted/greyed-out text
     *            'F11:J15' => [
     *                'font' => [
     *                    'color' => ['argb' => 'AAAAAA'],
     *                ],
     *                'fill' => [
     *                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE,
     *                ],
     *            ],
     *
     *            // Grand total row (Row 20): Bold text, thick outline, gray background
     *            'D20:O20' => [
     *                'font' => [
     *                    'bold' => true,
     *                    'size' => 12,
     *                ],
     *                'fill' => [
     *                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
     *                    'color' => ['argb' => 'BBBBBB'],
     *                ],
     *                'borders' => [
     *                    'outline' => [
     *                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
     *                    ],
     *                ],
     *            ],
     *        ];
     *
     *        foreach ($stylesMap as $cellRange => $styleArray) {
     *            $sheet->getStyle($cellRange)->applyFromArray($styleArray);
     *        }
     *    }
     */
    public function streamDownload(
        string $htmlString,
        string $filename = 'export.xlsx',
        array $responseHeaders = [],
        ?callable $configureSpreadsheet = null
    ): StreamedResponse {
        return \app(StreamedResponse::class, [function () use ($htmlString, $configureSpreadsheet) {
            try {
                $spreadsheet = $this->getSpreadsheet($htmlString);

                if ($configureSpreadsheet !== null) {
                    $configureSpreadsheet($spreadsheet);
                }

                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save('php://output');
            } finally {
                if (($spreadsheet ?? null) instanceof Spreadsheet) {
                    $spreadsheet->disconnectWorksheets();
                }
            }
        }, 200, \array_merge([
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' =>
                HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, 'export.xlsx'),
            'Cache-Control' => 'max-age=0',
        ], $responseHeaders)]);
    }

    /**
     * Stores an HTML string as an Excel file on the filesystem.
     *
     * IMPORTANT: This method parses the entire HTML string in memory. It is not
     * recommended for extremely large data exports.
     *
     * To upload to a remote disk (like AWS S3), store the file locally in a
     * temporary path first, then write the contents of the temporary file to S3
     * using the filesystem driver.
     *
     * @example
     * $tmpFile = \tempnam(\sys_get_temp_dir(), 'excel_');
     *
     * try {
     *     \app(HtmlToExcelExporter::class)->store($view->toHtml(), $tmpFile);
     *     \app('filesystem')->disk('s3')->put('exports/file.xlsx', \fopen($tmpFile, 'r'));
     * } finally {
     *     \unlink($tmpFile);
     * }
     *
     * @throws \Throwable
     */
    public function store(
        string $htmlString,
        string $filePath,
        ?callable $configureSpreadsheet = null
    ): void {
        try {
            $spreadsheet = $this->getSpreadsheet($htmlString);

            if ($configureSpreadsheet !== null) {
                $configureSpreadsheet($spreadsheet);
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($filePath);
        } finally {
            if (($spreadsheet ?? null) instanceof Spreadsheet) {
                $spreadsheet->disconnectWorksheets();
            }
        }
    }

    protected function getSpreadsheet(string $htmlString): Spreadsheet
    {
        if (!\class_exists(Html::class)) {
            throw new RuntimeException(
                'phpoffice/phpspreadsheet is required to export HTML to Excel. Please install it.'
            );
        }

        $reader = new Html();

        return $reader->loadFromString($htmlString);
    }
}
