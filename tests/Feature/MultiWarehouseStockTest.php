<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ItemUnit;
use App\Models\InboundTransaction;
use App\Models\OutboundTransaction;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Models\User;
use App\Support\StockService;
use App\Support\DamagedStockService;
use App\Models\DamagedItemStock;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiWarehouseStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_is_separated_by_warehouse_and_records_before_after(): void
    {
        $item = Item::create([
            'sku' => 'MW-001',
            'name' => 'Multi Warehouse Item',
            'category_id' => 0,
        ]);
        $small = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $bulk = Warehouse::where('code', 'WH-BULK')->firstOrFail();

        $first = StockService::mutate([
            'warehouse_id' => $bulk->id,
            'item_id' => $item->id,
            'direction' => 'in',
            'qty' => 120,
            'source_type' => 'test',
            'source_id' => 1,
        ]);
        $second = StockService::mutate([
            'warehouse_id' => $bulk->id,
            'item_id' => $item->id,
            'direction' => 'out',
            'qty' => 24,
            'source_type' => 'test',
            'source_id' => 2,
        ]);

        $this->assertSame(0, $first->stock_before);
        $this->assertSame(120, $first->stock_after);
        $this->assertSame(120, $second->stock_before);
        $this->assertSame(96, $second->stock_after);
        $this->assertSame(96, (int) ItemStock::where('warehouse_id', $bulk->id)->where('item_id', $item->id)->value('stock'));
        $this->assertNull(ItemStock::where('warehouse_id', $small->id)->where('item_id', $item->id)->value('stock'));
    }

    public function test_mutation_can_record_package_unit_while_storing_base_quantity(): void
    {
        $item = Item::create(['sku' => 'BOX-001', 'name' => 'Box Item', 'category_id' => 0]);
        $warehouse = Warehouse::where('code', 'WH-BULK')->firstOrFail();
        $unit = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'DUS',
            'conversion_qty' => 24,
            'is_base' => false,
        ]);

        $mutation = StockService::mutate([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'direction' => 'in',
            'qty_input' => 5,
            'qty' => 120,
            'source_type' => 'test',
            'source_id' => 3,
        ]);

        $this->assertSame(5, $mutation->qty_input);
        $this->assertSame(24, $mutation->conversion_qty);
        $this->assertSame(120, $mutation->qty);
        $this->assertSame(120, $mutation->stock_after);
        $this->assertSame(1, StockMutation::whereKey($mutation->id)->count());
    }

    public function test_transfer_ship_and_receive_moves_stock_between_warehouses(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $item = Item::create(['sku' => 'TRF-ITEM', 'name' => 'Transfer Item', 'category_id' => 0]);
        $unit = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'DUS',
            'conversion_qty' => 12,
            'is_base' => false,
        ]);
        $small = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $bulk = Warehouse::where('code', 'WH-BULK')->firstOrFail();

        StockService::mutate([
            'warehouse_id' => $bulk->id,
            'item_id' => $item->id,
            'direction' => 'in',
            'qty' => 120,
            'source_type' => 'opening',
            'source_id' => 1,
        ]);

        $create = $this->actingAs($user)->postJson(route('admin.inventory.stock-transfers.store'), [
            'source_warehouse_id' => $bulk->id,
            'destination_warehouse_id' => $small->id,
            'transacted_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'item_id' => $item->id,
                'unit_id' => $unit->id,
                'qty_input' => 2,
            ]],
        ])->assertOk();

        $transferId = $create->json('id');
        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.ship', $transferId))
            ->assertOk();
        $this->assertSame(96, (int) ItemStock::where('warehouse_id', $bulk->id)->where('item_id', $item->id)->value('stock'));

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.receive', $transferId), [
                'items' => [[
                    'item_id' => $item->id,
                    'qty_received_input' => 2,
                ]],
            ])
            ->assertOk();
        $this->assertSame(24, (int) ItemStock::where('warehouse_id', $small->id)->where('item_id', $item->id)->value('stock'));
    }

    public function test_transfer_ship_and_receive_routes_require_update_permission(): void
    {
        $this->assertSame('admin.inventory.stock-transfers.index', Permission::resolveBaseRoute('admin.inventory.stock-transfers.ship'));
        $this->assertSame('admin.inventory.stock-transfers.index', Permission::resolveBaseRoute('admin.inventory.stock-transfers.receive'));
        $this->assertSame('update', Permission::actionFromRoute('admin.inventory.stock-transfers.ship'));
        $this->assertSame('update', Permission::actionFromRoute('admin.inventory.stock-transfers.receive'));
    }

    public function test_inbound_and_outbound_accept_package_units_while_posting_base_stock(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $item = Item::create(['sku' => 'FLOW-KOLI', 'name' => 'Flow Koli', 'category_id' => 0]);
        ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);
        $package = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'KOLI',
            'conversion_qty' => 12,
            'is_base' => false,
        ]);
        $bulk = Warehouse::where('code', 'WH-BULK')->firstOrFail();

        $this->actingAs($user)->postJson(route('admin.inbound.receipts.store'), [
            'warehouse_id' => $bulk->id,
            'transacted_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'item_id' => $item->id,
                'unit_id' => $package->id,
                'qty' => 2,
            ]],
        ])->assertOk();
        $inbound = InboundTransaction::latest('id')->firstOrFail();
        $this->actingAs($user)
            ->postJson(route('admin.inbound.receipts.approve', $inbound->id))
            ->assertOk();

        $this->assertSame(24, (int) ItemStock::where('warehouse_id', $bulk->id)->where('item_id', $item->id)->value('stock'));
        $this->assertSame(2, (int) $inbound->items()->firstOrFail()->qty_input);

        $this->actingAs($user)->postJson(route('admin.outbound.manuals.store'), [
            'warehouse_id' => $bulk->id,
            'transacted_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'item_id' => $item->id,
                'unit_id' => $package->id,
                'qty' => 1,
            ]],
        ])->assertOk();
        $outbound = OutboundTransaction::latest('id')->firstOrFail();
        $this->actingAs($user)
            ->postJson(route('admin.outbound.manuals.approve', $outbound->id))
            ->assertOk();

        $this->assertSame(12, (int) ItemStock::where('warehouse_id', $bulk->id)->where('item_id', $item->id)->value('stock'));
        $this->assertSame(1, (int) $outbound->items()->firstOrFail()->qty_input);
    }

    public function test_damaged_stock_is_separated_by_warehouse(): void
    {
        $item = Item::create(['sku' => 'DMG-WH', 'name' => 'Damaged Warehouse', 'category_id' => 0]);
        $small = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $bulk = Warehouse::where('code', 'WH-BULK')->firstOrFail();

        DamagedStockService::mutate([
            'warehouse_id' => $small->id,
            'item_id' => $item->id,
            'direction' => 'in',
            'qty' => 3,
            'source_type' => 'test',
            'source_id' => 10,
        ]);
        DamagedStockService::mutate([
            'warehouse_id' => $bulk->id,
            'item_id' => $item->id,
            'direction' => 'in',
            'qty' => 7,
            'source_type' => 'test',
            'source_id' => 11,
        ]);

        $this->assertSame(3, (int) DamagedItemStock::where('warehouse_id', $small->id)->where('item_id', $item->id)->value('stock'));
        $this->assertSame(7, (int) DamagedItemStock::where('warehouse_id', $bulk->id)->where('item_id', $item->id)->value('stock'));
    }

    public function test_transfer_draft_can_be_edited_and_cancelled(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $item = Item::create(['sku' => 'TRF-EDIT', 'name' => 'Transfer Edit', 'category_id' => 0]);
        $unit = ItemUnit::create(['item_id' => $item->id, 'name' => 'PCS', 'conversion_qty' => 1, 'is_base' => true]);
        $small = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $bulk = Warehouse::where('code', 'WH-BULK')->firstOrFail();

        $create = $this->actingAs($user)->postJson(route('admin.inventory.stock-transfers.store'), [
            'source_warehouse_id' => $bulk->id,
            'destination_warehouse_id' => $small->id,
            'transacted_at' => now(),
            'items' => [['item_id' => $item->id, 'unit_id' => $unit->id, 'qty_input' => 2]],
        ])->assertOk();

        $id = $create->json('id');
        $this->actingAs($user)->putJson(route('admin.inventory.stock-transfers.update', $id), [
            'source_warehouse_id' => $bulk->id,
            'destination_warehouse_id' => $small->id,
            'transacted_at' => now(),
            'note' => 'draft diperbarui',
            'items' => [['item_id' => $item->id, 'unit_id' => $unit->id, 'qty_input' => 4]],
        ])->assertOk();

        $transfer = StockTransfer::with('items')->findOrFail($id);
        $this->assertSame('draft diperbarui', $transfer->note);
        $this->assertSame(4, (int) $transfer->items->first()->qty_input);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.cancel', $id))
            ->assertOk();
        $this->assertSame('cancelled', $transfer->fresh()->status);
    }

    public function test_transfer_can_receive_partial_quantity_with_discrepancy(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $item = Item::create(['sku' => 'TRF-PART', 'name' => 'Transfer Partial', 'category_id' => 0]);
        $unit = ItemUnit::create(['item_id' => $item->id, 'name' => 'KOLI', 'conversion_qty' => 10, 'is_base' => false]);
        $small = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $bulk = Warehouse::where('code', 'WH-BULK')->firstOrFail();
        StockService::mutate([
            'warehouse_id' => $bulk->id,
            'item_id' => $item->id,
            'direction' => 'in',
            'qty' => 50,
            'source_type' => 'test',
            'source_id' => 200,
        ]);

        $create = $this->actingAs($user)->postJson(route('admin.inventory.stock-transfers.store'), [
            'source_warehouse_id' => $bulk->id,
            'destination_warehouse_id' => $small->id,
            'transacted_at' => now(),
            'items' => [['item_id' => $item->id, 'unit_id' => $unit->id, 'qty_input' => 5]],
        ])->assertOk();
        $id = $create->json('id');
        $this->actingAs($user)->postJson(route('admin.inventory.stock-transfers.ship', $id))->assertOk();
        $this->actingAs($user)->postJson(route('admin.inventory.stock-transfers.receive', $id), [
            'items' => [[
                'item_id' => $item->id,
                'qty_received_input' => 4,
                'discrepancy_note' => 'Satu koli rusak saat perjalanan',
            ]],
        ])->assertOk();

        $transfer = StockTransfer::with('items')->findOrFail($id);
        $this->assertSame('received_with_discrepancy', $transfer->status);
        $this->assertSame(40, (int) $transfer->items->first()->qty_received_base);
        $this->assertSame(40, (int) ItemStock::where('warehouse_id', $small->id)->where('item_id', $item->id)->value('stock'));
        $this->assertSame(0, (int) ItemStock::where('warehouse_id', $bulk->id)->where('item_id', $item->id)->value('stock'));
    }
}
