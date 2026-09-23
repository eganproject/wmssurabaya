<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InboundReceiptsItemSheet extends DefaultValueBinder implements FromCollection, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    private ?Collection $rows = null;

    public function __construct(private readonly Collection $itemRows) {}

    public function title(): string
    {
        return 'Rekap SKU';
    }

    public function headings(): array
    {
        return [
            'Peringkat',
            'Gudang',
            'SKU',
            'Nama Item',
            'Satuan Dasar',
            'Jumlah Transaksi',
            'Baris Item',
            'Qty Disetujui',
            'Qty Menunggu',
            'Total Qty Dasar',
            'Kontribusi Volume',
            'Rata-rata Qty / Transaksi',
            'Penerimaan Pertama',
            'Penerimaan Terakhir',
        ];
    }

    public function collection(): Collection
    {
        if ($this->rows) {
            return $this->rows;
        }

        $grandTotal = (int) $this->itemRows->sum('qty_received');
        $ranked = $this->itemRows
            ->groupBy(fn (array $row) => $row['warehouse_id'].'|'.$row['item_id'])
            ->map(function (Collection $items) use ($grandTotal) {
                $first = $items->first();
                $transactionCount = $items->pluck('transaction_id')->unique()->count();
                $totalQty = (int) $items->sum('qty_received');
                $dates = $items->pluck('transacted_at')->filter()->sort()->values();

                return [
                    'warehouse' => $first['warehouse'],
                    'sku' => $first['sku'],
                    'name' => $first['item_name'],
                    'unit' => $first['base_unit'],
                    'transactions' => $transactionCount,
                    'lines' => $items->count(),
                    'approved_qty' => (int) $items->where('status', 'approved')->sum('qty_received'),
                    'pending_qty' => (int) $items->where('status', 'pending')->sum('qty_received'),
                    'total_qty' => $totalQty,
                    'contribution' => $grandTotal > 0 ? $totalQty / $grandTotal : 0,
                    'average' => $transactionCount > 0 ? $totalQty / $transactionCount : 0,
                    'first_at' => $dates->first(),
                    'last_at' => $dates->last(),
                ];
            })
            ->sortByDesc('total_qty')
            ->values();

        return $this->rows = $ranked->map(fn (array $row, int $index) => [
            $index + 1,
            $row['warehouse'],
            $row['sku'],
            $row['name'],
            $row['unit'],
            $row['transactions'],
            $row['lines'],
            $row['approved_qty'],
            $row['pending_qty'],
            $row['total_qty'],
            $row['contribution'],
            $row['average'],
            $row['first_at'] ? Date::dateTimeToExcel($row['first_at']) : null,
            $row['last_at'] ? Date::dateTimeToExcel($row['last_at']) : null,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            'F' => '#,##0',
            'G' => '#,##0',
            'H' => '#,##0',
            'I' => '#,##0',
            'J' => '#,##0',
            'K' => '0.00%',
            'L' => '#,##0.00',
            'M' => 'dd/mm/yyyy hh:mm',
            'N' => 'dd/mm/yyyy hh:mm',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->collection()->count() + 1);
        $sheet->freezePane('F2');
        $sheet->setAutoFilter("A1:N{$lastRow}");

        $widths = [
            'A' => 12, 'B' => 28, 'C' => 18, 'D' => 38, 'E' => 17, 'F' => 18, 'G' => 14,
            'H' => 18, 'I' => 18, 'J' => 18, 'K' => 20, 'L' => 23, 'M' => 21, 'N' => 21,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle("A1:N{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A1:N{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1:N1')->applyFromArray($this->headerStyle());
        $sheet->getRowDimension(1)->setRowHeight(42);

        for ($row = 2; $row <= $lastRow; $row++) {
            if ((int) $sheet->getCell("I{$row}")->getValue() > 0) {
                $sheet->getStyle("I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
                $sheet->getStyle("I{$row}")->getFont()->setBold(true)->getColor()->setRGB('C65911');
            }
        }

        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.4)->setLeft(0.3);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

        return [];
    }

    private function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
    }
}
