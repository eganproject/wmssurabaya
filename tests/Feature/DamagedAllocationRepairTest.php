<?php

namespace Tests\Feature;

use App\Models\DamagedAllocation;
use App\Models\DamagedItemStock;
use App\Models\DamagedStockMutation;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ItemUnit;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamagedAllocationRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_repair_moves_damaged_stock_back_to_display_stock(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $warehouse = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $item = Item::create([
            'sku' => 'DMG-REPAIR-001',
            'name' => 'Barang Selesai Diperbaiki',
            'category_id' => null,
        ]);
        $unit = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);
        ItemStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'stock' => 4,
        ]);
        DamagedItemStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'stock' => 10,
            'reserved_stock' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.damaged-allocations.store'), [
                'allocation_type' => 'repair',
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [[
                    'item_id' => $item->id,
                    'unit_id' => $unit->id,
                    'qty_input' => 3,
                ]],
            ])
            ->assertOk();

        $allocation = DamagedAllocation::firstOrFail();
        $this->assertSame(3, (int) DamagedItemStock::where([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
        ])->value('reserved_stock'));

        $this->actingAs($user)
            ->postJson(route('admin.inventory.damaged-allocations.approve', $allocation->id))
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Alokasi barang rusak berhasil disetujui dan stok display berhasil ditambahkan'
            );

        $damagedStock = DamagedItemStock::where([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
        ])->firstOrFail();

        $this->assertSame(7, (int) $damagedStock->stock);
        $this->assertSame(0, (int) $damagedStock->reserved_stock);
        $this->assertSame(7, (int) ItemStock::where([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
        ])->value('stock'));

        $this->assertDatabaseHas('damaged_stock_mutations', [
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'direction' => 'out',
            'qty' => 3,
            'source_type' => 'damaged_allocation',
            'source_subtype' => 'repair',
            'source_id' => $allocation->id,
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'direction' => 'in',
            'qty' => 3,
            'source_type' => 'damaged_allocation',
            'source_subtype' => 'repair',
            'source_id' => $allocation->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.damaged-allocations.approve', $allocation->id))
            ->assertOk()
            ->assertJsonPath('message', 'Data sudah disetujui');

        $this->assertSame(7, (int) ItemStock::where([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
        ])->value('stock'));
        $this->assertSame(1, DamagedStockMutation::where([
            'source_type' => 'damaged_allocation',
            'source_subtype' => 'repair',
            'source_id' => $allocation->id,
        ])->count());
        $this->assertSame(1, StockMutation::where([
            'source_type' => 'damaged_allocation',
            'source_subtype' => 'repair',
            'source_id' => $allocation->id,
        ])->count());
    }
}
