<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockApiSyncRecord;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemActiveStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_route_uses_master_item_update_permission(): void
    {
        $this->assertSame(
            'admin.masterdata.items.index',
            Permission::resolveBaseRoute('admin.masterdata.items.status')
        );
        $this->assertSame('update', Permission::actionFromRoute('admin.masterdata.items.status'));
    }

    public function test_new_item_is_active_by_default_and_status_can_be_changed(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $item = Item::create(['sku' => 'STATUS-001', 'name' => 'Status Item']);

        $this->assertTrue($item->refresh()->is_active);

        $this->actingAs($user)
            ->patchJson(route('admin.masterdata.items.status', $item), ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertFalse($item->refresh()->is_active);
        $this->assertTrue(StockApiSyncRecord::where('item_id', $item->id)->where('status', 'deleted')->exists());
        $this->assertFalse(StockApiSyncRecord::where('item_id', $item->id)->where('status', '!=', 'deleted')->exists());

        $this->actingAs($user)
            ->patchJson(route('admin.masterdata.items.status', $item), ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('is_active', true);

        $this->assertTrue($item->refresh()->is_active);
        $this->assertFalse(StockApiSyncRecord::where('item_id', $item->id)->where('status', 'deleted')->exists());
    }

    public function test_master_and_item_stock_lists_can_filter_product_status(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $active = Item::create(['sku' => 'ACTIVE-001', 'name' => 'Active Item']);
        $inactive = Item::create(['sku' => 'INACTIVE-001', 'name' => 'Inactive Item', 'is_active' => false]);
        $warehouse = Warehouse::where('is_default', true)->firstOrFail();
        foreach ([$active, $inactive] as $item) {
            ItemStock::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'stock' => 0]);
        }

        $this->actingAs($user)
            ->getJson(route('admin.masterdata.items.data', ['is_active' => 0, 'length' => -1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'INACTIVE-001');

        $this->actingAs($user)
            ->getJson(route('admin.inventory.item-stocks.data', ['warehouse_id' => $warehouse->id, 'length' => -1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'ACTIVE-001');

        $this->actingAs($user)
            ->getJson(route('admin.inventory.item-stocks.data', ['warehouse_id' => $warehouse->id, 'is_active' => '', 'length' => -1]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_current_stock_report_defaults_to_active_products_only(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $active = Item::create(['sku' => 'REPORT-ACTIVE', 'name' => 'Report Active']);
        $inactive = Item::create(['sku' => 'REPORT-INACTIVE', 'name' => 'Report Inactive', 'is_active' => false]);
        $warehouse = Warehouse::where('is_default', true)->firstOrFail();
        foreach ([$active, $inactive] as $item) {
            ItemStock::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'stock' => 0]);
        }

        $this->actingAs($user)
            ->getJson(route('admin.reports.stock.data', ['warehouse_id' => $warehouse->id, 'length' => -1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'REPORT-ACTIVE');

        $this->actingAs($user)
            ->getJson(route('admin.reports.stock.data', ['warehouse_id' => $warehouse->id, 'is_active' => 0, 'length' => -1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'REPORT-INACTIVE');
    }

    public function test_historical_stock_keeps_inactive_products_while_planning_excludes_them(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $active = Item::create(['sku' => 'PLAN-ACTIVE', 'name' => 'Plan Active']);
        $inactive = Item::create(['sku' => 'PLAN-INACTIVE', 'name' => 'Plan Inactive', 'is_active' => false]);
        $warehouse = Warehouse::where('is_default', true)->firstOrFail();
        foreach ([$active, $inactive] as $item) {
            ItemStock::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'stock' => 0]);
        }

        $this->actingAs($user)
            ->getJson(route('admin.reports.stock-as-of-date.data', [
                'warehouse_id' => $warehouse->id,
                'date' => now()->toDateString(),
                'length' => -1,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($user)
            ->getJson(route('admin.reports.stock-as-of-date.data', [
                'warehouse_id' => $warehouse->id,
                'date' => now()->toDateString(),
                'is_active' => 0,
                'length' => -1,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'PLAN-INACTIVE');

        $this->actingAs($user)
            ->getJson(route('admin.reports.stock-planning.data', [
                'warehouse_id' => $warehouse->id,
                'length' => -1,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'PLAN-ACTIVE');
    }
}
