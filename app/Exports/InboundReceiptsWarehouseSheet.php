<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InboundReceiptsWarehouseSheet implements FromCollection, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    private ?Collection $rows = null;

    public function __construct(
        private readonly Collection $transactions,
        private readonly Collection $itemRows,
    ) {}

    public function title(): string
    {
        return 'Rekap Gudang';
    }

    public function headings(): array
    {
        return [
            'Gudang',
            'Tipe Gudang',
            'Jumlah Transaksi',
            'Disetujui',
            'Menunggu',
            'Approval Rate',
            'SKU Unik',
            'Baris Item',
            'Qty Disetujui',
            'Qty Menunggu',
            'Total Qty Dasar',
            'Rata-rata Qty / Transaksi',
            'Penerimaan Pertama',
            'Penerimaan Terakhir',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows ??= $this->transactions
            ->groupBy(fn ($transaction) => $transaction->warehouse_id ?: 0)
            ->map(function (Collection $transactions) {
                $first = $transactions->first();
                $warehouseId = (int) ($first?->warehouse_id ?? 0);
                $items = $this->itemRows->where('warehouse_id', $warehouseId);
                $approvedCount = $transactions->where('status', 'approved')->count();
                $transactionCount = $transactions->count();
                $orderedDates = $transactions->pluck('transacted_at')->filter()->sort()->values();
                $totalQty = (int) $items->sum('qty_received');

                return [
                    $first?->warehouse?->name ?? '-',
                    $this->warehouseTypeLabel($first?->warehouse?->type),
                    $transactionCount,
                    $approvedCount,
                    $transactions->filter(fn ($transaction) => ($transaction->status ?? 'pending') === 'pending')->count(),
                    $transactionCount > 0 ? $approvedCount / $transactionCount : 0,
                    $items->pluck('item_id')->filter()->unique()->count(),
                    $items->count(),
                    (int) $items->where('status', 'approved')->sum('qty_received'),
                    (int) $items->where('status', 'pending')->sum('qty_received'),
                    $totalQty,
                    $transactionCount > 0 ? $totalQty / $transactionCount : 0,
                    $orderedDates->first() ? Date::dateTimeToExcel($orderedDates->first()) : null,
                    $orderedDates->last() ? Date::dateTimeToExcel($orderedDates->last()) : null,
                ];
            })
            ->sortByDesc(fn (array $row) => $row[10])
            ->values();
    }

    public function columnFormats(): array
    {
        return [
            'C' => '#,##0',
            'D' => '#,##0',
            'E' => '#,##0',
            'F' => '0.00%',
            'G' => '#,##0',
            'H' => '#,##0',
            'I' => '#,##0',
            'J' => '#,##0',
            'K' => '#,##0',
            'L' => '#,##0.00',
            'M' => 'dd/mm/yyyy hh:mm',
            'N' => 'dd/mm/yyyy hh:mm',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->collection()->count() + 1);
        $sheet->freezePane('C2');
        $sheet->setAutoFilter("A1:N{$lastRow}");

        $widths = [
            'A' => 30, 'B' => 18, 'C' => 18, 'D' => 15, 'E' => 15, 'F' => 16, 'G' => 14,
            'H' => 14, 'I' => 18, 'J' => 18, 'K' => 18, 'L' => 23, 'M' => 21, 'N' => 21,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle("A1:N{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A1:N{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:N1')->applyFromArray($this->headerStyle());
        $sheet->getRowDimension(1)->setRowHeight(42);

        for ($row = 2; $row <= $lastRow; $row++) {
            if ((int) $sheet->getCell("E{$row}")->getValue() > 0) {
                $sheet->getStyle("E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
                $sheet->getStyle("J{$row}")->getFont()->setBold(true)->getColor()->setRGB('C65911');
            }
        }

        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.4)->setLeft(0.3);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

        return [];
    }

    private function warehouseTypeLabel(?string $type): string
    {
        return match ($type) {
            'bulk' => 'Gudang Besar',
            'small' => 'Gudang Kecil',
            default => $type ?: '-',
        };
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
