<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Fast / medium / slow moving per toko. Each toko is classified against its own
 * qty keluar from completed resi (manual outbound has no toko).
 */
class StockMovementStoreSheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    private Collection $lines;

    public function __construct(private readonly Collection $stores)
    {
        $this->lines = $this->stores->flatMap(function (array $store) {
            return collect($store['items'] ?? [])->map(fn (array $item) => [
                $store['store_name'],
                $item['sku'],
                $item['name'],
                $item['movement_label'],
                $item['outbound_qty'],
                $item['average_daily_outbound'],
                $item['contribution_percent'] / 100,
                $store['total_outbound_qty'],
            ]);
        })->values();
    }

    public function title(): string
    {
        return 'Per Toko';
    }

    public function array(): array
    {
        return [
            ['Toko', 'SKU', 'Nama Item', 'Klasifikasi di Toko', 'Qty Keluar', 'Rata-rata / Hari', 'Kontribusi di Toko', 'Total Qty Keluar Toko'],
            ...$this->lines->all(),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->lines->count() + 1);
        $sheet->freezePane('C2');
        $sheet->setAutoFilter("A1:H{$lastRow}");
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(40);
        foreach (range('D', 'H') as $column) {
            $sheet->getColumnDimension($column)->setWidth(18);
        }
        if ($lastRow >= 2) {
            $sheet->getStyle("E2:E{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("F2:F{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("G2:G{$lastRow}")->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyle("H2:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        }
        $sheet->getStyle("A1:H{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        return [];
    }
}
