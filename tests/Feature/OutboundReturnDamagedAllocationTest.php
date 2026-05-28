<?php

namespace Tests\Feature;

use App\Models\DamagedAllocation;
use App\Models\DamagedItemStock;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OutboundTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundReturnDamagedAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_vendor_damaged_allocation_creates_outbound_return_from_damaged_stock(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin-damaged-allocation@example.test',
            'password' => 'password',
        ]);
        $item = Item::create([
            'sku' => 'DMG-RET-001',
            'name' => 'Damaged Return Item',
        ]);
        DamagedItemStock::create([
            'item_id' => $item->id,
            'stock' => 5,
            'reserved_stock' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.damaged-allocations.store'), [
                'allocation_type' => 'return_vendor',
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    ['item_id' => $item->id, 'qty' => 2],
                ],
            ])
            ->assertOk();

        $allocation = DamagedAllocation::firstOrFail();
        $this->assertSame(2, DamagedItemStock::where('item_id', $item->id)->value('reserved_stock'));

        $this->actingAs($user)
            ->postJson(route('admin.inventory.damaged-allocations.approve', $allocation->id))
            ->assertOk()
            ->assertJsonPath('message', 'Alokasi barang rusak berhasil disetujui dan retur outbound berhasil dibuat serta disetujui');

        $allocation->refresh();
        $outbound = OutboundTransaction::with('items')->findOrFail($allocation->outbound_transaction_id);
        $this->assertSame('return', $outbound->type);
        $this->assertSame('approved', $outbound->status);
        $this->assertNotNull($outbound->approved_at);
        $this->assertSame($allocation->code, $outbound->ref_no);
        $this->assertSame('damaged', $outbound->items->first()->stock_source);
        $this->assertSame(3, DamagedItemStock::where('item_id', $item->id)->value('stock'));
        $this->assertSame(0, DamagedItemStock::where('item_id', $item->id)->value('reserved_stock'));
    }

    public function test_outbound_return_validates_regular_stock_before_saving(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin-outbound-return@example.test',
            'password' => 'password',
        ]);
        $item = Item::create([
            'sku' => 'REG-RET-001',
            'name' => 'Regular Return Item',
        ]);
        ItemStock::create([
            'item_id' => $item->id,
            'stock' => 1,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.outbound.returns.store'), [
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    ['item_id' => $item->id, 'stock_source' => 'regular', 'qty' => 2],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }
}
