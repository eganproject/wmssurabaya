<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.stock.index', [
            'dataUrl' => route('admin.reports.stock.data'),
            'warehouses' => Warehouse::where('is_active', true)
                ->orderByRaw("CASE WHEN type = 'bulk' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'is_default']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
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
}
