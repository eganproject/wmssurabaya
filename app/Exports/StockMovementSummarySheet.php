<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMovementSummarySheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    private Collection $groupedRows;

    public function __construct(
        private readonly Collection $rows,
        private readonly string $groupKey,
        private readonly string $sheetTitle,
    ) {
        $this->groupedRows = $this->buildRows();
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        return [
            ['Kelompok', 'Jumlah SKU', 'Fast', 'Medium', 'Slow', 'Non-moving', 'Qty Keluar', 'Stok Saat Ini', 'Stok Non-moving', 'Di Bawah Safety', 'Cover <= 7 Hari'],
            ...$this->groupedRows->all(),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->groupedRows->count() + 1);
        $sheet->freezePane('B2');
        $sheet->setAutoFilter("A1:K{$lastRow}");
        $sheet->getColumnDimension('A')->setWidth(32);
        foreach (range('B', 'K') as $column) {
            $sheet->getColumnDimension($column)->setWidth(17);
        }
        if ($lastRow >= 2) {
            $sheet->getStyle("B2:K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
        }
        $sheet->getStyle("A1:K{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);

        return [];
    }

    private function buildRows(): Collection
    {
        return $this->rows
            ->groupBy(fn (array $row) => $row[$this->groupKey] ?: '-')
            ->map(function (Collection $group, string $label) {
                return [
                    $label,
                    $group->count(),
                    $group->where('movement_key', 'fast')->count(),
                    $group->where('movement_key', 'medium')->count(),
                    $group->where('movement_key', 'slow')->count(),
                    $group->where('movement_key', 'non_moving')->count(),
                    (int) $group->sum('outbound_qty'),
                    (int) $group->sum('stock'),
                    (int) $group->where('movement_key', 'non_moving')->sum('stock'),
                    $group->filter(fn (array $row) => $row['safety_stock'] > 0 && $row['stock'] <= $row['safety_stock'])->count(),
                    $group->filter(fn (array $row) => $row['days_cover'] !== null && $row['days_cover'] <= 7)->count(),
                ];
            })
            ->sortByDesc(fn (array $row) => $row[6])
            ->values();
    }
}
