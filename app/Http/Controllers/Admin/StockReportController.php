<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.stock.index', [
            'dataUrl' => route('admin.reports.stock.data'),
            'movementDataUrl' => route('admin.reports.stock.movement-data'),
            'warehouses' => Warehouse::where('is_active', true)
                ->orderByRaw("CASE WHEN type = 'bulk' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'is_default']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function movementData(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'category_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'movement' => ['nullable', 'in:fast,medium,slow,non_moving'],
            'is_active' => ['nullable', 'in:0,1'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $warehouseId = isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;
        [$dateFrom, $dateTo, $periodDays] = $this->movementPeriod($validated);

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

        $activeStatus = (string) ($validated['is_active'] ?? '1');
        if (in_array($activeStatus, ['0', '1'], true)) {
            $query->where('i.is_active', $activeStatus === '1');
        }

        if (array_key_exists('category_id', $validated) && $validated['category_id'] !== null) {
            if ((int) $validated['category_id'] === 0) {
                $query->whereNull('i.category_id');
            } else {
                $query->where('i.category_id', (int) $validated['category_id']);
            }
        }

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('c.name', 'like', "%{$search}%")
                    ->orWhere('w.name', 'like', "%{$search}%")
                    ->orWhere('ws.location', 'like', "%{$search}%");
            });
        }

        $rows = $query->get()->map(function ($row) use ($periodDays) {
            $outboundQty = (int) $row->outbound_qty;
            $averageDaily = $periodDays > 0 ? $outboundQty / $periodDays : 0;
            $stock = (int) $row->stock;

            return [
                'id' => (int) $row->id,
                'sku' => $row->sku ?: '-',
                'name' => $row->name ?: '-',
                'category' => $row->category ?: 'Tanpa Kategori',
                'warehouse' => $row->warehouse ?: '-',
                'warehouse_type' => $row->warehouse_type === Warehouse::TYPE_BULK
                    ? 'Gudang Besar'
                    : ($row->warehouse_type === Warehouse::TYPE_FULFILLMENT ? 'Gudang Kecil' : '-'),
                'location' => $row->location ?: '-',
                'stock' => $stock,
                'safety_stock' => (int) $row->safety_stock,
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

        $rows = $this->classifyMovements($rows);
        $summary = $this->movementSummary($rows, $periodDays, $dateFrom, $dateTo);

        if ($movement = $validated['movement'] ?? null) {
            $rows = $rows->where('movement_key', $movement)->values();
        }

        $rows = $rows->sortBy([
            ['movement_order', 'asc'],
            ['outbound_qty', 'desc'],
            ['sku', 'asc'],
        ])->values();

        $recordsFiltered = $rows->count();
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $paged = $length > 0 ? $rows->slice($start, $length)->values() : $rows;

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $summary['total_sku'],
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $paged,
        ]);
    }

    public function data(Request $request)
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        if ($warehouseId && !Warehouse::whereKey($warehouseId)->where('is_active', true)->exists()) {
            $warehouseId = null;
        }

        $baseQuery = DB::table('items as i')
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
            ->leftJoin('item_units as base_unit', function ($join) {
                $join->on('base_unit.item_id', '=', 'i.id')
                    ->where('base_unit.is_base', '=', true);
            })
            ->leftJoin('item_units as package_unit', function ($join) {
                $join->on('package_unit.item_id', '=', 'i.id')
                    ->where('package_unit.is_base', '=', false);
            });

        $activeStatus = (string) $request->input('is_active', '1');
        if (in_array($activeStatus, ['0', '1'], true)) {
            $baseQuery->where('i.is_active', (int) $activeStatus === 1);
        }

        $categoryId = $request->input('category_id');
        if ($categoryId !== null && $categoryId !== '') {
            if ((int) $categoryId === 0) {
                $baseQuery->whereNull('i.category_id');
            } else {
                $baseQuery->where('i.category_id', (int) $categoryId);
            }
        }

        $status = $request->input('status');
        if ($status === 'empty') {
            $baseQuery->whereRaw('COALESCE(s.stock, 0) <= 0');
        } elseif ($status === 'low') {
            $baseQuery->whereRaw('COALESCE(s.stock, 0) > 0')
                ->whereRaw('COALESCE(ws.safety_stock, 0) > 0')
                ->whereRaw('COALESCE(s.stock, 0) <= COALESCE(ws.safety_stock, 0)');
        } elseif ($status === 'safe') {
            $baseQuery->whereRaw('COALESCE(s.stock, 0) > 0')
                ->where(function ($query) {
                    $query->whereRaw('COALESCE(ws.safety_stock, 0) <= 0')
                        ->orWhereRaw('COALESCE(s.stock, 0) > COALESCE(ws.safety_stock, 0)');
                });
        } elseif ($status === 'has_stock') {
            $baseQuery->whereRaw('COALESCE(s.stock, 0) > 0');
        }

        $recordsTotalQuery = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search) {
                $query->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('c.name', 'like', "%{$search}%")
                    ->orWhere('w.name', 'like', "%{$search}%")
                    ->orWhere('ws.location', 'like', "%{$search}%")
                    ->orWhere('i.description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = (clone $recordsTotalQuery)->count();
        $recordsFiltered = (clone $baseQuery)->count();
        $summary = $this->summary(clone $baseQuery);

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);

        $dataQuery = (clone $baseQuery)
            ->select([
                'i.id',
                'i.sku',
                'i.name',
                'i.is_bundle',
                'i.is_active',
                DB::raw("COALESCE(c.name, 'Tanpa Kategori') as category"),
                DB::raw("COALESCE(w.name, '-') as warehouse"),
                DB::raw("COALESCE(w.type, '-') as warehouse_type"),
                DB::raw("COALESCE(ws.location, '-') as location"),
                DB::raw('COALESCE(ws.safety_stock, 0) as safety_stock'),
                DB::raw('COALESCE(s.stock, 0) as stock'),
                DB::raw("COALESCE(base_unit.name, 'PCS') as base_unit"),
                DB::raw('package_unit.name as package_unit'),
                DB::raw('COALESCE(package_unit.conversion_qty, 1) as package_conversion'),
            ])
            ->orderBy('w.name')
            ->orderBy('i.sku');

        if ($length > 0) {
            $dataQuery->skip($start)->take($length);
        }

        $data = $dataQuery->get()->map(function ($row) {
            $stock = (int) $row->stock;
            $safety = (int) $row->safety_stock;
            $packageConversion = max(1, (int) $row->package_conversion);
            $packageUnit = $row->package_unit ?: null;

            if ($stock <= 0) {
                $statusKey = 'empty';
                $statusLabel = 'Stok Habis';
            } elseif ($safety > 0 && $stock <= $safety) {
                $statusKey = 'low';
                $statusLabel = 'Stok Menipis';
            } else {
                $statusKey = 'safe';
                $statusLabel = 'Aman';
            }

            return [
                'id' => (int) $row->id,
                'sku' => $row->sku ?: '-',
                'name' => $row->name ?: '-',
                'category' => $row->category ?: '-',
                'warehouse' => $row->warehouse ?: '-',
                'warehouse_type' => $row->warehouse_type === Warehouse::TYPE_BULK ? 'Gudang Besar' : ($row->warehouse_type === Warehouse::TYPE_FULFILLMENT ? 'Gudang Kecil' : '-'),
                'location' => $row->location ?: '-',
                'stock' => $stock,
                'safety_stock' => $safety,
                'gap_to_safety' => max(0, $safety - $stock),
                'base_unit' => $row->base_unit ?: 'PCS',
                'package_unit' => $packageUnit,
                'package_conversion' => $packageConversion,
                'package_qty' => $packageUnit ? intdiv($stock, $packageConversion) : null,
                'package_remainder' => $packageUnit ? $stock % $packageConversion : null,
                'is_bundle' => (bool) $row->is_bundle,
                'is_active' => (bool) $row->is_active,
                'status_key' => $statusKey,
                'status_label' => $statusLabel,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    private function summary($query): array
    {
        $rows = $query
            ->selectRaw('COALESCE(SUM(COALESCE(s.stock, 0)), 0) as total_stock')
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw('SUM(CASE WHEN COALESCE(s.stock, 0) <= 0 THEN 1 ELSE 0 END) as empty_rows')
            ->selectRaw('SUM(CASE WHEN COALESCE(s.stock, 0) > 0 AND COALESCE(ws.safety_stock, 0) > 0 AND COALESCE(s.stock, 0) <= COALESCE(ws.safety_stock, 0) THEN 1 ELSE 0 END) as low_rows')
            ->selectRaw('SUM(CASE WHEN COALESCE(s.stock, 0) > 0 AND (COALESCE(ws.safety_stock, 0) <= 0 OR COALESCE(s.stock, 0) > COALESCE(ws.safety_stock, 0)) THEN 1 ELSE 0 END) as safe_rows')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(ws.safety_stock, 0) > COALESCE(s.stock, 0) THEN COALESCE(ws.safety_stock, 0) - COALESCE(s.stock, 0) ELSE 0 END), 0) as safety_gap')
            ->first();

        return [
            'total_rows' => (int) ($rows->total_rows ?? 0),
            'total_stock' => (int) ($rows->total_stock ?? 0),
            'empty_rows' => (int) ($rows->empty_rows ?? 0),
            'low_rows' => (int) ($rows->low_rows ?? 0),
            'safe_rows' => (int) ($rows->safe_rows ?? 0),
            'safety_gap' => (int) ($rows->safety_gap ?? 0),
        ];
    }

    private function movementPeriod(array $validated): array
    {
        $to = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfDay();
        $from = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : $to->copy()->subDays(29)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $periodDays = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);

        return [$from, $to, $periodDays];
    }

    private function classifyMovements(Collection $rows): Collection
    {
        $totalOutbound = (int) $rows->sum('outbound_qty');
        $cumulativeOutbound = 0;

        return $rows
            ->sortByDesc('outbound_qty')
            ->values()
            ->map(function (array $row) use (&$cumulativeOutbound, $totalOutbound) {
                $qty = (int) $row['outbound_qty'];
                $startingContribution = $totalOutbound > 0
                    ? ($cumulativeOutbound / $totalOutbound) * 100
                    : 100;

                if ($qty <= 0) {
                    $key = 'non_moving';
                    $label = 'Non-moving';
                    $order = 4;
                } elseif ($startingContribution < 70) {
                    $key = 'fast';
                    $label = 'Fast moving';
                    $order = 1;
                } elseif ($startingContribution < 90) {
                    $key = 'medium';
                    $label = 'Medium moving';
                    $order = 2;
                } else {
                    $key = 'slow';
                    $label = 'Slow moving';
                    $order = 3;
                }

                $cumulativeOutbound += $qty;
                $row['movement_key'] = $key;
                $row['movement_label'] = $label;
                $row['movement_order'] = $order;
                $row['contribution_percent'] = $totalOutbound > 0
                    ? round(($qty / $totalOutbound) * 100, 2)
                    : 0;

                return $row;
            });
    }

    private function movementSummary(Collection $rows, int $periodDays, Carbon $dateFrom, Carbon $dateTo): array
    {
        return [
            'total_sku' => $rows->count(),
            'fast_sku' => $rows->where('movement_key', 'fast')->count(),
            'medium_sku' => $rows->where('movement_key', 'medium')->count(),
            'slow_sku' => $rows->where('movement_key', 'slow')->count(),
            'non_moving_sku' => $rows->where('movement_key', 'non_moving')->count(),
            'total_outbound_qty' => (int) $rows->sum('outbound_qty'),
            'period_days' => $periodDays,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
        ];
    }
}
