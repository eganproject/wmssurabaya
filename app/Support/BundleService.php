<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class BundleService
{
    /**
     * Compute the virtual stock for a bundle item.
     * Virtual stock = min over all components of floor(component_stock / component_qty_per_bundle).
     * Returns 0 if the bundle has no components or any component has no stock row.
     */
    public static function getVirtualStock(int $bundleItemId, ?int $warehouseId = null): int
    {
        $warehouseId ??= Warehouse::defaultId();
        $components = ItemBundle::where('bundle_item_id', $bundleItemId)->get();

        if ($components->isEmpty()) {
            return 0;
        }

        $stockByItemId = ItemStock::where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $components->pluck('component_item_id'))
            ->pluck('stock', 'item_id');

        $min = PHP_INT_MAX;
        foreach ($components as $component) {
            $componentStock = (int) ($stockByItemId[$component->component_item_id] ?? 0);
            $perBundle = max(1, (int) $component->qty);
            $available = (int) floor($componentStock / $perBundle);
            if ($available < $min) {
                $min = $available;
            }
        }

        return $min === PHP_INT_MAX ? 0 : max(0, $min);
    }

    /**
     * Compute virtual stock for multiple bundle item IDs at once.
     * Returns [item_id => virtual_stock].
     */
    public static function getVirtualStockBatch(array $bundleItemIds, ?int $warehouseId = null): array
    {
        $warehouseId ??= Warehouse::defaultId();
        if (empty($bundleItemIds)) {
            return [];
        }

        $components = ItemBundle::whereIn('bundle_item_id', $bundleItemIds)->get();
        if ($components->isEmpty()) {
            return array_fill_keys($bundleItemIds, 0);
        }

        $componentItemIds = $components->pluck('component_item_id')->unique()->values()->all();
        $stockByItemId = ItemStock::where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $componentItemIds)
            ->pluck('stock', 'item_id');

        $result = array_fill_keys($bundleItemIds, PHP_INT_MAX);

        foreach ($components as $component) {
            $bundleId = $component->bundle_item_id;
            $componentStock = (int) ($stockByItemId[$component->component_item_id] ?? 0);
            $perBundle = max(1, (int) $component->qty);
            $available = (int) floor($componentStock / $perBundle);

            if ($available < $result[$bundleId]) {
                $result[$bundleId] = $available;
            }
        }

        foreach ($result as $bundleId => $val) {
            $result[$bundleId] = $val === PHP_INT_MAX ? 0 : max(0, $val);
        }

        return $result;
    }

    /**
     * Validate that sufficient virtual stock exists for a bundle scan.
     * Throws ValidationException if insufficient.
     */
    public static function assertVirtualStockSufficient(int $bundleItemId, int $qty, ?int $warehouseId = null): void
    {
        $virtual = self::getVirtualStock($bundleItemId, $warehouseId);
        if ($virtual < $qty) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'qty' => "Stok virtual bundle tidak mencukupi (tersedia: {$virtual}, diminta: {$qty}).",
            ]);
        }
    }
}
