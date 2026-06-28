<?php

namespace Tests\Feature;

use App\Models\DamagedGood;
use App\Models\DamagedItemStock;
use App\Models\DamagedStockMutation;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamagedGoodsInboundReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_inbound_return_damaged_good_creates_finalized_inbound_return(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin-damaged-inbound-return@example.test',
            'password' => 'password',
        ]);
        $item = Item::create([
            'sku' => 'DMG-INB-001',
            'name' => 'Damaged Inbound Return Item',
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.damaged-goods.store'), [
                'source_type' => 'inbound_return',
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    ['item_id' => $item->id, 'qty' => 4],
                ],
            ])
            ->assertOk();

        $damage = DamagedGood::firstOrFail();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.damaged-goods.approve', $damage->id))
            ->assertOk()
            ->assertJsonPath('message', 'Barang rusak berhasil disetujui dan retur inbound berhasil dibuat');

        $damage->refresh();
        $tx = InboundTransaction::with('items')->findOrFail($damage->inbound_transaction_id);

        $this->assertSame('return', $tx->type);
        $this->assertSame('finalized', $tx->status);
        $this->assertSame($damage->code, $tx->ref_no);
        $this->assertSame($tx->code, $damage->source_ref);
        $this->assertSame(4, (int) $tx->items->first()->qty_received);
        $this->assertSame(0, (int) $tx->items->first()->qty_good);
        $this->assertSame(4, (int) $tx->items->first()->qty_damaged);
        $this->assertSame(0, (int) $tx->items->first()->qty_missing);
        $this->assertSame(4, DamagedItemStock::where('item_id', $item->id)->value('stock'));
        $this->assertSame(1, DamagedStockMutation::where('source_type', 'inbound_return')->where('source_id', $tx->id)->count());

        $this->actingAs($user)
            ->postJson(route('admin.inventory.damaged-goods.approve', $damage->id))
            ->assertOk();

        $this->assertSame(1, InboundTransaction::where('type', 'return')->count());
        $this->assertSame(4, DamagedItemStock::where('item_id', $item->id)->value('stock'));
    }
}
