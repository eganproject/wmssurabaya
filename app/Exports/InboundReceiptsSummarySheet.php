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

class InboundReceiptsSummarySheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    private int $statusTitleRow = 0;

    private int $statusHeaderRow = 0;

    private int $warehouseTitleRow = 0;

    private int $warehouseHeaderRow = 0;

    private int $topItemTitleRow = 0;

    private int $topItemHeaderRow = 0;

    private int $lastRow = 0;

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
        $approved = $this->transactions->where('status', 'approved');
        $pending = $this->transactions->filter(fn ($transaction) => ($transaction->status ?? 'pending') === 'pending');
        $totalQty = (int) $this->itemRows->sum('qty_received');
        $approvedQty = (int) $this->itemRows->where('status', 'approved')->sum('qty_received');
        $pendingQty = (int) $this->itemRows->where('status', 'pending')->sum('qty_received');
        $approvalHours = $approved
            ->filter(fn ($transaction) => $transaction->created_at && $transaction->approved_at)
            ->map(fn ($transaction) => $transaction->created_at->diffInMinutes($transaction->approved_at) / 60);

        $rows = [
            ['LAPORAN PENERIMAAN BARANG', '', '', '', '', '', '', ''],
            [config('app.name'), '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', ''],
            ['INFORMASI LAPORAN', '', '', '', '', '', '', ''],
            ['Dibuat pada', now()->format('d/m/Y H:i:s'), '', '', '', '', '', ''],
            ['Dibuat oleh', $this->generatedBy ?: '-', '', '', '', '', '', ''],
            ['Periode transaksi', $this->periodLabel(), '', '', '', '', '', ''],
            ['Status', $this->statusFilterLabel(), '', '', '', '', '', ''],
            ['Pencarian', trim((string) ($this->filters['q'] ?? '')) ?: 'Semua data', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', ''],
            ['RINGKASAN UTAMA', '', '', '', '', '', '', ''],
            ['Transaksi', 'Disetujui', 'Menunggu', 'Approval Rate', 'SKU Unik', 'Gudang', 'Baris Item', 'Total Qty Dasar'],
            [
                $transactionCount,
                $approved->count(),
                $pending->count(),
                $transactionCount > 0 ? $approved->count() / $transactionCount : 0,
                $this->itemRows->pluck('item_id')->filter()->unique()->count(),
                $this->transactions->pluck('warehouse_id')->filter()->unique()->count(),
                $this->itemRows->count(),
                $totalQty,
            ],
            ['', '', '', '', '', '', '', ''],
            ['INDIKATOR ANALISIS', '', '', '', '', '', '', ''],
            ['Indikator', 'Nilai', 'Interpretasi', '', '', '', '', ''],
            ['Qty telah disetujui', $approvedQty, 'Qty yang sudah menambah stok melalui proses persetujuan.', '', '', '', '', ''],
            ['Qty masih menunggu', $pendingQty, 'Qty yang belum menambah stok dan perlu ditindaklanjuti.', '', '', '', '', ''],
            ['Rata-rata qty / transaksi', $transactionCount > 0 ? $totalQty / $transactionCount : 0, 'Ukuran rata-rata volume setiap dokumen penerimaan.', '', '', '', '', ''],
            ['Rata-rata waktu persetujuan (jam)', $approvalHours->isNotEmpty() ? $approvalHours->average() : 0, 'Dihitung dari waktu pencatatan sampai waktu persetujuan.', '', '', '', '', ''],
            ['Dokumen tanpa Ref No', $this->transactions->filter(fn ($transaction) => trim((string) $transaction->ref_no) === '')->count(), 'Lengkapi referensi agar penelusuran dokumen lebih mudah.', '', '', '', '', ''],
            ['', '', '', '', '', '', '', ''],
        ];

        $this->statusTitleRow = count($rows) + 1;
        $rows[] = ['RINGKASAN PER STATUS', '', '', '', '', '', '', ''];
        $this->statusHeaderRow = count($rows) + 1;
        $rows[] = ['Status', 'Transaksi', '% Transaksi', 'SKU Unik', 'Baris Item', 'Total Qty Dasar', 'Rata-rata Qty / Transaksi', ''];

        foreach (['approved' => 'Disetujui', 'pending' => 'Menunggu Persetujuan'] as $status => $label) {
            $transactions = $this->transactions->filter(fn ($transaction) => ($transaction->status ?? 'pending') === $status);
            $items = $this->itemRows->where('status', $status);
            $rows[] = [
                $label,
                $transactions->count(),
                $transactionCount > 0 ? $transactions->count() / $transactionCount : 0,
                $items->pluck('item_id')->filter()->unique()->count(),
                $items->count(),
                (int) $items->sum('qty_received'),
                $transactions->isNotEmpty() ? $items->sum('qty_received') / $transactions->count() : 0,
                '',
            ];
        }

        $rows[] = ['', '', '', '', '', '', '', ''];
        $this->warehouseTitleRow = count($rows) + 1;
        $rows[] = ['RINGKASAN PER GUDANG', '', '', '', '', '', '', ''];
        $this->warehouseHeaderRow = count($rows) + 1;
        $rows[] = ['Gudang', 'Transaksi', 'Disetujui', 'Menunggu', 'SKU Unik', 'Total Qty Dasar', '% Volume', 'Rata-rata Qty / Transaksi'];

        $warehouseGroups = $this->transactions
            ->groupBy(fn ($transaction) => $transaction->warehouse_id ?: 0)
            ->sortByDesc(fn (Collection $group) => $this->itemRows->where('warehouse_id', (int) ($group->first()?->warehouse_id ?? 0))->sum('qty_received'));

        foreach ($warehouseGroups as $transactions) {
            $warehouseId = (int) ($transactions->first()?->warehouse_id ?? 0);
            $items = $this->itemRows->where('warehouse_id', $warehouseId);
            $qty = (int) $items->sum('qty_received');
            $rows[] = [
                $transactions->first()?->warehouse?->name ?? '-',
                $transactions->count(),
                $transactions->where('status', 'approved')->count(),
                $transactions->filter(fn ($transaction) => ($transaction->status ?? 'pending') === 'pending')->count(),
                $items->pluck('item_id')->filter()->unique()->count(),
                $qty,
                $totalQty > 0 ? $qty / $totalQty : 0,
                $transactions->isNotEmpty() ? $qty / $transactions->count() : 0,
            ];
        }

        $rows[] = ['', '', '', '', '', '', '', ''];
        $this->topItemTitleRow = count($rows) + 1;
        $rows[] = ['TOP 10 SKU BERDASARKAN QTY', '', '', '', '', '', '', ''];
        $this->topItemHeaderRow = count($rows) + 1;
        $rows[] = ['Peringkat', 'SKU', 'Nama Item', 'Gudang', 'Transaksi', 'Total Qty Dasar', '% Volume', 'Terakhir Diterima'];

        $topItems = $this->itemRows
            ->groupBy(fn (array $row) => $row['warehouse_id'].'|'.$row['item_id'])
            ->map(function (Collection $items) {
                $first = $items->first();

                return [
                    'sku' => $first['sku'],
                    'name' => $first['item_name'],
                    'warehouse' => $first['warehouse'],
                    'transactions' => $items->pluck('transaction_id')->unique()->count(),
                    'qty' => (int) $items->sum('qty_received'),
                    'last_at' => $items->max('transacted_at'),
                ];
            })
            ->sortByDesc('qty')
            ->take(10)
            ->values();

        foreach ($topItems as $index => $item) {
            $rows[] = [
                $index + 1,
                $item['sku'],
                $item['name'],
                $item['warehouse'],
                $item['transactions'],
                $item['qty'],
                $totalQty > 0 ? $item['qty'] / $totalQty : 0,
                $item['last_at'] ? Carbon::parse($item['last_at'])->format('d/m/Y H:i') : '-',
            ];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ([1, 2, 4, 11, 15, $this->statusTitleRow, $this->warehouseTitleRow, $this->topItemTitleRow] as $row) {
            $sheet->mergeCells("A{$row}:H{$row}");
        }
        foreach (range(17, 21) as $row) {
            $sheet->mergeCells("C{$row}:H{$row}");
        }

        $sheet->freezePane('A12');
        $sheet->setShowGridlines(false);
        $widths = ['A' => 30, 'B' => 22, 'C' => 38, 'D' => 24, 'E' => 17, 'F' => 19, 'G' => 17, 'H' => 23];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle("A1:H{$this->lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:H{$this->lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A2:H2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5:A9')->getFont()->setBold(true);

        foreach ([4, 11, 15, $this->statusTitleRow, $this->warehouseTitleRow, $this->topItemTitleRow] as $row) {
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($this->sectionStyle());
            $sheet->getRowDimension($row)->setRowHeight(25);
        }
        foreach ([12, 16, $this->statusHeaderRow, $this->warehouseHeaderRow, $this->topItemHeaderRow] as $row) {
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($this->headerStyle());
            $sheet->getRowDimension($row)->setRowHeight(34);
        }

        $sheet->getStyle('A12:H13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A13:H13')->getFont()->setBold(true);
        $sheet->getStyle('A13:H13')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAF7');
        $sheet->getStyle('D13')->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle('H13')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('B17:B21')->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
        $sheet->getStyle('C'.($this->statusHeaderRow + 1).':C'.($this->warehouseTitleRow - 2))->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle('G'.($this->warehouseHeaderRow + 1).':G'.($this->topItemTitleRow - 2))->getNumberFormat()->setFormatCode('0.00%');
        if ($this->lastRow > $this->topItemHeaderRow) {
            $sheet->getStyle('G'.($this->topItemHeaderRow + 1).":G{$this->lastRow}")->getNumberFormat()->setFormatCode('0.00%');
        }
        $sheet->getStyle('C17:C21')->getAlignment()->setWrapText(true);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setBottom(0.35)->setLeft(0.25);

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

    private function statusFilterLabel(): string
    {
        return match ((string) ($this->filters['status'] ?? '')) {
            'approved' => 'Disetujui',
            'pending' => 'Menunggu Persetujuan',
            default => 'Semua status',
        };
    }

    private function sectionStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        ];
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
