<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use Illuminate\Support\Carbon;
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

class InboundReturnsSummarySheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    private array $sectionRows = [];

    private array $headerRows = [];

    private int $lastRow = 1;

    public function __construct(
        private readonly Collection $transactions,
        private readonly Collection $itemRows,
        private readonly array $filters = [],
        private readonly ?string $generatedBy = null,
    ) {}

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        $transactionCount = $this->transactions->count();
        $finalized = $this->transactions->where('status', 'finalized');
        $approved = $this->transactions->where('status', 'approved');
        $pending = $this->transactions->filter(fn ($transaction) => ($transaction->status ?? 'pending') === 'pending');
        $expected = (int) $this->itemRows->sum('qty_expected');
        $received = (int) $this->itemRows->sum('qty_received');
        $good = (int) $this->itemRows->sum('qty_good');
        $damaged = (int) $this->itemRows->sum('qty_damaged');
        $missing = (int) $this->itemRows->sum('qty_missing');
        $holdingHours = $finalized
            ->filter(fn ($transaction) => $transaction->approved_at && $transaction->finalized_at)
            ->map(fn ($transaction) => $transaction->approved_at->diffInMinutes($transaction->finalized_at) / 60);

        $rows = [
            ['LAPORAN ANALITIK RETUR INBOUND', '', '', '', '', '', '', '', ''],
            [config('app.name'), '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['INFORMASI LAPORAN', '', '', '', '', '', '', '', ''],
            ['Dibuat pada', now()->format('d/m/Y H:i:s'), '', '', '', '', '', '', ''],
            ['Dibuat oleh', $this->generatedBy ?: '-', '', '', '', '', '', '', ''],
            ['Periode transaksi', $this->periodLabel(), '', '', '', '', '', '', ''],
            ['Status', $this->statusFilterLabel(), '', '', '', '', '', '', ''],
            ['Pencarian', trim((string) ($this->filters['q'] ?? '')) ?: 'Semua data', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['RINGKASAN UTAMA', '', '', '', '', '', '', '', ''],
            ['Transaksi', 'Finalisasi', 'Area Retur', 'Menunggu', 'Finalization Rate', 'SKU Unik', 'Baris Item', 'Tanpa No. Resi', 'Total Masalah'],
            [
                $transactionCount,
                $finalized->count(),
                $approved->count(),
                $pending->count(),
                $transactionCount > 0 ? $finalized->count() / $transactionCount : 0,
                $this->itemRows->pluck('item_id')->filter()->unique()->count(),
                $this->itemRows->count(),
                $this->transactions->filter(fn ($transaction) => trim((string) $transaction->ref_no) === '')->count(),
                $damaged + $missing,
            ],
            ['', '', '', '', '', '', '', '', ''],
            ['ANALISIS KUALITAS', '', '', '', '', '', '', '', ''],
            ['Qty Menurut Resi', 'Qty Diterima', 'Qty Bagus', 'Qty Rusak', 'Qty Hilang', 'Tingkat Penerimaan', 'Tingkat Bagus', 'Tingkat Rusak', 'Tingkat Hilang'],
            [
                $expected,
                $received,
                $good,
                $damaged,
                $missing,
                $expected > 0 ? $received / $expected : 0,
                $expected > 0 ? $good / $expected : 0,
                $expected > 0 ? $damaged / $expected : 0,
                $expected > 0 ? $missing / $expected : 0,
            ],
            ['', '', '', '', '', '', '', '', ''],
            ['INDIKATOR OPERASIONAL', '', '', '', '', '', '', '', ''],
            ['Indikator', 'Nilai', 'Interpretasi', '', '', '', '', '', ''],
            ['Rata-rata qty / transaksi', $transactionCount > 0 ? $expected / $transactionCount : 0, 'Rata-rata unit menurut resi pada setiap dokumen retur.', '', '', '', '', '', ''],
            ['Rata-rata waktu di Area Retur (jam)', $holdingHours->isNotEmpty() ? $holdingHours->average() : 0, 'Dihitung dari persetujuan sampai finalisasi.', '', '', '', '', '', ''],
            ['Dokumen belum finalisasi', $approved->count() + $pending->count(), 'Dokumen yang masih membutuhkan tindakan operasional.', '', '', '', '', '', ''],
            ['SKU memiliki rusak / hilang', $this->itemRows->filter(fn (array $row) => $row['qty_damaged'] > 0 || $row['qty_missing'] > 0)->pluck('item_id')->unique()->count(), 'SKU yang perlu dianalisis penyebab masalahnya.', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
        ];

        $this->sectionRows = [1, 2, 4, 11, 15, 19];
        $this->headerRows = [12, 16, 20];

        $this->sectionRows[] = count($rows) + 1;
        $rows[] = ['RINGKASAN PER STATUS', '', '', '', '', '', '', '', ''];
        $this->headerRows[] = count($rows) + 1;
        $rows[] = ['Status', 'Transaksi', '% Transaksi', 'SKU Unik', 'Qty Menurut Resi', 'Qty Diterima', 'Qty Bagus', 'Qty Rusak', 'Qty Hilang'];

        foreach (['finalized' => 'Finalisasi', 'approved' => 'Area Retur / Belum Finalisasi', 'pending' => 'Menunggu'] as $status => $label) {
            $transactions = $this->transactions->filter(fn ($transaction) => ($transaction->status ?? 'pending') === $status);
            $items = $this->itemRows->where('status', $status);
            $rows[] = [
                $label,
                $transactions->count(),
                $transactionCount > 0 ? $transactions->count() / $transactionCount : 0,
                $items->pluck('item_id')->filter()->unique()->count(),
                (int) $items->sum('qty_expected'),
                (int) $items->sum('qty_received'),
                (int) $items->sum('qty_good'),
                (int) $items->sum('qty_damaged'),
                (int) $items->sum('qty_missing'),
            ];
        }

        $rows[] = ['', '', '', '', '', '', '', '', ''];
        $this->sectionRows[] = count($rows) + 1;
        $rows[] = ['TOP 10 SKU BERMASALAH', '', '', '', '', '', '', '', ''];
        $this->headerRows[] = count($rows) + 1;
        $rows[] = ['Peringkat', 'SKU', 'Nama Item', 'Transaksi', 'Qty Menurut Resi', 'Qty Rusak', 'Qty Hilang', 'Total Masalah', '% dari Masalah'];

        $grandIssue = $damaged + $missing;
        $topItems = $this->itemRows
            ->groupBy('item_id')
            ->map(function (Collection $items) {
                $first = $items->first();
                $damage = (int) $items->sum('qty_damaged');
                $missing = (int) $items->sum('qty_missing');

                return [
                    'sku' => $first['sku'],
                    'name' => $first['item_name'],
                    'transactions' => $items->pluck('transaction_id')->unique()->count(),
                    'expected' => (int) $items->sum('qty_expected'),
                    'damage' => $damage,
                    'missing' => $missing,
                    'issue' => $damage + $missing,
                ];
            })
            ->sortByDesc('issue')
            ->take(10)
            ->values();

        foreach ($topItems as $index => $item) {
            $rows[] = [
                $index + 1,
                $item['sku'],
                $item['name'],
                $item['transactions'],
                $item['expected'],
                $item['damage'],
                $item['missing'],
                $item['issue'],
                $grandIssue > 0 ? $item['issue'] / $grandIssue : 0,
            ];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ($this->sectionRows as $row) {
            $sheet->mergeCells("A{$row}:I{$row}");
            $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($row <= 2 ? '17365D' : '1F4E78');
        }

        foreach ($this->headerRows as $row) {
            $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B9BD5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
        }

        $sheet->freezePane('A11');
        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(27);
        foreach (range('C', 'I') as $column) {
            $sheet->getColumnDimension($column)->setWidth($column === 'C' ? 46 : 20);
        }

        $sheet->getStyle("A1:I{$this->lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A1:I{$this->lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C1:C{$this->lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle('E13')->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle('F17:I17')->getNumberFormat()->setFormatCode('0.00%');

        $sheet->getStyle('C28:C30')->getNumberFormat()->setFormatCode('0.00%');
        if ($this->lastRow >= 34) {
            $sheet->getStyle("I34:I{$this->lastRow}")->getNumberFormat()->setFormatCode('0.00%');
        }

        $sheet->getStyle("A1:I{$this->lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1')->getFont()->setSize(16);
        $sheet->getStyle('A2')->getFont()->setSize(11);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);

        return [];
    }

    private function periodLabel(): string
    {
        $from = trim((string) ($this->filters['date_from'] ?? ''));
        $to = trim((string) ($this->filters['date_to'] ?? ''));

        if ($from === '' && $to === '') {
            return 'Semua tanggal';
        }

        $fromLabel = $from !== '' ? Carbon::parse($from)->format('d/m/Y') : 'awal';
        $toLabel = $to !== '' ? Carbon::parse($to)->format('d/m/Y') : 'sekarang';

        return "{$fromLabel} s.d. {$toLabel}";
    }

    private function statusFilterLabel(): string
    {
        return match ($this->filters['status'] ?? '') {
            'finalized' => 'Finalisasi',
            'approved' => 'Area Retur / Belum Finalisasi',
            'pending' => 'Menunggu',
            default => 'Semua status',
        };
    }
}
