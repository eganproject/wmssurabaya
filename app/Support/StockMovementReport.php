<?php

namespace App\Support;

use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockMovementReport
{
    /**
     * Build the stock-movement dataset once so the web table and Excel export
     * always use the same operational-outbound and ABC classification rules.
     */
    public static function generate(array $filters = [], bool $includeTrend = false): array
    {
        $warehouseId = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        [$dateFrom, $dateTo, $periodDays] = self::period($filters);

        $usage = DB::table('stock_mutations')
            ->select(['item_id', 'warehouse_id'])
            ->selectRaw('SUM(qty) as outbound_qty')
            ->selectRaw('COUNT(*) as outbound_transactions')
            ->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as active_days')
            ->selectRaw('MAX(occurred_at) as last_outbound_at')
            ->where('direction', 'out')
            ->whereIn('source_type', ['outbound', 'picker', 'qc', 'qc_resi'])
            ->where(function ($query) {
                $query->whereNull('source_subtype')
                    ->orWhere('source_subtype', '!=', 'return');
            })
            ->whereBetween('occurred_at', [$dateFrom, $dateTo])
            ->groupBy('item_id', 'warehouse_id');

        $query = DB::table('items as i')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('item_stocks as s', function ($join) use ($warehouseId) {
                $join->on('s.item_id', '=', 'i.id');
                if ($warehouseId) {
                    $join->where('s.warehouse_id', '=', $warehouseId);
                }
            })
            ->leftJoin('warehouses as w', function ($join) use ($warehouseId) {
                if ($warehouseId) {
                    $join->where('w.id', '=', $warehouseId);
                } else {
                    $join->on('w.id', '=', 's.warehouse_id');
                }
            })
            ->leftJoin('item_warehouse_settings as ws', function ($join) use ($warehouseId) {
                $join->on('ws.item_id', '=', 'i.id');
                if ($warehouseId) {
                    $join->where('ws.warehouse_id', '=', $warehouseId);
                } else {
                    $join->on('ws.warehouse_id', '=', 's.warehouse_id');
                }
            })
            ->leftJoinSub($usage, 'usage', function ($join) use ($warehouseId) {
                $join->on('usage.item_id', '=', 'i.id');
                if ($warehouseId) {
                    $join->where('usage.warehouse_id', '=', $warehouseId);
                } else {
                    $join->on('usage.warehouse_id', '=', 's.warehouse_id');
                }
            })
            ->leftJoin('item_units as base_unit', function ($join) {
                $join->on('base_unit.item_id', '=', 'i.id')
                    ->where('base_unit.is_base', '=', true);
            })
            ->where('i.is_bundle', false)
            ->select([
                'i.id',
                'w.id as warehouse_id',
                'i.sku',
                'i.name',
                'i.is_active',
                DB::raw("COALESCE(c.name, 'Tanpa Kategori') as category"),
                DB::raw("COALESCE(w.name, '-') as warehouse"),
                DB::raw("COALESCE(w.type, '-') as warehouse_type"),
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
            $query->where(function ($builder) use ($search) {
                $builder->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('c.name', 'like', "%{$search}%")
                    ->orWhere('w.name', 'like', "%{$search}%")
                    ->orWhere('ws.location', 'like', "%{$search}%");
            });
        }

        $allRows = $query->get()->map(function ($row) use ($periodDays) {
            $outboundQty = (int) $row->outbound_qty;
            $averageDaily = $periodDays > 0 ? $outboundQty / $periodDays : 0;
            $stock = (int) $row->stock;
            $safetyStock = (int) $row->safety_stock;

            return [
                'id' => (int) $row->id,
                'warehouse_id' => $row->warehouse_id ? (int) $row->warehouse_id : null,
                'sku' => $row->sku ?: '-',
                'name' => $row->name ?: '-',
                'category' => $row->category ?: 'Tanpa Kategori',
                'warehouse' => $row->warehouse ?: '-',
                'warehouse_type' => $row->warehouse_type === Warehouse::TYPE_BULK
                    ? 'Gudang Besar'
                    : ($row->warehouse_type === Warehouse::TYPE_FULFILLMENT ? 'Gudang Kecil' : '-'),
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

        return [
            'all_rows' => $allRows,
            'rows' => $rows,
            'summary' => self::summary($allRows, $periodDays, $dateFrom, $dateTo),
            'filtered_summary' => self::summary($rows, $periodDays, $dateFrom, $dateTo),
            'trend' => $includeTrend ? self::dailyOutboundTrend($rows, $dateFrom, $dateTo, $warehouseId) : [],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'period_days' => $periodDays,
        ];
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

    private static function dailyOutboundTrend(Collection $rows, Carbon $dateFrom, Carbon $dateTo, ?int $warehouseId): array
    {
        $dailyTotals = [];
        $cursor = $dateFrom->copy()->startOfDay();
        $lastDate = $dateTo->copy()->startOfDay();

        while ($cursor->lte($lastDate)) {
            $dailyTotals[$cursor->toDateString()] = 0;
            $cursor->addDay();
        }

        $allowedPairs = $rows
            ->filter(fn (array $row) => ! empty($row['warehouse_id']))
            ->mapWithKeys(fn (array $row) => [self::pairKey((int) $row['id'], (int) $row['warehouse_id']) => true]);

        if ($allowedPairs->isNotEmpty()) {
            $trendQuery = DB::table('stock_mutations')
                ->select(['item_id', 'warehouse_id'])
                ->selectRaw('DATE(occurred_at) as movement_date')
                ->selectRaw('SUM(qty) as outbound_qty')
                ->where('direction', 'out')
                ->whereIn('source_type', ['outbound', 'picker', 'qc', 'qc_resi'])
                ->where(function ($query) {
                    $query->whereNull('source_subtype')
                        ->orWhere('source_subtype', '!=', 'return');
                })
                ->whereBetween('occurred_at', [$dateFrom, $dateTo])
                ->groupBy('item_id', 'warehouse_id', DB::raw('DATE(occurred_at)'));

            if ($warehouseId) {
                $trendQuery->where('warehouse_id', $warehouseId);
            }

            foreach ($trendQuery->get() as $point) {
                $pair = self::pairKey((int) $point->item_id, (int) $point->warehouse_id);
                $date = (string) $point->movement_date;

                if ($allowedPairs->has($pair) && array_key_exists($date, $dailyTotals)) {
                    $dailyTotals[$date] += (int) $point->outbound_qty;
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

    private static function pairKey(int $itemId, int $warehouseId): string
    {
        return $itemId.':'.$warehouseId;
    }

    private static function summary(Collection $rows, int $periodDays, Carbon $dateFrom, Carbon $dateTo): array
    {
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
