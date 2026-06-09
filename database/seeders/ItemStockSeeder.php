<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class ItemStockSeeder extends Seeder
{
    public function run(): void
    {
        $warehouseIds = Warehouse::pluck('id');
        Item::select('id')->chunkById(200, function ($items) use ($warehouseIds) {
            foreach ($items as $item) {
                foreach ($warehouseIds as $warehouseId) {
                    ItemStock::firstOrCreate(
                        ['warehouse_id' => $warehouseId, 'item_id' => $item->id],
                        ['stock' => 0]
                    );
                }
            }
        });
    }
}
