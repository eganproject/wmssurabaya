<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use App\Models\Category;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockForecastDashboardSheet extends DefaultValueBinder implements FromArray, WithCharts, WithCustomValueBinder, WithStyles, WithTitle
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
        $importRows = $this->rows->where('procurement_source', 'import');
        $productionRows = $this->rows->where('procurement_source', 'nanggewer');
        $priorityRows = $this->rows
            ->filter(fn (array $row) => in_array($row['recommendation']['status'], ['order_now', 'plan'], true))
            ->sortBy([['priority', 'asc'], ['recommendation.recommended_qty', 'desc']])
            ->take(10)
            ->values();

        $result = [
            ['DASHBOARD ANALISA FORECAST & PENGADAAN STOK', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            [config('app.name').' | Dasar keputusan pengadaan berdasarkan demand, posisi stok, dan lead time sumber', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['PARAMETER & FILTER', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Gudang', $this->summary['warehouse'] ?? '-', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Periode Histori', ($this->summary['date_from'] ?? '-').' s.d. '.($this->summary['date_to'] ?? '-').' ('.($this->summary['history_days'] ?? 0).' hari)', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Lead Time', 'Import '.($this->summary['import_lead_days'] ?? 0).' hari | Nanggewer '.($this->summary['production_lead_days'] ?? 0).' hari', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Siklus Review', ($this->summary['review_days'] ?? 0).' hari', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Kategori', $this->categoryLabel(), '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Sumber Pengadaan', $this->sourceLabel(), '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Tindakan', $this->actionLabel(), '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Pencarian', trim((string) ($this->filters['q'] ?? '')) ?: 'Semua item', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Dibuat', now()->format('d/m/Y H:i:s').' oleh '.($this->generatedBy ?: '-'), '', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['KPI UTAMA', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['SKU Dianalisis', 'SKU Berdemand', 'Tanpa Demand', 'SKU Import', 'SKU Nanggewer', 'Import Sekarang', 'Produksi Sekarang', 'Qty Rekom. Import', 'Qty Rekom. Produksi', 'Posisi Stok', 'Histori Out', 'Forecast 30 Hari', '', ''],
            [
                $this->rows->count(),
                $this->rows->where('forecast_daily', '>', 0)->count(),
                $this->rows->where('forecast_daily', '<=', 0)->count(),
                $importRows->count(),
                $productionRows->count(),
                $importRows->where('recommendation.status', 'order_now')->count(),
                $productionRows->where('recommendation.status', 'order_now')->count(),
                (int) $importRows->sum('recommendation.recommended_qty'),
                (int) $productionRows->sum('recommendation.recommended_qty'),
                (int) $this->rows->sum('stock_position'),
                (int) $this->rows->sum('history_qty'),
                (int) $this->rows->sum('forecast_monthly'),
                '', '',
            ],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['DISTRIBUSI SUMBER PENGADAAN', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Sumber', 'Jumlah SKU', 'SKU Berdemand', 'Order Sekarang', 'Jadwalkan', 'Tercukupi', 'Qty Rekomendasi', '', '', '', '', '', '', ''],
            $this->sourceSummaryRow('Import', $importRows),
            $this->sourceSummaryRow('Nanggewer (Produksi)', $productionRows),
            $this->sourceSummaryRow('TOTAL', $this->rows),
            ['', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['DISTRIBUSI TINDAKAN', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Tindakan', 'Jumlah SKU', 'Qty Rekomendasi', '', '', '', '', '', '', '', '', '', '', ''],
            $this->actionSummaryRow('Order Sekarang', 'order_now'),
            $this->actionSummaryRow('Jadwalkan', 'plan'),
            $this->actionSummaryRow('Tercukupi', 'covered'),
            $this->actionSummaryRow('Tanpa Demand', 'no_demand'),
            ['TOTAL', $this->rows->count(), (int) $this->rows->sum('recommendation.recommended_qty'), '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['TOP 10 PRIORITAS PENGADAAN', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Rank', 'Tindakan', 'SKU', 'Nama Item', 'Sumber', 'Posisi Stok', 'Forecast/Hari', 'Days Cover', 'Lead Time', 'Tanggal Order', 'Target Qty', 'Rekomendasi', 'Kemasan', 'Kualitas'],
        ];

        foreach ($priorityRows as $index => $row) {
            $scenario = $row['recommendation'];
            $result[] = [
                $index + 1,
                self::statusLabel($scenario['status']),
                $row['sku'],
                $row['name'],
                $row['procurement_source_label'],
                $row['stock_position'],
                $row['forecast_daily'],
                $row['days_cover'],
                $scenario['lead_days'],
                $scenario['order_date'],
                $scenario['target_qty'],
                $scenario['recommended_qty'],
                $scenario['recommended_packages'] ?: '-',
                self::qualityLabel($row['data_quality']),
            ];
        }

        return $result;
    }

    public function charts(): array
    {
        $sourceCategories = [new DataSeriesValues('String', "'Dashboard'!\$A\$21:\$A\$22", null, 2)];
        $sourceSeries = new DataSeries(
            DataSeries::TYPE_DOUGHNUTCHART,
            null,
            [0],
            [new DataSeriesValues('String', "'Dashboard'!\$B\$20", null, 1)],
            $sourceCategories,
            [new DataSeriesValues('Number', "'Dashboard'!\$B\$21:\$B\$22", null, 2)],
        );
        $sourceChart = new Chart('forecast_source_distribution', new Title('Distribusi SKU per Sumber'), new Legend(Legend::POSITION_RIGHT), new PlotArea(null, [$sourceSeries]));
        $sourceChart->setTopLeftPosition('I19');
        $sourceChart->setBottomRightPosition('N31');

        $actionCategories = [new DataSeriesValues('String', "'Dashboard'!\$A\$27:\$A\$30", null, 4)];
        $actionSeries = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            [new DataSeriesValues('String', "'Dashboard'!\$B\$26", null, 1)],
            $actionCategories,
            [new DataSeriesValues('Number', "'Dashboard'!\$B\$27:\$B\$30", null, 4)],
            DataSeries::DIRECTION_COL,
        );
        $actionChart = new Chart('forecast_action_distribution', new Title('Distribusi Tindakan SKU'), null, new PlotArea(null, [$actionSeries]));
        $actionChart->setTopLeftPosition('O19');
        $actionChart->setBottomRightPosition('T31');

        return [$sourceChart, $actionChart];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(34, 34 + min(10, $this->rows->count()));

        foreach ([1, 2, 4, 15, 19, 25, 33] as $row) {
            $sheet->mergeCells("A{$row}:N{$row}");
        }
        foreach (range(5, 13) as $row) {
            $sheet->mergeCells("B{$row}:N{$row}");
        }

        $sheet->freezePane('A16');
        $sheet->setShowGridlines(false);
        $widths = [
            'A' => 20, 'B' => 22, 'C' => 19, 'D' => 36, 'E' => 24, 'F' => 15, 'G' => 16,
            'H' => 15, 'I' => 14, 'J' => 17, 'K' => 15, 'L' => 17, 'M' => 15, 'N' => 16,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 19, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A2:N2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach ([4, 15, 19, 25, 33] as $row) {
            $sheet->getStyle("A{$row}:N{$row}")->applyFromArray($this->sectionStyle());
        }
        foreach ([16, 20, 26, 34] as $row) {
            $sheet->getStyle("A{$row}:N{$row}")->applyFromArray($this->headerStyle());
        }

        $sheet->getStyle("A4:N{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A1:N{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("D35:D{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle('A17:L17')->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
        $sheet->getStyle('B21:G23')->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
        $sheet->getStyle('B27:C31')->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
        if ($lastRow >= 35) {
            $sheet->getStyle("F35:M{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
        }
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);

        return [];
    }

    private function sourceSummaryRow(string $label, Collection $rows): array
    {
        return [
            $label,
            $rows->count(),
            $rows->where('forecast_daily', '>', 0)->count(),
            $rows->where('recommendation.status', 'order_now')->count(),
            $rows->where('recommendation.status', 'plan')->count(),
            $rows->where('recommendation.status', 'covered')->count(),
            (int) $rows->sum('recommendation.recommended_qty'),
            '', '', '', '', '', '', '',
        ];
    }

    private function actionSummaryRow(string $label, string $status): array
    {
        $rows = $this->rows->where('recommendation.status', $status);

        return [$label, $rows->count(), (int) $rows->sum('recommendation.recommended_qty'), '', '', '', '', '', '', '', '', '', '', ''];
    }

    private function categoryLabel(): string
    {
        $categoryId = $this->filters['category_id'] ?? null;
        return $categoryId ? (Category::find($categoryId)?->name ?? "Kategori #{$categoryId}") : 'Semua kategori';
    }

    private function sourceLabel(): string
    {
        return match ($this->filters['procurement_source'] ?? null) {
            'import' => 'Import',
            'nanggewer' => 'Nanggewer (Produksi)',
            default => 'Semua sumber',
        };
    }

    private function actionLabel(): string
    {
        return match ($this->filters['action'] ?? null) {
            'import_now' => 'Import sekarang',
            'production_now' => 'Produksi sekarang',
            'any_action' => 'Ada rekomendasi',
            'no_demand' => 'Tanpa demand',
            default => 'Semua tindakan',
        };
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'order_now' => 'Order Sekarang',
            'plan' => 'Jadwalkan',
            'covered' => 'Tercukupi',
            default => 'Tanpa Demand',
        };
    }

    private static function qualityLabel(string $quality): string
    {
        return match ($quality) {
            'high' => 'Baik',
            'medium' => 'Cukup',
            'low' => 'Rendah',
            default => 'Tidak Ada',
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
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];
    }
}
