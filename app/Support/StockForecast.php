<?php

namespace App\Support;

use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockForecast
{
    public static function generate(array $filters = []): array
    {
        $historyDays = max(30, min(365, (int) ($filters['history_days'] ?? 90)));
        $importLeadDays = max(1, min(365, (int) ($filters['import_lead_days'] ?? 90)));
        $productionLeadDays = max(1, min(365, (int) ($filters['production_lead_days'] ?? 14)));
        $reviewDays = max(1, min(180, (int) ($filters['review_days'] ?? 30)));

        [$warehouseIds, $warehouseLabel] = self::warehouseScope(
            isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null
        );

        $dateTo = now()->endOfDay();
        $dateFrom = $dateTo->copy()->subDays($historyDays - 1)->startOfDay();
        $recentDays = min(30, $historyDays);
        $remainingDays = $historyDays - $recentDays;
        $previousDays = min(30, $remainingDays);
        $olderDays = max(0, $remainingDays - $previousDays);
        $recentFrom = $dateTo->copy()->subDays($recentDays - 1)->startOfDay();
        $previousFrom = $recentFrom->copy()->subDays($previousDays)->startOfDay();

        $usage = self::eligibleOutboundQuery($warehouseIds, $dateFrom, $dateTo)
            ->select('item_id')
            ->selectRaw('SUM(qty) as total_qty')
            ->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as active_days')
            ->selectRaw('SUM(CASE WHEN occurred_at >= ? THEN qty ELSE 0 END) as recent_qty', [$recentFrom])
            ->selectRaw(
                'SUM(CASE WHEN occurred_at >= ? AND occurred_at < ? THEN qty ELSE 0 END) as previous_qty',
                [$previousFrom, $recentFrom]
            )
            ->selectRaw('SUM(CASE WHEN occurred_at < ? THEN qty ELSE 0 END) as older_qty', [$previousFrom])
            ->groupBy('item_id');

        $stocks = DB::table('item_stocks')
            ->select('item_id')
            ->selectRaw('SUM(stock) as current_stock')
            ->whereIn('warehouse_id', $warehouseIds)
            ->groupBy('item_id');

        $incoming = DB::table('stock_transfer_items as incoming_items')
            ->join('stock_transfers as incoming_transfers', 'incoming_transfers.id', '=', 'incoming_items.stock_transfer_id')
            ->select('incoming_items.item_id')
            ->selectRaw('SUM(incoming_items.qty_base) as incoming_qty')
            ->whereIn('incoming_transfers.destination_warehouse_id', $warehouseIds)
            ->where('incoming_transfers.status', 'shipped')
            ->groupBy('incoming_items.item_id');

        $query = DB::table('items as i')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoinSub($stocks, 's', 's.item_id', '=', 'i.id')
            ->leftJoinSub($incoming, 'incoming', 'incoming.item_id', '=', 'i.id')
            ->leftJoinSub($usage, 'usage', 'usage.item_id', '=', 'i.id')
            ->leftJoin('item_units as base_unit', function ($join) {
                $join->on('base_unit.item_id', '=', 'i.id')
                    ->where('base_unit.is_base', true);
            })
            ->leftJoin('item_units as package_unit', function ($join) {
                $join->on('package_unit.item_id', '=', 'i.id')
                    ->where('package_unit.is_base', false);
            })
            ->where('i.is_bundle', false)
            ->where('i.is_active', true)
            ->select([
                'i.id',
                'i.sku',
                'i.name',
                'c.name as category_name',
                'base_unit.name as base_unit_name',
                'package_unit.name as package_unit_name',
                'package_unit.conversion_qty as package_conversion',
            ])
            ->selectRaw('COALESCE(s.current_stock, 0) as current_stock')
            ->selectRaw('COALESCE(incoming.incoming_qty, 0) as incoming_qty')
            ->selectRaw('COALESCE(usage.total_qty, 0) as total_qty')
            ->selectRaw('COALESCE(usage.active_days, 0) as active_days')
            ->selectRaw('COALESCE(usage.recent_qty, 0) as recent_qty')
            ->selectRaw('COALESCE(usage.previous_qty, 0) as previous_qty')
            ->selectRaw('COALESCE(usage.older_qty, 0) as older_qty');

        if (! empty($filters['category_id'])) {
            $query->where('i.category_id', (int) $filters['category_id']);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('c.name', 'like', "%{$search}%");
            });
        }

        $rows = $query->get()->map(function ($row) use (
            $historyDays,
            $recentDays,
            $previousDays,
            $olderDays,
            $importLeadDays,
            $productionLeadDays,
            $reviewDays
        ) {
            $recentRate = $recentDays > 0 ? (int) $row->recent_qty / $recentDays : 0;
            $previousRate = $previousDays > 0 ? (int) $row->previous_qty / $previousDays : 0;
            $olderRate = $olderDays > 0 ? (int) $row->older_qty / $olderDays : 0;

            $weightedRates = [['rate' => $recentRate, 'weight' => 0.50]];
            if ($previousDays > 0) {
                $weightedRates[] = ['rate' => $previousRate, 'weight' => 0.30];
            }
            if ($olderDays > 0) {
                $weightedRates[] = ['rate' => $olderRate, 'weight' => 0.20];
            }

            $weightTotal = array_sum(array_column($weightedRates, 'weight'));
            $forecastDaily = $weightTotal > 0
                ? array_sum(array_map(fn (array $part) => $part['rate'] * $part['weight'], $weightedRates)) / $weightTotal
                : 0;

            [$trendKey, $trendPercent] = self::trend($recentRate, $previousRate, $previousDays);
            $stock = (int) $row->current_stock;
            $incomingQty = (int) $row->incoming_qty;
            $stockPosition = $stock + $incomingQty;
            $daysCover = $forecastDaily > 0 ? $stockPosition / $forecastDaily : null;
            $packageConversion = max(1, (int) ($row->package_conversion ?? 1));

            $import = self::scenario(
                $forecastDaily,
                $stockPosition,
                $importLeadDays,
                $reviewDays,
                $packageConversion
            );
            $production = self::scenario(
                $forecastDaily,
                $stockPosition,
                $productionLeadDays,
                $reviewDays
            );

            $activeDays = (int) $row->active_days;
            $quality = match (true) {
                $forecastDaily <= 0 => 'none',
                $activeDays < 7 => 'low',
                $activeDays < 20 => 'medium',
                default => 'high',
            };

            $priority = match (true) {
                $import['status'] === 'order_now' || $production['status'] === 'order_now' => 1,
                $import['status'] === 'plan' || $production['status'] === 'plan' => 2,
                $forecastDaily > 0 => 3,
                default => 4,
            };

            return [
                'id' => (int) $row->id,
                'sku' => $row->sku,
                'name' => $row->name,
                'category' => $row->category_name ?: 'Tanpa Kategori',
                'base_unit' => $row->base_unit_name ?: 'UNIT',
                'package_unit' => $row->package_unit_name,
                'package_conversion' => $packageConversion,
                'current_stock' => $stock,
                'incoming_stock' => $incomingQty,
                'stock_position' => $stockPosition,
                'history_qty' => (int) $row->total_qty,
                'active_days' => $activeDays,
                'history_days' => $historyDays,
                'recent_daily' => round($recentRate, 2),
                'previous_daily' => round($previousRate, 2),
                'forecast_daily' => round($forecastDaily, 2),
                'forecast_monthly' => (int) ceil($forecastDaily * 30),
                'days_cover' => $daysCover === null ? null : round($daysCover, 1),
                'trend' => $trendKey,
                'trend_percent' => $trendPercent,
                'data_quality' => $quality,
                'import' => $import,
                'production' => $production,
                'priority' => $priority,
            ];
        });

        if ($action = $filters['action'] ?? null) {
            $rows = $rows->filter(fn (array $row) => match ($action) {
                'import_now' => $row['import']['status'] === 'order_now',
                'production_now' => $row['production']['status'] === 'order_now',
                'any_action' => in_array($row['import']['status'], ['order_now', 'plan'], true)
                    || in_array($row['production']['status'], ['order_now', 'plan'], true),
                'no_demand' => $row['forecast_daily'] <= 0,
                default => true,
            })->values();
        }

        $rows = $rows->sort(function (array $left, array $right) {
            return ($left['priority'] <=> $right['priority'])
                ?: (max($right['import']['recommended_qty'], $right['production']['recommended_qty'])
                    <=> max($left['import']['recommended_qty'], $left['production']['recommended_qty']))
                ?: strcmp($left['sku'], $right['sku']);
        })->values();

        return [
            'rows' => $rows,
            'summary' => self::summary(
                $rows,
                $warehouseLabel,
                $dateFrom,
                $dateTo,
                $historyDays,
                $importLeadDays,
                $productionLeadDays,
                $reviewDays
            ),
        ];
    }

    private static function warehouseScope(?int $warehouseId): array
    {
        if ($warehouseId) {
            $warehouse = Warehouse::findOrFail($warehouseId);

            return [[(int) $warehouse->id], $warehouse->name];
        }

        $warehouses = Warehouse::query()
            ->whereIn('code', [Warehouse::BULK_CODE, Warehouse::DEFAULT_CODE])
            ->get(['id', 'name', 'code']);

        if ($warehouses->isEmpty()) {
            $warehouses = Warehouse::query()->where('is_active', true)->get(['id', 'name', 'code']);
        }

        $label = $warehouses
            ->sortBy(fn (Warehouse $row) => $row->code === Warehouse::BULK_CODE ? 0 : 1)
            ->pluck('name')
            ->implode(' + ');

        return [$warehouses->pluck('id')->map(fn ($id) => (int) $id)->all(), $label];
    }

    private static function eligibleOutboundQuery(array $warehouseIds, Carbon $from, Carbon $to)
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
            ->whereBetween('occurred_at', [$from, $to]);
    }

    private static function trend(float $recentRate, float $previousRate, int $previousDays): array
    {
        if ($previousDays <= 0) {
            return ['insufficient', null];
        }
        if ($previousRate <= 0) {
            return $recentRate > 0 ? ['new', null] : ['stable', 0.0];
        }

        $percent = round((($recentRate / $previousRate) - 1) * 100, 1);

        return match (true) {
            $percent > 20 => ['growing', $percent],
            $percent < -20 => ['declining', $percent],
            default => ['stable', $percent],
        };
    }

    private static function scenario(
        float $forecastDaily,
        int $stockPosition,
        int $leadDays,
        int $reviewDays,
        int $roundingMultiple = 1
    ): array {
        if ($forecastDaily <= 0) {
            return [
                'lead_days' => $leadDays,
                'lead_demand' => 0,
                'target_days' => $leadDays + $reviewDays,
                'target_qty' => 0,
                'recommended_qty' => 0,
                'recommended_packages' => null,
                'order_in_days' => null,
                'order_date' => null,
                'status' => 'no_demand',
            ];
        }

        $leadDemand = (int) ceil($forecastDaily * $leadDays);
        $targetDays = $leadDays + $reviewDays;
        $targetQty = (int) ceil($forecastDaily * $targetDays);
        $recommended = max(0, $targetQty - $stockPosition);
        if ($recommended > 0 && $roundingMultiple > 1) {
            $recommended = (int) (ceil($recommended / $roundingMultiple) * $roundingMultiple);
        }

        $daysCover = $stockPosition / $forecastDaily;
        $orderInDays = max(0, (int) floor($daysCover - $leadDays));
        $status = match (true) {
            $daysCover <= $leadDays => 'order_now',
            $recommended > 0 => 'plan',
            default => 'covered',
        };

        return [
            'lead_days' => $leadDays,
            'lead_demand' => $leadDemand,
            'target_days' => $targetDays,
            'target_qty' => $targetQty,
            'recommended_qty' => $recommended,
            'recommended_packages' => $roundingMultiple > 1 && $recommended > 0
                ? (int) ceil($recommended / $roundingMultiple)
                : null,
            'order_in_days' => $orderInDays,
            'order_date' => now()->addDays($orderInDays)->toDateString(),
            'status' => $status,
        ];
    }

    private static function summary(
        Collection $rows,
        string $warehouse,
        Carbon $dateFrom,
        Carbon $dateTo,
        int $historyDays,
        int $importLeadDays,
        int $productionLeadDays,
        int $reviewDays
    ): array {
        return [
            'total_sku' => $rows->count(),
            'demand_sku' => $rows->where('forecast_daily', '>', 0)->count(),
            'no_demand_sku' => $rows->where('forecast_daily', '<=', 0)->count(),
            'import_order_now_sku' => $rows->filter(fn (array $row) => $row['import']['status'] === 'order_now')->count(),
            'production_order_now_sku' => $rows->filter(fn (array $row) => $row['production']['status'] === 'order_now')->count(),
            'import_recommended_qty' => (int) $rows->sum(fn (array $row) => $row['import']['recommended_qty']),
            'production_recommended_qty' => (int) $rows->sum(fn (array $row) => $row['production']['recommended_qty']),
            'warehouse' => $warehouse,
            'history_days' => $historyDays,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'import_lead_days' => $importLeadDays,
            'production_lead_days' => $productionLeadDays,
            'review_days' => $reviewDays,
        ];
    }
}
