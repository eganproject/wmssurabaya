<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\StockAsOfDateReportExport;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class StockAsOfDateReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.stock-as-of-date.index', [
            'dataUrl' => route('admin.reports.stock-as-of-date.data'),
            'warehouses' => Warehouse::where('is_active', true)
                ->orderByRaw("CASE WHEN type = 'bulk' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'is_default']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'defaultDate' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $asOf = $this->asOfEndOfDay($request);
        $warehouseId = $request->integer('warehouse_id') ?: null;
        if ($warehouseId && ! Warehouse::whereKey($warehouseId)->where('is_active', true)->exists()) {
            $warehouseId = null;
        }

        // Stok saat ini dikurangi seluruh mutasi setelah tanggal laporan.
        // Dengan demikian hasil adalah saldo penutup pada pukul 23:59:59 tanggal yang dipilih,
        // tanpa mengubah saldo berjalan maupun riwayat mutasi.
        $movementsAfterDate = DB::table('stock_mutations')
            ->select('warehouse_id', 'item_id')
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END) as net_movement")
            ->where('occurred_at', '>', $asOf)
            ->groupBy('warehouse_id', 'item_id');

        $stockExpression = 'COALESCE(s.stock, 0) - COALESCE(m.net_movement, 0)';
        $baseQuery = DB::table('items as i')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('item_stocks as s', function ($join) use ($warehouseId) {
                $join->on('s.item_id', '=', 'i.id');
                if ($warehouseId) {
                    $join->where('s.warehouse_id', '=', $warehouseId);
                }
            })
            ->leftJoinSub($movementsAfterDate, 'm', function ($join) {
                $join->on('m.item_id', '=', 'i.id')
                    ->on('m.warehouse_id', '=', 's.warehouse_id');
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
                $join->on('base_unit.item_id', '=', 'i.id')->where('base_unit.is_base', true);
            })
            ->leftJoin('item_units as package_unit', function ($join) {
                $join->on('package_unit.item_id', '=', 'i.id')->where('package_unit.is_base', false);
            });

        $categoryId = $request->input('category_id');
        if ($categoryId !== null && $categoryId !== '') {
            (int) $categoryId === 0
                ? $baseQuery->whereNull('i.category_id')
                : $baseQuery->where('i.category_id', (int) $categoryId);
        }

        $status = $request->input('status');
        if ($status === 'empty') {
            $baseQuery->whereRaw("{$stockExpression} <= 0");
        } elseif ($status === 'low') {
            $baseQuery->whereRaw("{$stockExpression} > 0")
                ->whereRaw('COALESCE(ws.safety_stock, 0) > 0')
                ->whereRaw("{$stockExpression} <= COALESCE(ws.safety_stock, 0)");
        } elseif ($status === 'safe') {
            $baseQuery->whereRaw("{$stockExpression} > 0")
                ->where(function ($query) use ($stockExpression) {
                    $query->whereRaw('COALESCE(ws.safety_stock, 0) <= 0')
                        ->orWhereRaw("{$stockExpression} > COALESCE(ws.safety_stock, 0)");
                });
        } elseif ($status === 'has_stock') {
            $baseQuery->whereRaw("{$stockExpression} > 0");
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
        $summary = $this->summary(clone $baseQuery, $stockExpression);

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $dataQuery = (clone $baseQuery)
            ->select([
                'i.id', 'i.sku', 'i.name', 'i.is_bundle',
                DB::raw("COALESCE(c.name, 'Tanpa Kategori') as category"),
                DB::raw("COALESCE(w.name, '-') as warehouse"),
                DB::raw("COALESCE(w.type, '-') as warehouse_type"),
                DB::raw("COALESCE(ws.location, '-') as location"),
                DB::raw('COALESCE(ws.safety_stock, 0) as safety_stock'),
                DB::raw("{$stockExpression} as stock"),
                DB::raw("COALESCE(base_unit.name, 'PCS') as base_unit"),
                DB::raw('package_unit.name as package_unit'),
                DB::raw('COALESCE(package_unit.conversion_qty, 1) as package_conversion'),
            ])
            ->orderBy('w.name')
            ->orderBy('i.sku');

        if ($length > 0) {
            $dataQuery->skip($start)->take($length);
        }

        $data = $dataQuery->get()->map(fn ($row) => $this->formatRow($row));

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'as_of_date' => $asOf->toDateString(),
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        // Reuse the exact report calculation with pagination disabled so the Excel
        // file always reflects every active filter in the screen.
        $request->merge(['start' => 0, 'length' => -1]);
        $report = $this->data($request)->getData(true);
        $asOfDate = $report['as_of_date'];
        $filename = "laporan-stok-per-tanggal-{$asOfDate}.xlsx";

        return Excel::download(
            new StockAsOfDateReportExport(collect($report['data']), $asOfDate),
            $filename
        );
    }

    private function asOfEndOfDay(Request $request): Carbon
    {
        try {
            return $request->filled('date') ? Carbon::parse($request->input('date'))->endOfDay() : now()->endOfDay();
        } catch (\Throwable) {
            return now()->endOfDay();
        }
    }

    private function summary($query, string $stockExpression): array
    {
        $row = $query
            ->selectRaw("COALESCE(SUM({$stockExpression}), 0) as total_stock")
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw("SUM(CASE WHEN {$stockExpression} <= 0 THEN 1 ELSE 0 END) as empty_rows")
            ->selectRaw("SUM(CASE WHEN {$stockExpression} > 0 AND COALESCE(ws.safety_stock, 0) > 0 AND {$stockExpression} <= COALESCE(ws.safety_stock, 0) THEN 1 ELSE 0 END) as low_rows")
            ->selectRaw("SUM(CASE WHEN {$stockExpression} > 0 AND (COALESCE(ws.safety_stock, 0) <= 0 OR {$stockExpression} > COALESCE(ws.safety_stock, 0)) THEN 1 ELSE 0 END) as safe_rows")
            ->selectRaw("COALESCE(SUM(CASE WHEN COALESCE(ws.safety_stock, 0) > {$stockExpression} THEN COALESCE(ws.safety_stock, 0) - {$stockExpression} ELSE 0 END), 0) as safety_gap")
            ->first();

        return collect(['total_rows', 'total_stock', 'empty_rows', 'low_rows', 'safe_rows', 'safety_gap'])
            ->mapWithKeys(fn ($key) => [$key => (int) ($row->{$key} ?? 0)])
            ->all();
    }

    private function formatRow(object $row): array
    {
        $stock = (int) $row->stock;
        $safety = (int) $row->safety_stock;
        $packageConversion = max(1, (int) $row->package_conversion);
        $packageUnit = $row->package_unit ?: null;
        $statusKey = $stock <= 0 ? 'empty' : ($safety > 0 && $stock <= $safety ? 'low' : 'safe');

        return [
            'id' => (int) $row->id,
            'sku' => $row->sku ?: '-', 'name' => $row->name ?: '-', 'category' => $row->category ?: '-',
            'warehouse' => $row->warehouse ?: '-',
            'warehouse_type' => $row->warehouse_type === Warehouse::TYPE_BULK ? 'Gudang Besar' : ($row->warehouse_type === Warehouse::TYPE_FULFILLMENT ? 'Gudang Kecil' : '-'),
            'location' => $row->location ?: '-', 'stock' => $stock, 'safety_stock' => $safety,
            'gap_to_safety' => max(0, $safety - $stock), 'base_unit' => $row->base_unit ?: 'PCS',
            'package_unit' => $packageUnit, 'package_conversion' => $packageConversion,
            'package_qty' => $packageUnit ? intdiv($stock, $packageConversion) : null,
            'package_remainder' => $packageUnit ? $stock % $packageConversion : null,
            'is_bundle' => (bool) $row->is_bundle, 'status_key' => $statusKey,
            'status_label' => $statusKey === 'empty' ? 'Stok Habis' : ($statusKey === 'low' ? 'Stok Menipis' : 'Aman'),
        ];
    }
}
