<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use App\Models\Category;
use App\Support\StockMovementReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMovementDashboardSheet extends DefaultValueBinder implements FromArray, WithCharts, WithCustomValueBinder, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    public function __construct(
        private readonly Collection $rows,
        private readonly array $summary,
        private readonly array $filters = [],
        private readonly ?string $generatedBy = null,
    ) {}

    public function title(): string
    {
        return 'Dashboard';
    }

    public function array(): array
    {
        $topItems = $this->rows->sortByDesc('outbound_qty')->take(10)->values();
        $result = [
            ['DASHBOARD ANALISIS PERGERAKAN STOK', '', '', '', '', '', '', '', '', '', '', ''],
            [config('app.name').' | Keputusan replenishment dan evaluasi stok', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', ''],
            ['FILTER LAPORAN', '', '', '', '', '', '', '', '', '', '', ''],
            ['Periode', $this->summary['date_from'].' s.d. '.$this->summary['date_to'].' ('.$this->summary['period_days'].' hari)', '', '', '', '', '', '', '', '', '', ''],
            ['Cakupan & Sumber', $this->warehouseLabel().' | Outbound manual + import resi selesai', '', '', '', '', '', '', '', '', '', ''],
            ['Kategori', $this->categoryLabel(), '', '', '', '', '', '', '', '', '', ''],
            ['Klasifikasi & Days Cover', $this->movementLabel().' | Days cover: '.$this->coverLabel(), '', '', '', '', '', '', '', '', '', ''],
            ['Status Produk', $this->statusLabel(), '', '', '', '', '', '', '', '', '', ''],
            ['Pencarian', trim((string) ($this->filters['q'] ?? '')) ?: 'Semua data', '', '', '', '', '', '', '', '', '', ''],
            ['Dibuat', now()->format('d/m/Y H:i:s').' oleh '.($this->generatedBy ?: '-'), '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', ''],
            ['KPI UTAMA', '', '', '', '', '', '', '', '', '', '', ''],
            ['SKU Dianalisis', 'Qty Keluar', 'Stok Akumulasi', 'Transaksi Keluar', 'Fast', 'Medium', 'Slow', 'Non-moving', 'Stok Non-moving', 'Di Bawah Safety', 'Cover <= 7 Hari', 'Stok Habis'],
            [
                $this->summary['total_sku'], $this->summary['total_outbound_qty'], $this->summary['total_stock'],
                $this->summary['total_transactions'], $this->summary['fast_sku'], $this->summary['medium_sku'],
                $this->summary['slow_sku'], $this->summary['non_moving_sku'], $this->summary['non_moving_stock'],
                $this->summary['below_safety_sku'], $this->summary['critical_cover_sku'], $this->summary['out_of_stock_sku'],
            ],
            ['', '', '', '', '', '', '', '', '', '', '', ''],
            ['DISTRIBUSI KLASIFIKASI', '', '', '', '', '', '', '', '', '', '', ''],
            ['Klasifikasi', 'Jumlah SKU', '% SKU', 'Qty Keluar', '% Qty Keluar', 'Stok Akumulasi', '', '', '', '', '', ''],
        ];

        foreach ([
            ['Fast moving', 'fast'],
            ['Medium moving', 'medium'],
            ['Slow moving', 'slow'],
            ['Non-moving', 'non_moving'],
        ] as [$label, $key]) {
            $group = $this->rows->where('movement_key', $key);
            $result[] = [
                $label,
                $group->count(),
                $this->percent($group->count(), $this->rows->count()),
                (int) $group->sum('outbound_qty'),
                $this->percent((int) $group->sum('outbound_qty'), (int) $this->rows->sum('outbound_qty')),
                (int) $group->sum('stock'),
                '', '', '', '', '', '',
            ];
        }

        $result[] = [
            'TOTAL',
            $this->rows->count(),
            $this->rows->isNotEmpty() ? 1 : 0,
            (int) $this->rows->sum('outbound_qty'),
            $this->rows->sum('outbound_qty') > 0 ? 1 : 0,
            (int) $this->rows->sum('stock'),
            '', '', '', '', '', '',
        ];
        $result[] = ['', '', '', '', '', '', '', '', '', '', '', ''];
        $result[] = ['PRIORITAS ANALISIS', '', '', '', '', '', '', '', '', '', '', ''];
        $result[] = ['Indikator', 'Nilai', 'Interpretasi / tindak lanjut', '', '', '', '', '', '', '', '', ''];
        $result[] = ['Stok pada SKU non-moving', $this->summary['non_moving_stock'], 'Evaluasi promo, transfer antar gudang, atau pengurangan pembelian.', '', '', '', '', '', '', '', '', ''];
        $result[] = ['SKU dengan cover <= 7 hari', $this->summary['critical_cover_sku'], 'Prioritaskan pengecekan replenishment agar penjualan tidak terputus.', '', '', '', '', '', '', '', '', ''];
        $result[] = ['SKU di bawah / sama dengan safety stock', $this->summary['below_safety_sku'], 'Periksa lead time dan usulan jumlah pemesanan.', '', '', '', '', '', '', '', '', ''];
        $result[] = ['SKU stok habis', $this->summary['out_of_stock_sku'], 'Validasi kebutuhan dan percepat pengadaan untuk SKU yang masih bergerak.', '', '', '', '', '', '', '', '', ''];
        $result[] = ['', '', '', '', '', '', '', '', '', '', '', ''];
        $result[] = ['TOP 10 QTY KELUAR', '', '', '', '', '', '', '', '', '', '', ''];
        $result[] = ['SKU', 'Nama Item', 'Cakupan Stok', 'Klasifikasi', 'Qty Keluar', 'Stok Akumulasi', 'Days Cover', '', '', '', '', ''];

        foreach ($topItems as $row) {
            $result[] = [
                $row['sku'], $row['name'], $row['warehouse'], $row['movement_label'], $row['outbound_qty'],
                $row['stock'], $row['days_cover'], '', '', '', '', '',
            ];
        }

        return $result;
    }

    public function charts(): array
    {
        $categories = [new DataSeriesValues('String', "'Dashboard'!\$A\$19:\$A\$22", null, 4)];

        $skuSeries = new DataSeries(
            DataSeries::TYPE_DOUGHNUTCHART,
            null,
            [0],
            [new DataSeriesValues('String', "'Dashboard'!\$B\$18", null, 1)],
            $categories,
            [new DataSeriesValues('Number', "'Dashboard'!\$B\$19:\$B\$22", null, 4)],
        );
        $skuChart = new Chart('movement_distribution', new Title('Distribusi SKU per Klasifikasi'), new Legend(Legend::POSITION_RIGHT), new PlotArea(null, [$skuSeries]));
        $skuChart->setTopLeftPosition('H17');
        $skuChart->setBottomRightPosition('L29');

        $qtySeries = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            [new DataSeriesValues('String', "'Dashboard'!\$D\$18", null, 1)],
            $categories,
            [new DataSeriesValues('Number', "'Dashboard'!\$D\$19:\$D\$22", null, 4)],
            DataSeries::DIRECTION_COL,
        );
        $qtyChart = new Chart('outbound_distribution', new Title('Qty Keluar per Klasifikasi'), null, new PlotArea(null, [$qtySeries]));
        $qtyChart->setTopLeftPosition('H30');
        $qtyChart->setBottomRightPosition('L43');

        return [$skuChart, $qtyChart];
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ([1, 2, 4, 13, 17, 25, 32] as $row) {
            $sheet->mergeCells("A{$row}:L{$row}");
        }
        foreach (range(5, 11) as $row) {
            $sheet->mergeCells("B{$row}:L{$row}");
        }
        foreach (range(27, 30) as $row) {
            $sheet->mergeCells("C{$row}:G{$row}");
        }

        $sheet->freezePane('A14');
        $sheet->setShowGridlines(false);
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(26);
        $sheet->getColumnDimension('C')->setWidth(22);
        foreach (range('D', 'L') as $column) {
            $sheet->getColumnDimension($column)->setWidth(16);
        }

        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 20, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(38);
        $sheet->getStyle('A2:L2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ([4, 13, 17, 25, 32] as $row) {
            $sheet->getStyle("A{$row}:L{$row}")->applyFromArray($this->sectionStyle());
            $sheet->getRowDimension($row)->setRowHeight(25);
        }
        foreach ([14, 18, 26, 33] as $row) {
            $sheet->getStyle("A{$row}:L{$row}")->applyFromArray($this->headerStyle());
        }
        $sheet->getStyle('A14:L15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A15:L15')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A15:L15')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAF7');
        $sheet->getStyle('B19:F23')->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
        $sheet->getStyle('C19:C23')->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle('E19:E23')->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle('B27:B30')->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
        $sheet->getStyle('E34:G43')->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
        $sheet->getStyle('A4:L43')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle('A1:L43')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A5:A11')->getFont()->setBold(true);
        $sheet->getStyle('C27:C30')->getAlignment()->setWrapText(true);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);

        return [];
    }

    private function percent(int $value, int $total): float
    {
        return $total > 0 ? $value / $total : 0;
    }

    private function warehouseLabel(): string
    {
        return $this->summary['warehouse'] ?? 'Gudang Besar + Gudang Kecil';
    }

    private function categoryLabel(): string
    {
        if (! array_key_exists('category_id', $this->filters) || $this->filters['category_id'] === null || $this->filters['category_id'] === '') {
            return 'Semua kategori';
        }
        $id = (int) $this->filters['category_id'];
        return $id === 0 ? 'Tanpa kategori' : (Category::find($id)?->name ?? "Kategori #{$id}");
    }

    private function movementLabel(): string
    {
        return [
            'fast' => 'Fast moving', 'medium' => 'Medium moving', 'slow' => 'Slow moving',
            'non_moving' => 'Non-moving',
        ][$this->filters['movement'] ?? ''] ?? 'Semua klasifikasi';
    }

    private function coverLabel(): string
    {
        return StockMovementReport::COVER_RANGES[$this->filters['cover'] ?? ''][0] ?? 'Semua';
    }

    private function statusLabel(): string
    {
        return match ((string) ($this->filters['is_active'] ?? '1')) {
            '0' => 'Produk nonaktif',
            '' => 'Semua produk',
            default => 'Produk aktif',
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
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ];
    }
}
