<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InboundReturnsFollowUpSheet extends InboundReturnsTableSheet
{
    private ?Collection $rows = null;

    public function __construct(
        private readonly Collection $transactions,
        private readonly Collection $itemRows,
    ) {}

    public function title(): string
    {
        return 'Tindak Lanjut';
    }

    public function headings(): array
    {
        return [
            'Prioritas', 'Kode Retur', 'Tanggal Transaksi', 'Status', 'No. Resi / Ref',
            'Gudang', 'SKU Unik', 'Qty Menurut Resi', 'Qty Diterima', 'Qty Bagus',
            'Qty Rusak', 'Qty Hilang', 'Tingkat Penerimaan', 'Total Masalah',
            'Waktu Disetujui', 'Waktu Finalisasi', 'Durasi di Area Retur (Jam)',
            'Submit Oleh', 'Finalisasi Oleh', 'Catatan',
        ];
    }

    public function collection(): Collection
    {
        if ($this->rows) {
            return $this->rows;
        }

        $ranked = $this->transactions->map(function ($transaction) {
            $items = $this->itemRows->where('transaction_id', (int) $transaction->id);
            $expected = (int) $items->sum('qty_expected');
            $received = (int) $items->sum('qty_received');
            $status = (string) ($transaction->status ?? 'pending');
            $start = $transaction->approved_at ?? $transaction->created_at;
            $end = $transaction->finalized_at ?? now();
            $hours = $start ? max(0, $start->diffInMinutes($end) / 60) : 0;
            $priority = $this->priority($status, $hours);
            $priorityScore = match ($priority) {
                'Mendesak' => 4,
                'Perhatian' => 3,
                'Menunggu Persetujuan' => 2,
                default => 1,
            };

            return [
                'sort' => $priorityScore * 1000000 + (int) round($hours),
                'row' => [
                    $priority,
                    (string) $transaction->code,
                    $transaction->transacted_at ? Date::dateTimeToExcel($transaction->transacted_at) : null,
                    $this->statusLabel($status),
                    (string) ($transaction->ref_no ?: '-'),
                    (string) ($transaction->warehouse?->name ?? '-'),
                    $items->pluck('item_id')->filter()->unique()->count(),
                    $expected,
                    $received,
                    (int) $items->sum('qty_good'),
                    (int) $items->sum('qty_damaged'),
                    (int) $items->sum('qty_missing'),
                    $expected > 0 ? $received / $expected : 0,
                    (int) $items->sum(fn (array $row) => $row['qty_damaged'] + $row['qty_missing']),
                    $transaction->approved_at ? Date::dateTimeToExcel($transaction->approved_at) : null,
                    $transaction->finalized_at ? Date::dateTimeToExcel($transaction->finalized_at) : null,
                    $hours,
                    (string) ($transaction->creator?->name ?? '-'),
                    (string) ($transaction->finalizer?->name ?? '-'),
                    (string) ($transaction->note ?: '-'),
                ],
            ];
        })->sortByDesc('sort')->values();

        return $this->rows = $ranked->pluck('row');
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT, 'C' => 'dd/mm/yyyy hh:mm',
            'E' => NumberFormat::FORMAT_TEXT, 'G' => '#,##0', 'H' => '#,##0',
            'I' => '#,##0', 'J' => '#,##0', 'K' => '#,##0', 'L' => '#,##0',
            'M' => '0.00%', 'N' => '#,##0', 'O' => 'dd/mm/yyyy hh:mm',
            'P' => 'dd/mm/yyyy hh:mm', 'Q' => '#,##0.00',
        ];
    }

    protected function lastColumn(): string
    {
        return 'T';
    }

    protected function widths(): array
    {
        return [
            'A' => 22, 'B' => 26, 'C' => 21, 'D' => 22, 'E' => 23,
            'F' => 27, 'G' => 14, 'H' => 19, 'I' => 16, 'J' => 15,
            'K' => 15, 'L' => 15, 'M' => 19, 'N' => 16, 'O' => 21,
            'P' => 21, 'Q' => 26, 'R' => 22, 'S' => 22, 'T' => 42,
        ];
    }

    protected function freezePane(): string
    {
        return 'G2';
    }

    protected function wrapColumns(): array
    {
        return ['T'];
    }

    protected function highlightRows(Worksheet $sheet, int $lastRow): void
    {
        $colors = [
            'Mendesak' => 'F4CCCC',
            'Perhatian' => 'FCE5CD',
            'Menunggu Persetujuan' => 'FFF2CC',
            'Selesai' => 'E2F0D9',
        ];

        for ($row = 2; $row <= $lastRow; $row++) {
            $priority = (string) $sheet->getCell("A{$row}")->getValue();
            $color = $colors[$priority] ?? 'FFFFFF';
            $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            if ((int) $sheet->getCell("N{$row}")->getValue() > 0) {
                $sheet->getStyle("N{$row}")->getFont()->setBold(true)->getColor()->setRGB('C00000');
            }
        }
    }

    private function priority(string $status, float $hours): string
    {
        if ($status === 'finalized') {
            return 'Selesai';
        }
        if ($status === 'pending') {
            return 'Menunggu Persetujuan';
        }
        if ($hours >= 48) {
            return 'Mendesak';
        }

        return $hours >= 24 ? 'Perhatian' : 'Normal';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'finalized' => 'Finalisasi',
            'approved' => 'Area Retur / Belum Finalisasi',
            default => 'Menunggu',
        };
    }
}
