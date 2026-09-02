<?php

namespace App\Exports;

use App\Models\Warehouse;
use App\Support\StockMutationReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMutationSummarySheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private int $sourceTitleRow = 0;

    private int $sourceHeaderRow = 0;

    private int $warehouseTitleRow = 0;

    private int $warehouseHeaderRow = 0;

    private int $lastRow = 0;

    public function __construct(
        private readonly Collection $mutations,
        private readonly array $filters = [],
        private readonly ?string $generatedBy = null,
    ) {}

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        $totalIn = (int) $this->mutations->where('direction', 'in')->sum('qty');
        $totalOut = (int) $this->mutations->where('direction', 'out')->sum('qty');
        $warehouseName = $this->warehouseFilterLabel();

        $rows = [
            ['LAPORAN MUTASI STOK', '', '', '', '', ''],
            [config('app.name'), '', '', '', '', ''],
            ['', '', '', '', '', ''],
            ['INFORMASI LAPORAN', '', '', '', '', ''],
            ['Dibuat pada', now()->format('d/m/Y H:i:s'), '', '', '', ''],
            ['Dibuat oleh', $this->generatedBy ?: '-', '', '', '', ''],
            ['Periode', $this->periodLabel(), '', '', '', ''],
            ['Gudang', $warehouseName, '', '', '', ''],
            ['Pencarian', trim((string) ($this->filters['q'] ?? '')) ?: 'Semua data', '', '', '', ''],
            ['', '', '', '', '', ''],
            ['RINGKASAN UTAMA', '', '', '', '', ''],
            ['Jumlah Mutasi', 'Jumlah SKU', 'Jumlah Gudang', 'Total Masuk', 'Total Keluar', 'Mutasi Bersih'],
            [
                $this->mutations->count(),
                $this->mutations->pluck('item_id')->filter()->unique()->count(),
                $this->mutations->pluck('warehouse_id')->filter()->unique()->count(),
                $totalIn,
                $totalOut,
                $totalIn - $totalOut,
            ],
            ['', '', '', '', '', ''],
        ];

        $this->sourceTitleRow = count($rows) + 1;
        $rows[] = ['RINGKASAN PER JENIS SUMBER', '', '', '', '', ''];
        $this->sourceHeaderRow = count($rows) + 1;
        $rows[] = ['Jenis Sumber', 'Jumlah Mutasi', 'Jumlah SKU', 'Total Masuk', 'Total Keluar', 'Mutasi Bersih'];

        $sourceGroups = $this->mutations
            ->groupBy(fn ($mutation) => $mutation->source_type ?: '-')
            ->sortKeys();

        foreach ($sourceGroups as $sourceType => $group) {
            $incoming = (int) $group->where('direction', 'in')->sum('qty');
            $outgoing = (int) $group->where('direction', 'out')->sum('qty');
            $rows[] = [
                StockMutationReport::sourceTypeLabel($sourceType),
                $group->count(),
                $group->pluck('item_id')->filter()->unique()->count(),
                $incoming,
                $outgoing,
                $incoming - $outgoing,
            ];
        }

        $rows[] = ['', '', '', '', '', ''];
        $this->warehouseTitleRow = count($rows) + 1;
        $rows[] = ['RINGKASAN PER GUDANG', '', '', '', '', ''];
        $this->warehouseHeaderRow = count($rows) + 1;
        $rows[] = ['Gudang', 'Jumlah Mutasi', 'Jumlah SKU', 'Total Masuk', 'Total Keluar', 'Mutasi Bersih'];

        $warehouseGroups = $this->mutations
            ->groupBy(fn ($mutation) => $mutation->warehouse_id ?: 0)
            ->sortBy(fn (Collection $group) => $group->first()?->warehouse?->name ?? '-');

        foreach ($warehouseGroups as $group) {
            $incoming = (int) $group->where('direction', 'in')->sum('qty');
            $outgoing = (int) $group->where('direction', 'out')->sum('qty');
            $rows[] = [
                $group->first()?->warehouse?->name ?? '-',
                $group->count(),
                $group->pluck('item_id')->filter()->unique()->count(),
                $incoming,
                $outgoing,
                $incoming - $outgoing,
            ];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ([1, 2, 4, 11, $this->sourceTitleRow, $this->warehouseTitleRow] as $row) {
            $sheet->mergeCells("A{$row}:F{$row}");
        }

        $sheet->freezePane('A12');
        $sheet->getColumnDimension('A')->setWidth(31);
        foreach (range('B', 'F') as $column) {
            $sheet->getColumnDimension($column)->setWidth(18);
        }

        $sheet->getStyle('A1:F'.$this->lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B12:F'.$this->lastRow)->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');

        foreach ([12, $this->sourceHeaderRow, $this->warehouseHeaderRow] as $headerRow) {
            $sheet->getStyle("A{$headerRow}:F{$headerRow}")->applyFromArray($this->headerStyle());
            $sheet->getRowDimension($headerRow)->setRowHeight(28);
        }

        foreach ([4, 11, $this->sourceTitleRow, $this->warehouseTitleRow] as $titleRow) {
            $sheet->getStyle("A{$titleRow}:F{$titleRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($titleRow)->setRowHeight(25);
        }

        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->getStyle('A2:F2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '44546A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A5:A9')->getFont()->setBold(true);
        $sheet->getStyle('A12:F13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A13:F13')->getFont()->setBold(true);
        $sheet->getStyle('A13:F13')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAF7');

        $sheet->getStyle('A1:F'.$this->lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);

        return [];
    }

    private function periodLabel(): string
    {
        $from = trim((string) ($this->filters['date_from'] ?? ''));
        $to = trim((string) ($this->filters['date_to'] ?? ''));

        if ($from && $to) {
            return $from.' s.d. '.$to;
        }
        if ($from) {
            return 'Mulai '.$from;
        }
        if ($to) {
            return 'Sampai '.$to;
        }

        return 'Semua periode';
    }

    private function warehouseFilterLabel(): string
    {
        $warehouseId = (int) ($this->filters['warehouse_id'] ?? 0);

        return $warehouseId > 0
            ? (Warehouse::query()->find($warehouseId)?->name ?? 'Gudang #'.$warehouseId)
            : 'Semua gudang';
    }

    private function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B9BD5']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
    }
}
