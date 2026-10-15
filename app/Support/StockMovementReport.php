<?php

namespace App\Support;

use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockMovementReport
{
    /**
     * Build the stock-movement dataset once so the web table and Excel export
     * always use the same combined-warehouse and eligible-outbound rules.
     */
    public static function generate(array $filters = [], bool $includeTrend = false): array
    {
        [$warehouseIds, $warehouseLabel] = self::warehouseScope();
        [$dateFrom, $dateTo, $periodDays] = self::period($filters);
        $storeFilter = ResiStoreFilter::normalize($filters['store_id'] ?? null);

        $usage = self::eligibleOutboundQuery($warehouseIds, $dateFrom, $dateTo, $storeFilter)
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
        $coverMin = self::coverBound($filters['cover_min'] ?? null);
        $coverMax = self::coverBound($filters['cover_max'] ?? null);
        if ($coverMin !== null || $coverMax !== null) {
            $rows = $rows->filter(fn (array $row) => self::matchesCover($row['days_cover'], $coverMin, $coverMax))->values();
        }

        return [
            'all_rows' => $allRows,
            'rows' => $rows,
            'summary' => self::summary($allRows, $periodDays, $dateFrom, $dateTo, $warehouseLabel, $storeFilter),
            'filtered_summary' => self::summary($rows, $periodDays, $dateFrom, $dateTo, $warehouseLabel, $storeFilter),
            'trend' => $includeTrend ? self::dailyOutboundTrend($rows, $dateFrom, $dateTo, $warehouseIds, $storeFilter) : [],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'period_days' => $periodDays,
        ];
    }

    /**
     * Manual days-cover range, both bounds inclusive. Rows without outbound
     * (days cover not measurable) never match once a bound is set.
     */
    private static function matchesCover(?float $daysCover, ?float $min, ?float $max): bool
    {
        if ($daysCover === null) {
            return false;
        }

        return ($min === null || $daysCover >= $min) && ($max === null || $daysCover <= $max);
    }

    private static function coverBound(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
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

    /**
     * Per toko Pareto classification. Only resi outbound carries a toko, so manual outbound is
     * excluded here; each toko is classified against its own total qty keluar.
     */
    public static function storeBreakdown(array $filters = []): array
    {
        [$warehouseIds] = self::warehouseScope();
        [$dateFrom, $dateTo, $periodDays] = self::period($filters);
        $storeFilter = ResiStoreFilter::normalize($filters['store_id'] ?? null);

        $query = self::eligibleOutboundQuery($warehouseIds, $dateFrom, $dateTo, $storeFilter)
            ->where('stock_mutations.source_type', 'qc_resi')
            ->join('qc_scan_resis as breakdown_qc', 'breakdown_qc.id', '=', 'stock_mutations.source_id')
            ->join('resis as breakdown_resi', 'breakdown_resi.id', '=', 'breakdown_qc.resi_id')
            ->join('items as breakdown_item', 'breakdown_item.id', '=', 'stock_mutations.item_id')
            ->where('breakdown_item.is_bundle', false)
            ->select('breakdown_resi.store_id', 'stock_mutations.item_id', 'breakdown_item.sku', 'breakdown_item.name')
            ->selectRaw('SUM(stock_mutations.qty) as outbound_qty')
            ->groupBy('breakdown_resi.store_id', 'stock_mutations.item_id', 'breakdown_item.sku', 'breakdown_item.name');

        $activeStatus = (string) ($filters['is_active'] ?? '1');
        if (in_array($activeStatus, ['0', '1'], true)) {
            $query->where('breakdown_item.is_active', $activeStatus === '1');
        }
        if (array_key_exists('category_id', $filters) && $filters['category_id'] !== null && $filters['category_id'] !== '') {
            if ((int) $filters['category_id'] === 0) {
                $query->whereNull('breakdown_item.category_id');
            } else {
                $query->where('breakdown_item.category_id', (int) $filters['category_id']);
            }
        }

        $storeNames = Store::query()->pluck('name', 'id');

        $stores = $query->get()
            ->groupBy(fn ($row) => (string) ($row->store_id ?? ''))
            ->map(function (Collection $items, string $storeId) use ($storeNames, $periodDays) {
                $total = (int) $items->sum('outbound_qty');
                $cumulative = 0;
                $classified = $items->sortBy([['outbound_qty', 'desc'], ['sku', 'asc']])->values()
                    ->map(function ($row) use (&$cumulative, $total, $periodDays) {
                        $qty = (int) $row->outbound_qty;
                        [$key, $label] = self::movementBand($qty, $total > 0 ? ($cumulative / $total) * 100 : 100);
                        $cumulative += $qty;

                        return [
                            'sku' => $row->sku ?: '-',
                            'name' => $row->name ?: '-',
                            'outbound_qty' => $qty,
                            'average_daily_outbound' => round($qty / max(1, $periodDays), 2),
                            'contribution_percent' => $total > 0 ? round($qty / $total * 100, 2) : 0,
                            'movement_key' => $key,
                            'movement_label' => $label,
                        ];
                    });

                return [
                    'store_id' => $storeId === '' ? null : (int) $storeId,
                    'store_name' => $storeId === '' ? 'Tanpa Toko' : ($storeNames[(int) $storeId] ?? '-'),
                    'total_outbound_qty' => $total,
                    'moving_sku' => $classified->count(),
                    'fast_sku' => $classified->where('movement_key', 'fast')->count(),
                    'medium_sku' => $classified->where('movement_key', 'medium')->count(),
                    'slow_sku' => $classified->where('movement_key', 'slow')->count(),
                    'top_fast' => $classified->where('movement_key', 'fast')->take(5)->values()->all(),
                    'items' => $classified->all(),
                ];
            })
            ->sortBy([['total_outbound_qty', 'desc'], ['store_name', 'asc']])
            ->values();

        return [
            'stores' => $stores,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'period_days' => $periodDays,
        ];
    }

    private static function eligibleOutboundQuery(array $warehouseIds, Carbon $dateFrom, Carbon $dateTo, ?string $storeFilter = null)
    {
        $query = DB::table('stock_mutations')
            ->whereIn('stock_mutations.warehouse_id', $warehouseIds)
            ->where('stock_mutations.direction', 'out')
            ->where(function ($query) {
                $query->where(function ($manual) {
                    $manual->where('stock_mutations.source_type', 'outbound')
                        ->where('stock_mutations.source_subtype', 'manual');
                })->orWhere(function ($resi) {
                    $resi->where('stock_mutations.source_type', 'qc_resi')
                        ->whereExists(function ($completed) {
                            $completed->selectRaw('1')
                                ->from('qc_scan_resis')
                                ->whereColumn('qc_scan_resis.id', 'stock_mutations.source_id')
                                ->where('qc_scan_resis.status', 'completed');
                        });
                });
            })
            ->whereBetween('stock_mutations.occurred_at', [$dateFrom, $dateTo]);

        if ($storeFilter !== null) {
            // Toko only exists on resi, so a toko filter limits outbound to resi mutations of that toko.
            $query->where('stock_mutations.source_type', 'qc_resi')
                ->whereExists(function ($store) use ($storeFilter) {
                    $store->selectRaw('1')
                        ->from('qc_scan_resis as store_qc')
                        ->join('resis as store_resi', 'store_resi.id', '=', 'store_qc.resi_id')
                        ->whereColumn('store_qc.id', 'stock_mutations.source_id');
                    ResiStoreFilter::apply($store, $storeFilter, 'store_resi.store_id');
                });
        }

        return $query;
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
            [$key, $label, $order] = self::movementBand($qty, $startingContribution);

            $cumulativeOutbound += $qty;
            $row['movement_key'] = $key;
            $row['movement_label'] = $label;
            $row['movement_order'] = $order;
            $row['contribution_percent'] = $totalOutbound > 0 ? round(($qty / $totalOutbound) * 100, 2) : 0;
            $row['recommended_action'] = self::recommendedAction($row);

            return $row;
        });
    }

    /**
     * Pareto band from the cumulative contribution before this SKU: <70% fast, <90% medium, rest slow.
     */
    private static function movementBand(int $qty, float $startingContribution): array
    {
        if ($qty <= 0) {
            return ['non_moving', 'Non-moving', 4];
        }
        if ($startingContribution < 70) {
            return ['fast', 'Fast moving', 1];
        }
        if ($startingContribution < 90) {
            return ['medium', 'Medium moving', 2];
        }

        return ['slow', 'Slow moving', 3];
    }

    private static function dailyOutboundTrend(
        Collection $rows,
        Carbon $dateFrom,
        Carbon $dateTo,
        array $warehouseIds,
        ?string $storeFilter = null
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
            $trend = self::eligibleOutboundQuery($warehouseIds, $dateFrom, $dateTo, $storeFilter)
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
        string $warehouseLabel,
        ?string $storeFilter = null
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
            'outbound_source' => self::outboundSourceLabel($storeFilter),
            'store' => self::storeLabel($storeFilter),
        ];
    }

    public static function storeLabel(?string $storeFilter): string
    {
        if ($storeFilter === null) {
            return 'Semua toko';
        }
        if ($storeFilter === ResiStoreFilter::NONE) {
            return 'Tanpa Toko';
        }

        return Store::query()->whereKey((int) $storeFilter)->value('name') ?? '-';
    }

    private static function outboundSourceLabel(?string $storeFilter): string
    {
        return $storeFilter === null
            ? 'Outbound manual + import resi selesai'
            : 'Import resi selesai · '.self::storeLabel($storeFilter);
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
