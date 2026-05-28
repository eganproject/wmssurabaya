<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemStock;
use Illuminate\Database\Seeder;

class ItemStockSeeder extends Seeder
{
    public function run(): void
    {
        Item::select('id')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                ItemStock::firstOrCreate(
                    ['item_id' => $item->id],
                    ['stock' => 0]
                );
            }
        });
    }
}
