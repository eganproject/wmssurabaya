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

class InboundReceiptsDailySheet implements FromCollection, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    private ?Collection $rows = null;

    public function __construct(
        private readonly Collection $transactions,
        private readonly Collection $itemRows,
    ) {}

    public function title(): string
    {
        return 'Tren Harian';
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Jumlah Transaksi',
            'Disetujui',
            'Menunggu',
            'Approval Rate',
            'SKU Unik',
            'Jumlah Gudang',
            'Baris Item',
            'Qty Disetujui',
            'Qty Menunggu',
            'Total Qty Dasar',
            'Rata-rata Qty / Transaksi',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows ??= $this->transactions
            ->groupBy(fn ($transaction) => $transaction->transacted_at?->format('Y-m-d') ?? '-')
            ->map(function (Collection $transactions, string $dateKey) {
                $transactionIds = $transactions->pluck('id')->map(fn ($id) => (int) $id);
                $items = $this->itemRows->whereIn('transaction_id', $transactionIds);
                $approvedCount = $transactions->where('status', 'approved')->count();
                $transactionCount = $transactions->count();
                $totalQty = (int) $items->sum('qty_received');

                return [
                    $dateKey !== '-' ? Date::dateTimeToExcel($transactions->first()->transacted_at->copy()->startOfDay()) : null,
                    $transactionCount,
                    $approvedCount,
                    $transactions->filter(fn ($transaction) => ($transaction->status ?? 'pending') === 'pending')->count(),
                    $transactionCount > 0 ? $approvedCount / $transactionCount : 0,
                    $items->pluck('item_id')->filter()->unique()->count(),
                    $transactions->pluck('warehouse_id')->filter()->unique()->count(),
                    $items->count(),
                    (int) $items->where('status', 'approved')->sum('qty_received'),
                    (int) $items->where('status', 'pending')->sum('qty_received'),
                    $totalQty,
                    $transactionCount > 0 ? $totalQty / $transactionCount : 0,
                ];
            })
            ->sortByDesc(fn (array $row) => $row[0] ?? 0)
            ->values();
    }

    public function columnFormats(): array
    {
        return [
            'A' => 'dd/mm/yyyy',
            'B' => '#,##0',
            'C' => '#,##0',
            'D' => '#,##0',
            'E' => '0.00%',
            'F' => '#,##0',
            'G' => '#,##0',
            'H' => '#,##0',
            'I' => '#,##0',
            'J' => '#,##0',
            'K' => '#,##0',
            'L' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->collection()->count() + 1);
        $sheet->freezePane('B2');
        $sheet->setAutoFilter("A1:L{$lastRow}");

        $sheet->getColumnDimension('A')->setWidth(16);
        foreach (range('B', 'L') as $column) {
            $sheet->getColumnDimension($column)->setWidth($column === 'L' ? 24 : 18);
        }

        $sheet->getStyle("A1:L{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A1:L{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:L1')->applyFromArray($this->headerStyle());
        $sheet->getRowDimension(1)->setRowHeight(42);

        for ($row = 2; $row <= $lastRow; $row++) {
            if ((int) $sheet->getCell("D{$row}")->getValue() > 0) {
                $sheet->getStyle("D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
                $sheet->getStyle("J{$row}")->getFont()->setBold(true)->getColor()->setRGB('C65911');
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
