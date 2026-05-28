<?php

namespace Tests\Feature;

use App\Models\DamagedItemStock;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundReturnFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_return_requires_good_and_damaged_qty_to_balance_received_qty(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin-return@example.test',
            'password' => 'password',
        ]);
        $item = Item::create([
            'sku' => 'RET-001',
            'name' => 'Return Item',
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.inbound.returns.store'), [
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty_received' => 5,
                        'qty_good' => 2,
                        'qty_damaged' => 2,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.qty_received']);
    }

    public function test_inbound_return_creation_holds_stock_in_return_warehouse_until_finalized(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin-return-finalize@example.test',
            'password' => 'password',
        ]);
        $item = Item::create([
            'sku' => 'RET-002',
            'name' => 'Return Item 2',
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.inbound.returns.store'), [
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty_received' => 5,
                        'qty_good' => 3,
                        'qty_damaged' => 2,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Retur berhasil masuk Gudang Retur. Lakukan finalisasi untuk distribusi stok.');

        $tx = InboundTransaction::where('type', 'return')->firstOrFail();

        $tx->refresh();
        $this->assertSame('approved', $tx->status);
        $this->assertNotNull($tx->approved_at);
        $this->assertNull($tx->finalized_at);
        $this->assertNull(ItemStock::where('item_id', $item->id)->value('stock'));
        $this->assertNull(DamagedItemStock::where('item_id', $item->id)->value('stock'));

        $this->actingAs($user)
            ->postJson(route('admin.inbound.returns.finalize', $tx->id))
            ->assertOk()
            ->assertJsonPath('message', 'Retur berhasil difinalisasi');

        $tx->refresh();
        $this->assertSame('finalized', $tx->status);
        $this->assertNotNull($tx->finalized_at);
        $this->assertSame(3, ItemStock::where('item_id', $item->id)->value('stock'));
        $this->assertSame(2, DamagedItemStock::where('item_id', $item->id)->value('stock'));
    }
}
