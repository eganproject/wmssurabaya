<?php

namespace Tests\Feature;

use App\Models\DamagedAllocation;
use App\Models\DamagedItemStock;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamagedAllocationFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_options_restore_current_pending_allocation_reservation_when_editing(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $warehouse = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $item = Item::create([
            'sku' => 'DMG-FORM-001',
            'name' => 'Barang Rusak Form',
            'category_id' => null,
        ]);
        $unit = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);
        DamagedItemStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'stock' => 10,
            'reserved_stock' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.damaged-allocations.store'), [
                'allocation_type' => 'dispose',
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [[
                    'item_id' => $item->id,
                    'unit_id' => $unit->id,
                    'qty_input' => 4,
                ]],
            ])
            ->assertOk();

        $allocation = DamagedAllocation::firstOrFail();

        $this->actingAs($user)
            ->getJson(route('admin.inventory.damaged-allocations.stocks'))
            ->assertOk()
            ->assertJsonPath("stocks.{$item->id}.stock", 10)
            ->assertJsonPath("stocks.{$item->id}.reserved", 4)
            ->assertJsonPath("stocks.{$item->id}.own_reserved", 0)
            ->assertJsonPath("stocks.{$item->id}.available", 6);

        $this->actingAs($user)
            ->getJson(route('admin.inventory.damaged-allocations.stocks', [
                'allocation_id' => $allocation->id,
            ]))
            ->assertOk()
            ->assertJsonPath("stocks.{$item->id}.stock", 10)
            ->assertJsonPath("stocks.{$item->id}.reserved", 4)
            ->assertJsonPath("stocks.{$item->id}.own_reserved", 4)
            ->assertJsonPath("stocks.{$item->id}.available", 10);
    }

    public function test_other_allocation_requires_an_explanation(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $warehouse = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $item = Item::create([
            'sku' => 'DMG-FORM-OTHER',
            'name' => 'Barang Rusak Lainnya',
            'category_id' => null,
        ]);
        $unit = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);
        DamagedItemStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'stock' => 5,
            'reserved_stock' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.damaged-allocations.store'), [
                'allocation_type' => 'other',
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [[
                    'item_id' => $item->id,
                    'unit_id' => $unit->id,
                    'qty_input' => 1,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);
    }
}
