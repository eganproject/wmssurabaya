<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockPlanningReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.stock-planning.index', [
            'dataUrl' => route('admin.reports.stock-planning.data'),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type', 'is_default']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'lead_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'target_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'status' => ['nullable', 'in:critical,reorder,healthy,slow'],
        ]);

        $warehouseId = (int) ($validated['warehouse_id'] ?? Warehouse::defaultId());
        $warehouse = Warehouse::findOrFail($warehouseId);
        [$dateFrom, $dateTo, $periodDays] = $this->period($validated);
        $leadDays = (int) ($validated['lead_days'] ?? 7);
        $targetDays = max($leadDays, (int) ($validated['target_days'] ?? 30));

        $usage = DB::table('stock_mutations')
            ->select('item_id')
            ->selectRaw('SUM(qty) as usage_qty')
            ->where('warehouse_id', $warehouseId)
            ->where('direction', 'out')
            ->whereIn('source_type', ['outbound', 'picker', 'qc', 'qc_resi'])
            ->where(function ($query) {
                $query->whereNull('source_subtype')
                    ->orWhere('source_subtype', '!=', 'return');
            })
            ->whereBetween('occurred_at', [$dateFrom, $dateTo])
            ->groupBy('item_id');

        $incoming = DB::table('stock_transfer_items as incoming_items')
            ->join('stock_transfers as incoming_transfers', 'incoming_transfers.id', '=', 'incoming_items.stock_transfer_id')
            ->select('incoming_items.item_id')
            ->selectRaw('SUM(incoming_items.qty_base) as incoming_qty')
            ->where('incoming_transfers.destination_warehouse_id', $warehouseId)
            ->where('incoming_transfers.status', 'shipped')
            ->groupBy('incoming_items.item_id');

        $query = DB::table('items as i')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('item_stocks as s', function ($join) use ($warehouseId) {
                $join->on('s.item_id', '=', 'i.id')
                    ->where('s.warehouse_id', '=', $warehouseId);
            })
            ->leftJoin('item_warehouse_settings as ws', function ($join) use ($warehouseId) {
                $join->on('ws.item_id', '=', 'i.id')
                    ->where('ws.warehouse_id', '=', $warehouseId);
            })
            ->leftJoinSub($usage, 'usage', 'usage.item_id', '=', 'i.id')
            ->leftJoinSub($incoming, 'incoming', 'incoming.item_id', '=', 'i.id')
            ->leftJoin('item_units as base_unit', function ($join) {
                $join->on('base_unit.item_id', '=', 'i.id')
                    ->where('base_unit.is_base', '=', true);
            })
            ->leftJoin('item_units as package_unit', function ($join) {
                $join->on('package_unit.item_id', '=', 'i.id')
                    ->where('package_unit.is_base', '=', false);
            })
            ->where('i.is_bundle', false)
            ->select([
                'i.id',
                'i.sku',
                'i.name',
                'c.name as category_name',
                'ws.location',
                'base_unit.name as base_unit_name',
                'package_unit.name as package_unit_name',
                'package_unit.conversion_qty as package_conversion',
            ])
            ->selectRaw('COALESCE(s.stock, 0) as current_stock')
            ->selectRaw('COALESCE(ws.safety_stock, 0) as safety_stock')
            ->selectRaw('COALESCE(usage.usage_qty, 0) as usage_qty')
            ->selectRaw('COALESCE(incoming.incoming_qty, 0) as incoming_qty');

        if ($categoryId = $request->input('category_id')) {
            $query->where('i.category_id', (int) $categoryId);
        }
        $search = trim((string) $request->input('q'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('c.name', 'like', "%{$search}%")
                    ->orWhere('ws.location', 'like', "%{$search}%");
            });
        }

        $rows = $query->get()->map(function ($row) use ($warehouse, $periodDays, $leadDays, $targetDays) {
            $stock = (int) $row->current_stock;
            $incoming = (int) $row->incoming_qty;
            $projected = $stock + $incoming;
            $safety = (int) $row->safety_stock;
            $usage = (int) $row->usage_qty;
            $averageDaily = $periodDays > 0 ? $usage / $periodDays : 0;
            $leadDemand = (int) ceil($averageDaily * $leadDays);
            $reorderPoint = max($safety, $leadDemand);
            $targetStock = max($safety, (int) ceil($averageDaily * $targetDays));
            $daysCover = $averageDaily > 0 ? $projected / $averageDaily : null;
            if ($projected <= 0 && $averageDaily > 0) {
                $status = 'critical';
                $priority = 1;
            } elseif ($projected <= $reorderPoint && $averageDaily > 0) {
                $status = 'reorder';
                $priority = 2;
            } elseif ($averageDaily <= 0 && $projected > 0) {
                $status = 'slow';
                $priority = 4;
            } else {
                $status = 'healthy';
                $priority = 3;
            }

            $recommended = in_array($status, ['critical', 'reorder'], true)
                ? max(0, $targetStock - $projected)
                : 0;
            $packageConversion = max(1, (int) ($row->package_conversion ?? 1));
            if ($warehouse->type === Warehouse::TYPE_BULK && $recommended > 0) {
                $recommended = (int) (ceil($recommended / $packageConversion) * $packageConversion);
            }

            return [
                'id' => (int) $row->id,
                'sku' => $row->sku,
                'name' => $row->name,
                'category' => $row->category_name ?: 'Tanpa Kategori',
                'location' => $row->location ?: '-',
                'base_unit' => $row->base_unit_name ?: 'UNIT',
                'current_stock' => $stock,
                'incoming_stock' => $incoming,
                'projected_stock' => $projected,
                'safety_stock' => $safety,
                'usage_qty' => $usage,
                'average_daily_usage' => round($averageDaily, 2),
                'days_cover' => $daysCover === null ? null : round($daysCover, 1),
                'reorder_point' => $reorderPoint,
                'target_stock' => $targetStock,
                'recommended_qty' => $recommended,
                'recommended_packages' => $warehouse->type === Warehouse::TYPE_BULK && $recommended > 0
                    ? (int) ceil($recommended / $packageConversion)
                    : null,
                'package_unit' => $row->package_unit_name,
                'package_conversion' => $packageConversion,
                'status' => $status,
                'priority' => $priority,
            ];
        });

        if ($status = $validated['status'] ?? null) {
            $rows = $rows->where('status', $status)->values();
        }

        $summary = $this->summary($rows);
        $analytics = [
            'priority_items' => $rows->whereIn('status', ['critical', 'reorder'])
                ->sortBy([['priority', 'asc'], ['recommended_qty', 'desc']])
                ->take(10)
                ->values()
                ->all(),
            'category_needs' => $rows->where('recommended_qty', '>', 0)
                ->groupBy('category')
                ->map(fn (Collection $items, string $category) => [
                    'category' => $category,
                    'sku_count' => $items->count(),
                    'recommended_qty' => (int) $items->sum('recommended_qty'),
                ])
                ->sortByDesc('recommended_qty')
                ->values()
                ->all(),
        ];

        $rows = $rows
            ->sortBy([['priority', 'asc'], ['recommended_qty', 'desc'], ['sku', 'asc']])
            ->values();
        $recordsFiltered = $rows->count();
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $paged = $length > 0 ? $rows->slice($start, $length)->values() : $rows;

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => DB::table('items')->where('is_bundle', false)->count(),
            'recordsFiltered' => $recordsFiltered,
            'summary' => array_merge($summary, [
                'period_days' => $periodDays,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'lead_days' => $leadDays,
                'target_days' => $targetDays,
                'warehouse' => $warehouse->name,
            ]),
            'analytics' => $analytics,
            'data' => $paged,
        ]);
    }

    private function period(array $validated): array
    {
        try {
            $to = isset($validated['date_to'])
                ? Carbon::parse($validated['date_to'])->endOfDay()
                : now()->endOfDay();
            $from = isset($validated['date_from'])
                ? Carbon::parse($validated['date_from'])->startOfDay()
                : $to->copy()->subDays(29)->startOfDay();
        } catch (\Throwable) {
            $to = now()->endOfDay();
            $from = $to->copy()->subDays(29)->startOfDay();
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to, max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1)];
    }

    private function summary(Collection $rows): array
    {
        $actionRows = $rows->whereIn('status', ['critical', 'reorder']);

        return [
            'total_sku' => $rows->count(),
            'critical_sku' => $rows->where('status', 'critical')->count(),
            'reorder_sku' => $rows->where('status', 'reorder')->count(),
            'healthy_sku' => $rows->where('status', 'healthy')->count(),
            'slow_sku' => $rows->where('status', 'slow')->count(),
            'incoming_qty' => (int) $rows->sum('incoming_stock'),
            'recommended_qty' => (int) $actionRows->sum('recommended_qty'),
            'recommended_sku' => $actionRows->where('recommended_qty', '>', 0)->count(),
        ];
    }
}
