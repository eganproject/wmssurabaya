<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockMutation;
use App\Support\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutate_with_same_idempotency_key_only_changes_stock_once(): void
    {
        $item = Item::create([
            'sku' => 'SKU-IDEMPOTENT',
            'name' => 'Item Idempotent',
            'category_id' => null,
        ]);

        $key = StockService::idempotencyKey(['test', 'inbound', 1, $item->id]);

        $payload = [
            'item_id' => $item->id,
            'direction' => 'in',
            'qty' => 5,
            'source_type' => 'test',
            'source_subtype' => 'idempotency',
            'source_id' => 1,
            'source_code' => 'TEST-1',
            'note' => 'Idempotency test',
            'occurred_at' => now(),
            'idempotency_key' => $key,
        ];

        $first = StockService::mutate($payload);
        $second = StockService::mutate($payload);

        $this->assertSame($first?->id, $second?->id);
        $this->assertSame(5, (int) ItemStock::where('item_id', $item->id)->value('stock'));
        $this->assertSame(1, StockMutation::where('idempotency_key', $key)->count());
    }
}
