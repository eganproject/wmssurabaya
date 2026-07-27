<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use App\Models\StockApiSyncRecord;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;

class StockApiSyncService
{
    public static function syncItem(int $warehouseId, int $itemId, ?Carbon $changedAt = null): void
    {
        $item = Item::with(['category', 'baseUnit.uom', 'warehouseSettings' => fn ($q) => $q->where('warehouse_id', $warehouseId)])
            ->find($itemId);
        if (! $item) {
            return;
        }

        $qty = $item->is_bundle
            ? BundleService::getVirtualStock($item->id, $warehouseId)
            : (int) (ItemStock::where('warehouse_id', $warehouseId)->where('item_id', $item->id)->value('stock') ?? 0);
        $setting = $item->warehouseSettings->first();
        $unit = $item->baseUnit;

        $attributes = [
            'item_id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'category' => $item->category?->name,
            'uom' => $unit?->uom?->name ?? $unit?->name ?? 'PCS',
            'qty' => max(0, $qty),
            'min_qty' => (int) ($setting?->safety_stock ?? 0) ?: null,
            'status' => 'active',
            'source_updated_at' => $changedAt ?? now(),
        ];
        $record = StockApiSyncRecord::where('warehouse_id', $warehouseId)
            ->where(fn ($query) => $query->where('item_id', $item->id)->orWhere('sku', $item->sku))
            ->first();
        if ($record) {
            $record->fill($attributes)->save();
        } else {
            StockApiSyncRecord::create(['warehouse_id' => $warehouseId, ...$attributes]);
        }
    }

    public static function syncBundlesUsingComponent(int $warehouseId, int $componentItemId, ?Carbon $changedAt = null): void
    {
        ItemBundle::where('component_item_id', $componentItemId)
            ->pluck('bundle_item_id')->unique()
            ->each(fn ($bundleId) => self::syncItem($warehouseId, (int) $bundleId, $changedAt));
    }

    public static function syncItemInAllActiveWarehouses(int $itemId, ?Carbon $changedAt = null): void
    {
        Warehouse::where('is_active', true)->pluck('id')
            ->each(fn ($warehouseId) => self::syncItem((int) $warehouseId, $itemId, $changedAt));
    }

    public static function markDeleted(Item $item, ?Carbon $changedAt = null): void
    {
        StockApiSyncRecord::where('item_id', $item->id)->each(function (StockApiSyncRecord $record) use ($item, $changedAt) {
            $record->update([
                'item_id' => null,
                'name' => $item->name,
                'category' => $item->category?->name,
                'status' => 'deleted',
                'qty' => 0,
                'min_qty' => null,
                'source_updated_at' => $changedAt ?? now(),
            ]);
        });
    }
}
