<?php

namespace App\Support;

use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the "Stok Item" overview: one row per item with the stock of every
 * active warehouse side by side (Gudang Kecil, Gudang Besar, ...).
 */
class ItemStockOverview
{
    public static function warehouses(): Collection
    {
        return Warehouse::where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByRaw('CASE WHEN type = ? THEN 1 ELSE 0 END', [Warehouse::TYPE_BULK])
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'is_default']);
    }

    public static function query(Collection $warehouses, string $activeStatus = '1'): Builder
    {
        $warehouseIds = $warehouses->pluck('id')->all();

        $query = Item::with([
            'units' => fn ($q) => $q->orderByDesc('is_base')->orderBy('conversion_qty'),
            'stocks' => fn ($q) => $q->whereIn('warehouse_id', $warehouseIds),
            'warehouseSettings' => fn ($q) => $q->whereIn('warehouse_id', $warehouseIds),
        ])->orderBy('name');

        if (in_array($activeStatus, ['0', '1'], true)) {
            $query->where('is_active', (int) $activeStatus === 1);
        }

        return $query;
    }

    public static function applySearch(Builder $query, Collection $warehouses, string $search): Builder
    {
        $search = trim($search);
        if ($search === '') {
            return $query;
        }

        $warehouseIds = $warehouses->pluck('id')->all();

        return $query->where(function ($q) use ($search, $warehouseIds) {
            $q->where('sku', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhereHas('warehouseSettings', fn ($settings) => $settings
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->where('location', 'like', "%{$search}%"))
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(Collection $items, Collection $warehouses): Collection
    {
        $bundleItemIds = $items->where('is_bundle', true)->pluck('id')->all();
        $virtualStocks = [];
        foreach ($warehouses as $warehouse) {
            $virtualStocks[$warehouse->id] = BundleService::getVirtualStockBatch($bundleItemIds, $warehouse->id);
        }

        return $items->map(function (Item $item) use ($warehouses, $virtualStocks) {
            $isBundle = (bool) $item->is_bundle;
            $baseUnit = $item->units->firstWhere('is_base', true);
            $packageUnit = $item->units
                ->where('is_base', false)
                ->sortByDesc('conversion_qty')
                ->first();
            $packageConversion = max(1, (int) ($packageUnit?->conversion_qty ?? 1));

            $perWarehouse = [];
            $totalStock = 0;
            foreach ($warehouses as $warehouse) {
                $stock = $isBundle
                    ? (int) ($virtualStocks[$warehouse->id][$item->id] ?? 0)
                    : (int) ($item->stocks->firstWhere('warehouse_id', $warehouse->id)?->stock ?? 0);
                $setting = $item->warehouseSettings->firstWhere('warehouse_id', $warehouse->id);
                $safetyStock = (int) ($setting?->safety_stock ?? 0);
                $totalStock += $stock;

                $perWarehouse[$warehouse->id] = [
                    'stock' => $stock,
                    'location' => $setting?->location ?? '',
                    'safety_stock' => $safetyStock,
                    'package_qty' => $packageUnit ? intdiv($stock, $packageConversion) : null,
                    'package_remainder' => $packageUnit ? $stock % $packageConversion : null,
                    'stock_status' => self::status($stock, $safetyStock),
                ];
            }

            return [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'is_bundle' => $isBundle,
                'is_active' => (bool) $item->is_active,
                'base_unit' => $baseUnit?->name ?? 'PCS',
                'package_unit' => $packageUnit?->name,
                'package_conversion' => $packageConversion,
                'total_stock' => $totalStock,
                'warehouses' => $perWarehouse,
            ];
        });
    }

    public static function status(int $stock, int $safetyStock): string
    {
        if ($stock <= 0) {
            return 'empty';
        }

        return $safetyStock > 0 && $stock <= $safetyStock ? 'low' : 'safe';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'empty' => 'Stok Habis',
            'low' => 'Stok Menipis',
            default => 'Stok Aman',
        };
    }
}
