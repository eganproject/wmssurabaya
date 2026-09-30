<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InboundReturnsItemSheet extends InboundReturnsTableSheet
{
    private ?Collection $rows = null;

    public function __construct(private readonly Collection $itemRows) {}

    public function title(): string
    {
        return 'Analisis SKU';
    }

    public function headings(): array
    {
        return [
            'Peringkat Risiko', 'SKU', 'Nama Item', 'Satuan Dasar', 'Jumlah Transaksi',
            'Qty Menurut Resi', 'Qty Diterima', 'Qty Bagus', 'Qty Rusak', 'Qty Hilang',
            'Tingkat Penerimaan', 'Tingkat Bagus', 'Tingkat Rusak', 'Tingkat Hilang',
            'Kontribusi Masalah', 'Retur Pertama', 'Retur Terakhir',
        ];
    }

    public function collection(): Collection
    {
        if ($this->rows) {
            return $this->rows;
        }

        $grandIssue = (int) $this->itemRows->sum(fn (array $row) => $row['qty_damaged'] + $row['qty_missing']);
        $ranked = $this->itemRows
            ->groupBy('item_id')
            ->map(function (Collection $items) use ($grandIssue) {
                $first = $items->first();
                $expected = (int) $items->sum('qty_expected');
                $received = (int) $items->sum('qty_received');
                $good = (int) $items->sum('qty_good');
                $damaged = (int) $items->sum('qty_damaged');
                $missing = (int) $items->sum('qty_missing');
                $issue = $damaged + $missing;
                $dates = $items->pluck('transacted_at')->filter()->sort()->values();

                return [
                    'sku' => $first['sku'],
                    'name' => $first['item_name'],
                    'unit' => $first['base_unit'],
                    'transactions' => $items->pluck('transaction_id')->unique()->count(),
                    'expected' => $expected,
                    'received' => $received,
                    'good' => $good,
                    'damaged' => $damaged,
                    'missing' => $missing,
                    'receipt_rate' => $expected > 0 ? $received / $expected : 0,
                    'good_rate' => $expected > 0 ? $good / $expected : 0,
                    'damage_rate' => $expected > 0 ? $damaged / $expected : 0,
                    'missing_rate' => $expected > 0 ? $missing / $expected : 0,
                    'issue_share' => $grandIssue > 0 ? $issue / $grandIssue : 0,
                    'issue' => $issue,
                    'first_at' => $dates->first(),
                    'last_at' => $dates->last(),
                ];
            })
            ->sortByDesc('issue')
            ->values();

        return $this->rows = $ranked->map(fn (array $row, int $index) => [
            $index + 1, $row['sku'], $row['name'], $row['unit'], $row['transactions'],
            $row['expected'], $row['received'], $row['good'], $row['damaged'], $row['missing'],
            $row['receipt_rate'], $row['good_rate'], $row['damage_rate'], $row['missing_rate'],
            $row['issue_share'],
            $row['first_at'] ? Date::dateTimeToExcel($row['first_at']) : null,
            $row['last_at'] ? Date::dateTimeToExcel($row['last_at']) : null,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'E' => '#,##0', 'F' => '#,##0', 'G' => '#,##0', 'H' => '#,##0',
            'I' => '#,##0', 'J' => '#,##0', 'K' => '0.00%', 'L' => '0.00%',
            'M' => '0.00%', 'N' => '0.00%', 'O' => '0.00%',
            'P' => 'dd/mm/yyyy hh:mm', 'Q' => 'dd/mm/yyyy hh:mm',
        ];
    }

    protected function lastColumn(): string
    {
        return 'Q';
    }

    protected function widths(): array
    {
        return [
            'A' => 18, 'B' => 18, 'C' => 38, 'D' => 16, 'E' => 18,
            'F' => 18, 'G' => 16, 'H' => 15, 'I' => 15, 'J' => 15,
            'K' => 19, 'L' => 17, 'M' => 17, 'N' => 17, 'O' => 21,
            'P' => 21, 'Q' => 21,
        ];
    }

    protected function freezePane(): string
    {
        return 'E2';
    }

    protected function wrapColumns(): array
    {
        return ['C'];
    }

    protected function highlightRows(Worksheet $sheet, int $lastRow): void
    {
        for ($row = 2; $row <= $lastRow; $row++) {
            $issue = (int) $sheet->getCell("I{$row}")->getValue() + (int) $sheet->getCell("J{$row}")->getValue();
            if ($issue > 0) {
                $sheet->getStyle("I{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');
                $sheet->getStyle("I{$row}:J{$row}")->getFont()->setBold(true)->getColor()->setRGB('C00000');
            }
        }
    }
}
