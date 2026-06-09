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
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('admin.inventory.item-stocks.index', compact('warehouses'));
    }

    public function show(int $id, Request $request)
    {
        $warehouseId = $request->integer('warehouse_id') ?: Warehouse::defaultId();
        $warehouse = Warehouse::findOrFail($warehouseId);
        $item = Item::with([
            'category',
            'stocks' => fn ($q) => $q->where('warehouse_id', $warehouseId),
            'warehouseSettings' => fn ($q) => $q->where('warehouse_id', $warehouseId),
        ])->findOrFail($id);
        $warehouseSetting = $item->warehouseSettings->first();

        $isBundle = (bool) $item->is_bundle;
        $currentStock = $isBundle
            ? BundleService::getVirtualStockBatch([$item->id], $warehouseId)[$item->id] ?? 0
            : ($item->stocks->first()?->stock ?? 0);

        $mutationQuery = StockMutation::with('creator')
            ->where('item_id', $id)
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $perPage = (int) $request->input('per_page', 20);
        $mutations = $mutationQuery->paginate($perPage)->withQueryString();

        return view('admin.inventory.item-stocks.show', [
            'item' => $item,
            'currentStock' => $currentStock,
            'isBundle' => $isBundle,
            'mutations' => $mutations,
            'warehouse' => $warehouse,
            'warehouseLocation' => $warehouseSetting?->location ?? $item->address,
            'warehouseSafetyStock' => (int) ($warehouseSetting?->safety_stock ?? $item->safety_stock ?? 0),
        ]);
    }

    public function data(Request $request)
    {
        $warehouseId = $request->integer('warehouse_id') ?: Warehouse::defaultId();
        $query = Item::with([
            'stocks' => fn ($q) => $q->where('warehouse_id', $warehouseId),
            'warehouseSettings' => fn ($q) => $q->where('warehouse_id', $warehouseId),
        ])->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search, $warehouseId) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
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

            return [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'stock' => $stock,
                'location' => $i->warehouseSettings->first()?->location ?? $i->address ?? '',
                'safety_stock' => (int) ($i->warehouseSettings->first()?->safety_stock ?? $i->safety_stock ?? 0),
                'is_bundle' => $isBundle,
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
