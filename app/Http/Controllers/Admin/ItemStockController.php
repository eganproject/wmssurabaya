<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\ItemStocksExport;
use App\Models\Item;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\BundleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ItemStockController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_default']);

        return view('admin.inventory.item-stocks.index', compact('warehouses'));
    }

    public function show(int $id, Request $request)
    {
        $warehouseId = $request->integer('warehouse_id') ?: Warehouse::defaultId();
        $warehouse = Warehouse::findOrFail($warehouseId);
        $item = Item::with([
            'category',
            'units' => fn ($q) => $q->orderByDesc('is_base')->orderBy('conversion_qty'),
            'stocks' => fn ($q) => $q->where('warehouse_id', $warehouseId),
            'warehouseSettings' => fn ($q) => $q->where('warehouse_id', $warehouseId),
        ])->findOrFail($id);
        $warehouseSetting = $item->warehouseSettings->first();

        $isBundle = (bool) $item->is_bundle;
        $currentStock = $isBundle
            ? BundleService::getVirtualStockBatch([$item->id], $warehouseId)[$item->id] ?? 0
            : ($item->stocks->first()?->stock ?? 0);

        $mutationQuery = StockMutation::with(['creator', 'unit'])
            ->where('item_id', $id)
            ->where('warehouse_id', $warehouseId);

        $mutationCount = (clone $mutationQuery)->count();
        $totalIn = (int) (clone $mutationQuery)->where('direction', 'in')->sum('qty');
        $totalOut = (int) (clone $mutationQuery)->where('direction', 'out')->sum('qty');
        $lastMutation = (clone $mutationQuery)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        $perPage = (int) $request->input('per_page', 20);
        $mutations = $mutationQuery
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
        $baseUnit = $item->units->firstWhere('is_base', true);
        $packageUnit = $item->units
            ->where('is_base', false)
            ->sortByDesc('conversion_qty')
            ->first();
        $packageConversion = max(1, (int) ($packageUnit?->conversion_qty ?? 1));
        $safetyStock = (int) ($warehouseSetting?->safety_stock ?? 0);
        $stockStatus = $currentStock <= 0
            ? 'empty'
            : ($safetyStock > 0 && $currentStock <= $safetyStock ? 'low' : 'safe');

        return view('admin.inventory.item-stocks.show', [
            'item' => $item,
            'currentStock' => $currentStock,
            'isBundle' => $isBundle,
            'mutations' => $mutations,
            'warehouse' => $warehouse,
            'warehouseLocation' => $warehouseSetting?->location,
            'warehouseSafetyStock' => $safetyStock,
            'mutationCount' => $mutationCount,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'lastMutation' => $lastMutation,
            'baseUnit' => $baseUnit,
            'packageUnit' => $packageUnit,
            'packageConversion' => $packageConversion,
            'stockStatus' => $stockStatus,
        ]);
    }

    public function data(Request $request)
    {
        $warehouseId = $request->integer('warehouse_id') ?: Warehouse::defaultId();
        $query = Item::with([
            'units' => fn ($q) => $q->orderByDesc('is_base')->orderBy('conversion_qty'),
            'stocks' => fn ($q) => $q->where('warehouse_id', $warehouseId),
            'warehouseSettings' => fn ($q) => $q->where('warehouse_id', $warehouseId),
        ])->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search, $warehouseId) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('warehouseSettings', fn ($settings) => $settings
                        ->where('warehouse_id', $warehouseId)
                        ->where('location', 'like', "%{$search}%"))
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = Item::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $items = $query->get();

        $bundleItemIds = $items->where('is_bundle', true)->pluck('id')->all();
        $virtualStocks = BundleService::getVirtualStockBatch($bundleItemIds, $warehouseId);

        $data = $items->map(function ($i) use ($virtualStocks) {
            $isBundle = (bool) $i->is_bundle;
            $stock = $isBundle
                ? ($virtualStocks[$i->id] ?? 0)
                : ($i->stocks->first()?->stock ?? 0);
            $setting = $i->warehouseSettings->first();
            $safetyStock = (int) ($setting?->safety_stock ?? 0);
            $baseUnit = $i->units->firstWhere('is_base', true);
            $packageUnit = $i->units
                ->where('is_base', false)
                ->sortByDesc('conversion_qty')
                ->first();
            $packageConversion = max(1, (int) ($packageUnit?->conversion_qty ?? 1));

            return [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'stock' => $stock,
                'location' => $setting?->location ?? '',
                'safety_stock' => $safetyStock,
                'is_bundle' => $isBundle,
                'base_unit' => $baseUnit?->name ?? 'PCS',
                'package_unit' => $packageUnit?->name,
                'package_conversion' => $packageConversion,
                'package_qty' => $packageUnit ? intdiv((int) $stock, $packageConversion) : null,
                'package_remainder' => $packageUnit ? (int) $stock % $packageConversion : null,
                'stock_status' => $stock <= 0
                    ? 'empty'
                    : ($safetyStock > 0 && $stock <= $safetyStock ? 'low' : 'safe'),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $warehouseId = $request->integer('warehouse_id') ?: Warehouse::defaultId();
        $filename = 'item-stocks-'.now()->format('YmdHis').'.xlsx';

        return Excel::download(new ItemStocksExport($search, $warehouseId), $filename);
    }
}
