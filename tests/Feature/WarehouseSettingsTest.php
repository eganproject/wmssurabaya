<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemWarehouseSetting;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\ItemStockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_warehouse_cannot_be_deactivated_or_changed_to_bulk(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $warehouse = Warehouse::where('is_default', true)->firstOrFail();

        $this->actingAs($user)->putJson(route('admin.masterdata.warehouses.update', $warehouse), [
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'type' => Warehouse::TYPE_BULK,
            'is_active' => false,
        ])->assertStatus(422);

        $warehouse->refresh();
        $this->assertTrue($warehouse->is_active);
        $this->assertSame(Warehouse::TYPE_FULFILLMENT, $warehouse->type);
    }

    public function test_item_can_store_different_location_and_safety_stock_per_warehouse(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $small = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $bulk = Warehouse::where('code', 'WH-BULK')->firstOrFail();

        $response = $this->actingAs($user)->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'SET-WH-001',
            'name' => 'Warehouse Setting Item',
            'category_id' => 0,
            'base_unit_name' => 'PCS',
            'warehouse_settings' => [
                ['warehouse_id' => $small->id, 'location' => 'Rak K-01', 'safety_stock' => 10],
                ['warehouse_id' => $bulk->id, 'location' => 'Blok B-02', 'safety_stock' => 100],
            ],
        ])->assertOk();

        $itemId = $response->json('item.id');
        $this->assertDatabaseHas('item_warehouse_settings', [
            'item_id' => $itemId,
            'warehouse_id' => $small->id,
            'location' => 'Rak K-01',
            'safety_stock' => 10,
        ]);
        $this->assertDatabaseHas('item_warehouse_settings', [
            'item_id' => $itemId,
            'warehouse_id' => $bulk->id,
            'location' => 'Blok B-02',
            'safety_stock' => 100,
        ]);
    }

    public function test_item_stock_seeder_creates_each_item_warehouse_combination(): void
    {
        $item = Item::create(['sku' => 'SEED-WH', 'name' => 'Seeder Warehouse', 'category_id' => 0]);
        $this->seed(ItemStockSeeder::class);

        $this->assertSame(
            Warehouse::count(),
            $item->stocks()->count()
        );
    }
}
