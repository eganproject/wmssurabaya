<?php

namespace App\Support;

use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockMovementReport
{
    /**
     * Days-cover filter buckets: key => [label, min (exclusive), max (inclusive)].
     * A null min/max means unbounded; 'no_usage' matches rows without outbound.
     */
    public const COVER_RANGES = [
        'critical' => ['<= 7 hari (kritis)', null, 7],
        '8_14' => ['8 - 14 hari', 7, 14],
        '15_30' => ['15 - 30 hari', 14, 30],
        '31_60' => ['31 - 60 hari', 30, 60],
        'over_60' => ['> 60 hari', 60, null],
        'no_usage' => ['Tidak terukur (tanpa pemakaian)', null, null],
    ];

    /**
     * Build the stock-movement dataset once so the web table and Excel export
     * always use the same combined-warehouse and eligible-outbound rules.
     */
    public static function generate(array $filters = [], bool $includeTrend = false): array
    {
        [$warehouseIds, $warehouseLabel] = self::warehouseScope();
        [$dateFrom, $dateTo, $periodDays] = self::period($filters);

        $usage = self::eligibleOutboundQuery($warehouseIds, $dateFrom, $dateTo)
            ->select('item_id')
            ->selectRaw('SUM(qty) as outbound_qty')
            ->selectRaw('COUNT(*) as outbound_transactions')
            ->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as active_days')
            ->selectRaw('MAX(occurred_at) as last_outbound_at')
            ->groupBy('item_id');

        $stocks = DB::table('item_stocks')
            ->select('item_id')
            ->selectRaw('SUM(stock) as stock')
            ->whereIn('warehouse_id', $warehouseIds)
            ->groupBy('item_id');

        $settings = DB::table('item_warehouse_settings')
            ->select('item_id')
            ->selectRaw('SUM(COALESCE(safety_stock, 0)) as safety_stock')
            ->selectRaw("GROUP_CONCAT(DISTINCT NULLIF(location, '')) as location")
            ->whereIn('warehouse_id', $warehouseIds)
            ->groupBy('item_id');

        $query = DB::table('items as i')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoinSub($stocks, 's', 's.item_id', '=', 'i.id')
            ->leftJoinSub($settings, 'ws', 'ws.item_id', '=', 'i.id')
            ->leftJoinSub($usage, 'usage', 'usage.item_id', '=', 'i.id')
            ->leftJoin('item_units as base_unit', function ($join) {
                $join->on('base_unit.item_id', '=', 'i.id')
                    ->where('base_unit.is_base', '=', true);
            })
            ->where('i.is_bundle', false)
            ->select([
                'i.id',
                'i.sku',
                'i.name',
                'i.is_active',
                DB::raw("COALESCE(c.name, 'Tanpa Kategori') as category"),
                DB::raw("COALESCE(ws.location, '-') as location"),
                DB::raw('COALESCE(ws.safety_stock, 0) as safety_stock'),
                DB::raw('COALESCE(s.stock, 0) as stock'),
                DB::raw("COALESCE(base_unit.name, 'PCS') as base_unit"),
                DB::raw('COALESCE(usage.outbound_qty, 0) as outbound_qty'),
                DB::raw('COALESCE(usage.outbound_transactions, 0) as outbound_transactions'),
                DB::raw('COALESCE(usage.active_days, 0) as active_days'),
                'usage.last_outbound_at',
            ]);

        $activeStatus = (string) ($filters['is_active'] ?? '1');
        if (in_array($activeStatus, ['0', '1'], true)) {
            $query->where('i.is_active', $activeStatus === '1');
        }

        if (array_key_exists('category_id', $filters) && $filters['category_id'] !== null && $filters['category_id'] !== '') {
            if ((int) $filters['category_id'] === 0) {
                $query->whereNull('i.category_id');
            } else {
                $query->where('i.category_id', (int) $filters['category_id']);
            }
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where('i.sku', $search);
        }

        $allRows = $query->get()->map(function ($row) use ($periodDays, $warehouseLabel) {
            $outboundQty = (int) $row->outbound_qty;
            $averageDaily = $periodDays > 0 ? $outboundQty / $periodDays : 0;
            $stock = (int) $row->stock;
            $safetyStock = (int) $row->safety_stock;

            return [
                'id' => (int) $row->id,
                'sku' => $row->sku ?: '-',
                'name' => $row->name ?: '-',
                'category' => $row->category ?: 'Tanpa Kategori',
                'warehouse' => $warehouseLabel,
                'warehouse_type' => 'Akumulasi',
                'location' => $row->location ?: '-',
                'stock' => $stock,
                'safety_stock' => $safetyStock,
                'gap_to_safety' => max(0, $safetyStock - $stock),
                'base_unit' => $row->base_unit ?: 'PCS',
                'outbound_qty' => $outboundQty,
                'outbound_transactions' => (int) $row->outbound_transactions,
                'active_days' => (int) $row->active_days,
                'average_daily_outbound' => round($averageDaily, 2),
                'days_cover' => $averageDaily > 0 ? round($stock / $averageDaily, 1) : null,
                'last_outbound_at' => $row->last_outbound_at,
                'is_active' => (bool) $row->is_active,
            ];
        });

        $allRows = self::classify($allRows);
        $rows = $allRows;
        if (! empty($filters['movement'])) {
            $rows = $rows->where('movement_key', $filters['movement'])->values();
        }
        if (! empty($filters['cover']) && isset(self::COVER_RANGES[$filters['cover']])) {
            $rows = $rows->filter(fn (array $row) => self::matchesCover($row['days_cover'], $filters['cover']))->values();
        }

        return [
            'all_rows' => $allRows,
            'rows' => $rows,
            'summary' => self::summary($allRows, $periodDays, $dateFrom, $dateTo, $warehouseLabel),
            'filtered_summary' => self::summary($rows, $periodDays, $dateFrom, $dateTo, $warehouseLabel),
            'trend' => $includeTrend ? self::dailyOutboundTrend($rows, $dateFrom, $dateTo, $warehouseIds) : [],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'period_days' => $periodDays,
        ];
    }

    private static function matchesCover(?float $daysCover, string $cover): bool
    {
        if ($cover === 'no_usage') {
            return $daysCover === null;
        }
        if ($daysCover === null) {
            return false;
        }

        [, $min, $max] = self::COVER_RANGES[$cover];

        return ($min === null || $daysCover > $min) && ($max === null || $daysCover <= $max);
    }

    private static function warehouseScope(): array
    {
        $warehouses = Warehouse::query()
            ->whereIn('code', [Warehouse::BULK_CODE, Warehouse::DEFAULT_CODE])
            ->get(['id', 'name', 'code']);

        if ($warehouses->isEmpty()) {
            $warehouses = Warehouse::query()
                ->where('is_active', true)
                ->get(['id', 'name', 'code']);
        }

        $warehouses = $warehouses
            ->sortBy(fn (Warehouse $warehouse) => $warehouse->code === Warehouse::BULK_CODE ? 0 : 1)
            ->values();

        return [
            $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $warehouses->pluck('name')->implode(' + '),
        ];
    }

    private static function eligibleOutboundQuery(array $warehouseIds, Carbon $dateFrom, Carbon $dateTo)
    {
        return DB::table('stock_mutations')
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('direction', 'out')
            ->where(function ($query) {
                $query->where(function ($manual) {
                    $manual->where('source_type', 'outbound')
                        ->where('source_subtype', 'manual');
                })->orWhere(function ($resi) {
                    $resi->where('source_type', 'qc_resi')
                        ->whereExists(function ($completed) {
                            $completed->selectRaw('1')
                                ->from('qc_scan_resis')
                                ->whereColumn('qc_scan_resis.id', 'stock_mutations.source_id')
                                ->where('qc_scan_resis.status', 'completed');
                        });
                });
            })
            ->whereBetween('occurred_at', [$dateFrom, $dateTo]);
    }

    private static function period(array $filters): array
    {
        $to = ! empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfDay();
        $from = ! empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : $to->copy()->subDays(29)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to, max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1)];
    }

    private static function classify(Collection $rows): Collection
    {
        $totalOutbound = (int) $rows->sum('outbound_qty');
        $cumulativeOutbound = 0;

        return $rows->sortByDesc('outbound_qty')->values()->map(function (array $row) use (&$cumulativeOutbound, $totalOutbound) {
            $qty = (int) $row['outbound_qty'];
            $startingContribution = $totalOutbound > 0 ? ($cumulativeOutbound / $totalOutbound) * 100 : 100;

            if ($qty <= 0) {
                [$key, $label, $order] = ['non_moving', 'Non-moving', 4];
            } elseif ($startingContribution < 70) {
                [$key, $label, $order] = ['fast', 'Fast moving', 1];
            } elseif ($startingContribution < 90) {
                [$key, $label, $order] = ['medium', 'Medium moving', 2];
            } else {
                [$key, $label, $order] = ['slow', 'Slow moving', 3];
            }

            $cumulativeOutbound += $qty;
            $row['movement_key'] = $key;
            $row['movement_label'] = $label;
            $row['movement_order'] = $order;
            $row['contribution_percent'] = $totalOutbound > 0 ? round(($qty / $totalOutbound) * 100, 2) : 0;
            $row['recommended_action'] = self::recommendedAction($row);

            return $row;
        });
    }

    private static function dailyOutboundTrend(
        Collection $rows,
        Carbon $dateFrom,
        Carbon $dateTo,
        array $warehouseIds
    ): array {
        $dailyTotals = [];
        $cursor = $dateFrom->copy()->startOfDay();
        $lastDate = $dateTo->copy()->startOfDay();

        while ($cursor->lte($lastDate)) {
            $dailyTotals[$cursor->toDateString()] = 0;
            $cursor->addDay();
        }

        $allowedItemIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($allowedItemIds->isNotEmpty()) {
            $trend = self::eligibleOutboundQuery($warehouseIds, $dateFrom, $dateTo)
                ->whereIn('item_id', $allowedItemIds->all())
                ->selectRaw('DATE(occurred_at) as movement_date')
                ->selectRaw('SUM(qty) as outbound_qty')
                ->groupBy(DB::raw('DATE(occurred_at)'))
                ->get();

            foreach ($trend as $point) {
                $date = (string) $point->movement_date;
                if (array_key_exists($date, $dailyTotals)) {
                    $dailyTotals[$date] = (int) $point->outbound_qty;
                }
            }
        }

        return collect($dailyTotals)
            ->map(fn (int $quantity, string $date) => [
                'date' => $date,
                'quantity' => $quantity,
            ])
            ->values()
            ->all();
    }

    private static function summary(
        Collection $rows,
        int $periodDays,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $warehouseLabel
    ): array {
        return [
            'total_sku' => $rows->count(),
            'fast_sku' => $rows->where('movement_key', 'fast')->count(),
            'medium_sku' => $rows->where('movement_key', 'medium')->count(),
            'slow_sku' => $rows->where('movement_key', 'slow')->count(),
            'non_moving_sku' => $rows->where('movement_key', 'non_moving')->count(),
            'total_outbound_qty' => (int) $rows->sum('outbound_qty'),
            'total_stock' => (int) $rows->sum('stock'),
            'non_moving_stock' => (int) $rows->where('movement_key', 'non_moving')->sum('stock'),
            'below_safety_sku' => $rows->filter(fn (array $row) => $row['safety_stock'] > 0 && $row['stock'] <= $row['safety_stock'])->count(),
            'critical_cover_sku' => $rows->filter(fn (array $row) => $row['days_cover'] !== null && $row['days_cover'] <= 7)->count(),
            'out_of_stock_sku' => $rows->where('stock', '<=', 0)->count(),
            'total_transactions' => (int) $rows->sum('outbound_transactions'),
            'period_days' => $periodDays,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'warehouse' => $warehouseLabel,
            'outbound_source' => 'Outbound manual + import resi selesai',
        ];
    }

    private static function recommendedAction(array $row): string
    {
        if ($row['movement_key'] === 'non_moving' && $row['stock'] > 0) {
            return 'Evaluasi promo, transfer, atau pengurangan pembelian';
        }
        if ($row['days_cover'] !== null && $row['days_cover'] <= 7) {
            return 'Prioritaskan replenishment; cover stok <= 7 hari';
        }
        if ($row['safety_stock'] > 0 && $row['stock'] <= $row['safety_stock']) {
            return 'Rencanakan replenishment hingga di atas safety stock';
        }
        if ($row['movement_key'] === 'slow') {
            return 'Pantau tren dan sesuaikan jumlah pembelian';
        }

        return 'Pertahankan dan monitor berkala';
    }
}
