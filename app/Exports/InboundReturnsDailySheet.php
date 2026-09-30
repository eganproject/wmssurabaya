<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InboundReturnsDailySheet extends InboundReturnsTableSheet
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
            'Tanggal', 'Transaksi', 'Finalisasi', 'Belum Finalisasi', 'Finalization Rate',
            'SKU Unik', 'Qty Menurut Resi', 'Qty Diterima', 'Qty Bagus', 'Qty Rusak',
            'Qty Hilang', 'Tingkat Penerimaan', 'Tingkat Bagus', 'Tingkat Rusak', 'Tingkat Hilang',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows ??= $this->transactions
            ->groupBy(fn ($transaction) => $transaction->transacted_at?->format('Y-m-d') ?? '-')
            ->map(function (Collection $transactions, string $dateKey) {
                $ids = $transactions->pluck('id')->map(fn ($id) => (int) $id);
                $items = $this->itemRows->whereIn('transaction_id', $ids);
                $expected = (int) $items->sum('qty_expected');
                $finalized = $transactions->where('status', 'finalized')->count();
                $total = $transactions->count();

                return [
                    $dateKey !== '-' ? Date::dateTimeToExcel($transactions->first()->transacted_at->copy()->startOfDay()) : null,
                    $total,
                    $finalized,
                    $total - $finalized,
                    $total > 0 ? $finalized / $total : 0,
                    $items->pluck('item_id')->filter()->unique()->count(),
                    $expected,
                    (int) $items->sum('qty_received'),
                    (int) $items->sum('qty_good'),
                    (int) $items->sum('qty_damaged'),
                    (int) $items->sum('qty_missing'),
                    $expected > 0 ? $items->sum('qty_received') / $expected : 0,
                    $expected > 0 ? $items->sum('qty_good') / $expected : 0,
                    $expected > 0 ? $items->sum('qty_damaged') / $expected : 0,
                    $expected > 0 ? $items->sum('qty_missing') / $expected : 0,
                ];
            })
            ->sortByDesc(fn (array $row) => $row[0] ?? 0)
            ->values();
    }

    public function columnFormats(): array
    {
        return [
            'A' => 'dd/mm/yyyy', 'B' => '#,##0', 'C' => '#,##0', 'D' => '#,##0',
            'E' => '0.00%', 'F' => '#,##0', 'G' => '#,##0', 'H' => '#,##0',
            'I' => '#,##0', 'J' => '#,##0', 'K' => '#,##0', 'L' => '0.00%',
            'M' => '0.00%', 'N' => '0.00%', 'O' => '0.00%',
        ];
    }

    protected function lastColumn(): string
    {
        return 'O';
    }

    protected function widths(): array
    {
        return [
            'A' => 16, 'B' => 14, 'C' => 14, 'D' => 20, 'E' => 19,
            'F' => 14, 'G' => 19, 'H' => 16, 'I' => 15, 'J' => 15,
            'K' => 15, 'L' => 19, 'M' => 17, 'N' => 17, 'O' => 17,
        ];
    }

    protected function freezePane(): string
    {
        return 'B2';
    }

    protected function highlightRows(Worksheet $sheet, int $lastRow): void
    {
        for ($row = 2; $row <= $lastRow; $row++) {
            if ((int) $sheet->getCell("D{$row}")->getValue() > 0) {
                $sheet->getStyle("D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
            }
            if ((int) $sheet->getCell("J{$row}")->getValue() + (int) $sheet->getCell("K{$row}")->getValue() > 0) {
                $sheet->getStyle("J{$row}:K{$row}")->getFont()->setBold(true)->getColor()->setRGB('C00000');
            }
        }
    }
}
