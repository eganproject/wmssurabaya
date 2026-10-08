<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CbmForecastExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private const HEADER_ROW = 4;

    public function __construct(
        private Collection $rows,
        private array $summary,
        private ?string $reportTitle,
        private array $containers,
    ) {
    }

    public function title(): string
    {
        return 'Forecast CBM';
    }

    public function array(): array
    {
        $data = [
            [$this->text('FORECAST CBM'.($this->reportTitle ? ' - '.$this->reportTitle : ''))],
            ['Dicetak: '.now()->format('d/m/Y H:i').' | CBM per koli = P x L x T (cm) / 1.000.000'],
            [],
            ['No', 'SKU', 'Nama Item', 'Status Produk', 'Panjang (cm)', 'Lebar (cm)', 'Tinggi (cm)', 'CBM / Koli (m³)', 'Jumlah Koli', 'Total CBM (m³)', 'Keterangan'],
        ];

        foreach ($this->rows->values() as $index => $row) {
            $data[] = [
                $index + 1,
                $this->text($row['sku']),
                $this->text($row['name'] ?? '-'),
                $row['found'] ? ($row['is_active'] ? 'Aktif' : 'Nonaktif') : '-',
                $row['length'],
                $row['width'],
                $row['height'],
                $row['cbm_per_koli'],
                $row['koli'],
                $row['total_cbm'],
                $row['note'] ?? '',
            ];
        }

        $data[] = ['', '', 'TOTAL', '', '', '', '', '', $this->summary['total_koli'], $this->summary['total_cbm'], ''];
        $data[] = [];
        $data[] = ['Ringkasan'];
        $data[] = ['Jumlah baris SKU', $this->summary['sku_count']];
        $data[] = ['Total koli', $this->summary['total_koli']];
        $data[] = ['Total CBM (m³)', $this->summary['total_cbm']];
        $data[] = ['Baris tanpa CBM', $this->summary['incomplete_count'].' baris ('.$this->summary['incomplete_koli'].' koli, tidak terhitung)'];
        foreach ($this->containers as $container) {
            $data[] = [
                'Estimasi kontainer '.$container['label'].' ('.$container['capacity'].' m³ nominal)',
                $this->summary['total_cbm'] > 0 ? (int) ceil($this->summary['total_cbm'] / $container['capacity']) : 0,
            ];
        }

        return $data;
    }

    // Cegah teks input user terbaca sebagai formula Excel.
    private function text(string $value): string
    {
        return str_starts_with($value, '=') ? "'".$value : $value;
    }

    public function styles(Worksheet $sheet): array
    {
        $firstDataRow = self::HEADER_ROW + 1;
        $totalRow = self::HEADER_ROW + $this->rows->count() + 1;
        $lastDataRow = $totalRow - 1;

        $sheet->mergeCells('A1:K1');
        $sheet->mergeCells('A2:K2');
        $sheet->freezePane('A'.$firstDataRow);
        $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(40);
        $sheet->getStyle("E{$firstDataRow}:G{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("H{$firstDataRow}:H{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.000000');
        $sheet->getStyle("I{$firstDataRow}:I{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("J{$firstDataRow}:J{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
        $sheet->getStyle('B'.($totalRow + 5))->getNumberFormat()->setFormatCode('#,##0.0000');
        if ($lastDataRow >= $firstDataRow) {
            $sheet->getStyle('A'.self::HEADER_ROW.":K{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        }

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true, 'color' => ['rgb' => '666666']]],
            self::HEADER_ROW => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E78']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            ],
            $totalRow => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DDEBF7']],
            ],
            $totalRow + 2 => ['font' => ['bold' => true]],
        ];
    }
}
