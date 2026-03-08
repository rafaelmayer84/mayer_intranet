<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Dompdf\Dompdf;
use Dompdf\Options;

class ReportExportService
{
    /**
     * Exporta dados para Excel (.xlsx) ou PDF
     *
     * @param string $type 'xlsx' ou 'pdf'
     * @param string $title Título do relatório
     * @param array $columns [['key' => 'campo', 'label' => 'Rótulo', 'format' => 'currency|date|number|text']]
     * @param Collection $data Dados a exportar
     * @param array $totals Linha de totalização ['campo' => valor]
     * @param string $orientation 'portrait' ou 'landscape'
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public static function export(
        string $type,
        string $title,
        array $columns,
        Collection $data,
        array $totals = [],
        string $orientation = 'landscape'
    ) {
        if ($type === 'xlsx') {
            return self::exportXlsx($title, $columns, $data, $totals);
        }

        return self::exportPdf($title, $columns, $data, $totals, $orientation);
    }

    private static function exportXlsx(string $title, array $columns, Collection $data, array $totals)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));

        // Header do relatório
        $lastCol = self::colLetter(count($columns) - 1);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('385776'));
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', 'Gerado em ' . now()->format('d/m/Y H:i') . ' — Mayer Advogados');
        $sheet->getStyle('A2')->getFont()->setSize(9)->setItalic(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Cabeçalho das colunas (linha 4)
        $headerRow = 4;
        foreach ($columns as $i => $col) {
            $cell = self::colLetter($i) . $headerRow;
            $sheet->setCellValue($cell, $col['label']);
        }

        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '385776']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2B4A66']]],
        ]);

        // Dados
        $row = $headerRow + 1;
        foreach ($data as $item) {
            foreach ($columns as $i => $col) {
                $cell = self::colLetter($i) . $row;
                $value = is_array($item) ? ($item[$col['key']] ?? '') : ($item->{$col['key']} ?? '');
                // Force type for non-numeric values
                if ($value === '' || $value === null) {
                    $sheet->setCellValue($cell, '');
                } elseif (is_numeric($value)) {
                    $sheet->setCellValue($cell, (float) $value);
                } else {
                    $sheet->setCellValueExplicit($cell, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }

                $format = $col['format'] ?? 'text';
                if ($format === 'currency' && is_numeric($value)) {
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                } elseif ($format === 'date') {
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('DD/MM/YYYY');
                } elseif ($format === 'percent') {
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('0.0%');
                }
            }

            // Zebra striping
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F4F8');
            }

            $row++;
        }

        // Bordas nos dados
        $dataRange = "A{$headerRow}:{$lastCol}" . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');

        // Totalização
        if (!empty($totals)) {
            foreach ($columns as $i => $col) {
                $cell = self::colLetter($i) . $row;
                if (isset($totals[$col['key']])) {
                    $sheet->setCellValue($cell, $totals[$col['key']]);
                } elseif ($i === 0) {
                    $sheet->setCellValue($cell, 'TOTAL');
                }
            }
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        }

        // Autofit
        foreach ($columns as $i => $col) {
            $sheet->getColumnDimension(self::colLetter($i))->setAutoSize(true);
        }

        // Download
        $filename = self::slugify($title) . '_' . now()->format('Ymd_Hi') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        $temp = tempnam(sys_get_temp_dir(), 'report_');
        $writer->save($temp);

        return response()->download($temp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private static function exportPdf(string $title, array $columns, Collection $data, array $totals, string $orientation)
    {
        $html = view('reports._pdf-template', [
            'title'       => $title,
            'columns'     => $columns,
            'data'        => $data,
            'totals'      => $totals,
            'generated'   => now()->format('d/m/Y H:i'),
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'sans-serif');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        $filename = self::slugify($title) . '_' . now()->format('Ymd_Hi') . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private static function colLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26) - 1;
        }
        return $letter;
    }

    private static function slugify(string $text): string
    {
        $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
        $text = preg_replace('/\s+/', '_', trim($text));
        return strtolower($text);
    }
}
