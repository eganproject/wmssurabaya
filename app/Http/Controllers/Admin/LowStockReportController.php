<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LowStockReportController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('admin.reports.low-stock.index', [
            'dataUrl' => route('admin.reports.low-stock.data'),
            'categories' => $categories,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'is_default']),
        ]);
    }

    public function data(Request $request)
    {
        $warehouseId = $request->integer('warehouse_id') ?: Warehouse::defaultId();
        $baseQuery = DB::table('items as i')
            ->leftJoin('item_stocks as s', function ($join) use ($warehouseId) {
                $join->on('s.item_id', '=', 'i.id')
                    ->where('s.warehouse_id', '=', $warehouseId);
            })
            ->leftJoin('item_warehouse_settings as ws', function ($join) use ($warehouseId) {
                $join->on('ws.item_id', '=', 'i.id')
                    ->where('ws.warehouse_id', '=', $warehouseId);
            })
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->whereRaw('COALESCE(ws.safety_stock, 0) > 0')
            ->whereRaw('COALESCE(s.stock, 0) < COALESCE(ws.safety_stock, 0)');

        $catFilter = $request->input('category_id');
        if ($catFilter !== null && $catFilter !== '') {
            if ((int) $catFilter === 0) {
                $baseQuery->whereNull('i.category_id');
            } else {
                $baseQuery->where('i.category_id', (int) $catFilter);
            }
        }

        $statusFilter = $request->input('status');
        if ($statusFilter === 'out') {
            $baseQuery->whereRaw('COALESCE(s.stock, 0) <= 0');
        } elseif ($statusFilter === 'low') {
            $baseQuery->whereRaw('COALESCE(s.stock, 0) > 0');
        }

        $recordsTotalQuery = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('ws.location', 'like', "%{$search}%")
                    ->orWhere('i.description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = (clone $recordsTotalQuery)->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $summaryQuery = clone $baseQuery;
        $summaryTotal = $recordsFiltered;
        $summaryOutOfStock = (clone $summaryQuery)
            ->whereRaw('COALESCE(s.stock, 0) <= 0')
            ->count();
        $summaryGap = (int) ((clone $summaryQuery)
            ->selectRaw('COALESCE(SUM(COALESCE(ws.safety_stock, 0) - COALESCE(s.stock, 0)), 0) as gap')
            ->value('gap') ?? 0);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $dataQuery = clone $baseQuery;
        $dataQuery->select([
            'i.id',
            'i.sku',
            'i.name',
            DB::raw('ws.location as address'),
            DB::raw('COALESCE(ws.safety_stock, 0) as safety_stock'),
            DB::raw('COALESCE(s.stock, 0) as stock'),
            DB::raw("COALESCE(c.name, 'Tanpa Kategori') as category"),
        ])
        ->orderByRaw('(COALESCE(ws.safety_stock, 0) - COALESCE(s.stock, 0)) desc')
        ->orderBy('i.sku');

        if ($length > 0) {
            $dataQuery->skip($start)->take($length);
        }

        $data = $dataQuery->get()->map(function ($row) {
            $stock = (int) ($row->stock ?? 0);
            $safety = (int) ($row->safety_stock ?? 0);
            $gap = max(0, $safety - $stock);

            return [
                'id' => $row->id,
                'sku' => $row->sku ?? '-',
                'name' => $row->name ?? '-',
                'category' => $row->category ?? '-',
                'address' => $row->address ?? '-',
                'stock' => $stock,
                'safety_stock' => $safety,
                'gap' => $gap,
                'status' => $stock <= 0 ? 'Out of Stock' : 'Low Stock',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => [
                'total_low' => $summaryTotal,
                'out_of_stock' => $summaryOutOfStock,
                'total_gap' => $summaryGap,
            ],
            'data' => $data,
        ]);
    }
}
