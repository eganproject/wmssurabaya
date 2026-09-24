<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockForecastReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $summary,
        private readonly array $filters = [],
        private readonly ?string $generatedBy = null,
    ) {}

    public function sheets(): array
    {
        return [
            new StockForecastDashboardSheet($this->rows, $this->summary, $this->filters, $this->generatedBy),
            new StockForecastTableSheet(
                'Prioritas Pengadaan',
                $this->priorityHeadings(),
                $this->priorityRows(),
                ['C' => '@', 'G' => '#,##0', 'H' => '#,##0.00', 'I' => '#,##0', 'J' => '#,##0.00', 'K' => '0', 'L' => '0', 'N' => '#,##0', 'O' => '0', 'P' => '#,##0', 'Q' => '#,##0', 'R' => '#,##0'],
                ['C' => 20, 'D' => 38, 'E' => 28, 'F' => 24, 'M' => 18, 'W' => 52],
            ),
            new StockForecastTableSheet(
                'Detail Forecast',
                $this->detailHeadings(),
                $this->detailRows(),
                ['A' => '@', 'E' => '#,##0', 'F' => '#,##0', 'G' => '#,##0', 'H' => '#,##0', 'I' => '0', 'J' => '#,##0.00', 'K' => '#,##0.00', 'L' => '#,##0.00', 'M' => '#,##0', 'N' => '#,##0.00', 'Q' => '0', 'R' => '#,##0', 'S' => '0', 'T' => '#,##0', 'U' => '#,##0', 'V' => '#,##0'],
                ['A' => 20, 'B' => 38, 'C' => 28, 'D' => 24, 'W' => 18],
            ),
            new StockForecastTableSheet(
                'Analisis Kategori',
                $this->categoryHeadings(),
                $this->categoryRows(),
                ['C' => '#,##0', 'D' => '#,##0', 'E' => '#,##0', 'F' => '#,##0', 'G' => '#,##0', 'H' => '#,##0', 'I' => '#,##0', 'J' => '#,##0', 'K' => '#,##0', 'L' => '0.00%'],
                ['A' => 32, 'B' => 24],
            ),
            new StockForecastTableSheet(
                'Kualitas Data',
                $this->qualityHeadings(),
                $this->qualityRows(),
                ['A' => '@', 'E' => '0', 'F' => '0', 'G' => '#,##0', 'H' => '#,##0.00', 'I' => '#,##0.00', 'J' => '#,##0.00', 'K' => '#,##0.00', 'N' => '#,##0'],
                ['A' => 20, 'B' => 38, 'C' => 28, 'O' => 52],
            ),
            new StockForecastMethodologySheet($this->summary),
        ];
    }

    private function priorityHeadings(): array
    {
        return [
            'Rank', 'Tindakan', 'SKU', 'Nama Item', 'Kategori', 'Sumber Pengadaan',
            'Posisi Stok', 'Forecast/Hari', 'Forecast 30 Hari', 'Days Cover',
            'Lead Time', 'Order Dalam (Hari)', 'Tanggal Order', 'Demand Selama LT',
            'Target Hari', 'Target Qty', 'Rekomendasi Qty', 'Rekomendasi Kemasan',
            'UOM Dasar', 'UOM Kemasan', 'Trend', 'Kualitas Data', 'Catatan Analisis',
        ];
    }

    private function priorityRows(): Collection
    {
        return $this->rows
            ->filter(fn (array $row) => in_array($row['recommendation']['status'], ['order_now', 'plan'], true))
            ->sortBy([['priority', 'asc'], ['recommendation.recommended_qty', 'desc'], ['sku', 'asc']])
            ->values()
            ->map(function (array $row, int $index) {
                $scenario = $row['recommendation'];

                return [
                    $index + 1,
                    self::statusLabel($scenario['status']),
                    $row['sku'],
                    $row['name'],
                    $row['category'],
                    $row['procurement_source_label'],
                    $row['stock_position'],
                    $row['forecast_daily'],
                    $row['forecast_monthly'],
                    $row['days_cover'],
                    $scenario['lead_days'],
                    $scenario['order_in_days'],
                    $scenario['order_date'],
                    $scenario['lead_demand'],
                    $scenario['target_days'],
                    $scenario['target_qty'],
                    $scenario['recommended_qty'],
                    $scenario['recommended_packages'],
                    $row['base_unit'],
                    $row['package_unit'],
                    self::trendLabel($row['trend'], $row['trend_percent']),
                    self::qualityLabel($row['data_quality']),
                    self::analysisNote($row),
                ];
            });
    }

    private function detailHeadings(): array
    {
        return [
            'SKU', 'Nama Item', 'Kategori', 'Sumber Pengadaan', 'Stok Saat Ini',
            'Transfer Masuk', 'Posisi Stok', 'Histori Out', 'Hari Outbound Aktif',
            'Rata-rata 30H Terbaru', 'Rata-rata Periode Sebelumnya', 'Forecast/Hari',
            'Forecast 30 Hari', 'Days Cover', 'Trend', 'Tindakan', 'Lead Time',
            'Demand Selama LT', 'Target Hari', 'Target Qty', 'Rekomendasi Qty',
            'Rekomendasi Kemasan', 'Tanggal Order', 'Kualitas Data',
        ];
    }

    private function detailRows(): Collection
    {
        return $this->rows->map(function (array $row) {
            $scenario = $row['recommendation'];

            return [
                $row['sku'],
                $row['name'],
                $row['category'],
                $row['procurement_source_label'],
                $row['current_stock'],
                $row['incoming_stock'],
                $row['stock_position'],
                $row['history_qty'],
                $row['active_days'],
                $row['recent_daily'],
                $row['previous_daily'],
                $row['forecast_daily'],
                $row['forecast_monthly'],
                $row['days_cover'],
                self::trendLabel($row['trend'], $row['trend_percent']),
                self::statusLabel($scenario['status']),
                $scenario['lead_days'],
                $scenario['lead_demand'],
                $scenario['target_days'],
                $scenario['target_qty'],
                $scenario['recommended_qty'],
                $scenario['recommended_packages'],
                $scenario['order_date'],
                self::qualityLabel($row['data_quality']),
            ];
        });
    }

    private function categoryHeadings(): array
    {
        return [
            'Kategori', 'Sumber Pengadaan', 'Jumlah SKU', 'SKU Berdemand',
            'Order Sekarang', 'Jadwalkan', 'Tanpa Demand', 'Posisi Stok',
            'Histori Out', 'Forecast 30 Hari', 'Qty Rekomendasi', 'Kontribusi Rekomendasi',
        ];
    }

    private function categoryRows(): Collection
    {
        $totalRecommended = max(1, (int) $this->rows->sum('recommendation.recommended_qty'));

        return $this->rows
            ->groupBy(fn (array $row) => $row['category'].'|'.$row['procurement_source'])
            ->map(function (Collection $group) use ($totalRecommended) {
                $first = $group->first();
                $recommended = (int) $group->sum('recommendation.recommended_qty');

                return [
                    $first['category'],
                    $first['procurement_source_label'],
                    $group->count(),
                    $group->where('forecast_daily', '>', 0)->count(),
                    $group->where('recommendation.status', 'order_now')->count(),
                    $group->where('recommendation.status', 'plan')->count(),
                    $group->where('recommendation.status', 'no_demand')->count(),
                    (int) $group->sum('stock_position'),
                    (int) $group->sum('history_qty'),
                    (int) $group->sum('forecast_monthly'),
                    $recommended,
                    $recommended / $totalRecommended,
                ];
            })
            ->sortByDesc(fn (array $row) => $row[10])
            ->values();
    }

    private function qualityHeadings(): array
    {
        return [
            'SKU', 'Nama Item', 'Kategori', 'Kualitas Data', 'Hari Aktif',
            'Hari Histori', 'Histori Out', 'Rata-rata Terbaru', 'Rata-rata Sebelumnya',
            'Forecast/Hari', 'Days Cover', 'Trend', 'Tindakan', 'Rekomendasi Qty',
            'Interpretasi',
        ];
    }

    private function qualityRows(): Collection
    {
        $order = ['none' => 1, 'low' => 2, 'medium' => 3, 'high' => 4];

        return $this->rows
            ->sort(function (array $left, array $right) use ($order) {
                return [$order[$left['data_quality']] ?? 5, $left['active_days'], $left['sku']]
                    <=> [$order[$right['data_quality']] ?? 5, $right['active_days'], $right['sku']];
            })
            ->values()
            ->map(fn (array $row) => [
                $row['sku'],
                $row['name'],
                $row['category'],
                self::qualityLabel($row['data_quality']),
                $row['active_days'],
                $row['history_days'],
                $row['history_qty'],
                $row['recent_daily'],
                $row['previous_daily'],
                $row['forecast_daily'],
                $row['days_cover'],
                self::trendLabel($row['trend'], $row['trend_percent']),
                self::statusLabel($row['recommendation']['status']),
                $row['recommendation']['recommended_qty'],
                self::qualityNote($row),
            ]);
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

    private static function trendLabel(string $trend, ?float $percent): string
    {
        $label = match ($trend) {
            'growing' => 'Naik',
            'declining' => 'Turun',
            'stable' => 'Stabil',
            'new' => 'Demand Baru',
            default => 'Data Terbatas',
        };

        return $percent === null ? $label : $label.' '.($percent > 0 ? '+' : '').$percent.'%';
    }

    private static function analysisNote(array $row): string
    {
        $scenario = $row['recommendation'];

        return match ($scenario['status']) {
            'order_now' => 'Days cover sudah mencapai lead time. Validasi PO/perintah produksi berjalan dan percepat pengadaan.',
            'plan' => 'Jadwalkan pengadaan paling lambat '.$scenario['order_date'].' agar stok tidak melewati kebutuhan lead time.',
            'covered' => 'Posisi stok masih mencukupi target horizon. Pantau pada review berikutnya.',
            default => 'Belum ada demand historis yang cukup; hindari pengadaan otomatis tanpa validasi bisnis.',
        };
    }

    private static function qualityNote(array $row): string
    {
        return match ($row['data_quality']) {
            'high' => 'Histori cukup tersebar; forecast relatif lebih layak dijadikan dasar pengadaan.',
            'medium' => 'Gunakan forecast bersama informasi promo, musiman, dan pesanan yang belum tercatat.',
            'low' => 'Demand jarang terjadi; validasi manual sebelum menjalankan rekomendasi.',
            default => 'Tidak ada demand valid; periksa produk baru, data transaksi, atau status item.',
        };
    }
}
