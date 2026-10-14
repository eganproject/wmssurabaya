<?php

namespace Tests\Feature;

use App\Exports\ItemStocksExport;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ItemUnit;
use App\Models\ItemWarehouseSetting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemStockOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function seedItem(): array
    {
        $small = Warehouse::where('code', Warehouse::DEFAULT_CODE)->firstOrFail();
        $bulk = Warehouse::where('code', Warehouse::BULK_CODE)->firstOrFail();
        $item = Item::create(['sku' => 'SIDE-001', 'name' => 'Side Item']);
        if (! ItemUnit::where('item_id', $item->id)->where('is_base', true)->exists()) {
            ItemUnit::create(['item_id' => $item->id, 'name' => 'PCS', 'conversion_qty' => 1, 'is_base' => true]);
        }
        ItemUnit::create(['item_id' => $item->id, 'name' => 'KOLI', 'conversion_qty' => 24, 'is_base' => false]);

        ItemStock::updateOrCreate(['warehouse_id' => $small->id, 'item_id' => $item->id], ['stock' => 5]);
        ItemStock::updateOrCreate(['warehouse_id' => $bulk->id, 'item_id' => $item->id], ['stock' => 50]);
        ItemWarehouseSetting::updateOrCreate(
            ['warehouse_id' => $small->id, 'item_id' => $item->id],
            ['location' => 'RAK-A1', 'safety_stock' => 10]
        );
        ItemWarehouseSetting::updateOrCreate(
            ['warehouse_id' => $bulk->id, 'item_id' => $item->id],
            ['location' => 'BLOK-B', 'safety_stock' => 0]
        );

        return [$small, $bulk, $item];
    }

    public function test_item_stock_list_shows_small_and_bulk_warehouse_side_by_side(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        [$small, $bulk] = $this->seedItem();

        $response = $this->actingAs($user)
            ->getJson(route('admin.inventory.item-stocks.data', ['length' => -1, 'q' => 'BLOK-B']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.total_stock', 55);

        $warehouses = $response->json('data.0.warehouses');
        $this->assertSame(5, $warehouses[$small->id]['stock']);
        $this->assertSame('low', $warehouses[$small->id]['stock_status']);
        $this->assertSame('RAK-A1', $warehouses[$small->id]['location']);
        $this->assertSame(50, $warehouses[$bulk->id]['stock']);
        $this->assertSame(2, $warehouses[$bulk->id]['package_qty']);
        $this->assertSame(2, $warehouses[$bulk->id]['package_remainder']);
    }

    public function test_item_stock_export_has_columns_for_each_warehouse(): void
    {
        $this->seedItem();
        $export = new ItemStocksExport('SIDE-001', '1');

        $headings = $export->headings();
        $this->assertContains('Gudang Kecil - Stok (Satuan Dasar)', $headings);
        $this->assertContains('Gudang Besar - Stok (Koli)', $headings);
        $this->assertSame('Total Stok (Satuan Dasar)', end($headings));

        $rows = $export->collection();
        $this->assertCount(1, $rows);
        $mapped = array_combine($headings, $export->map($rows->first()));
        $this->assertSame(5, $mapped['Gudang Kecil - Stok (Satuan Dasar)']);
        $this->assertSame('Stok Menipis', $mapped['Gudang Kecil - Status']);
        $this->assertSame(2, $mapped['Gudang Besar - Stok (Koli)']);
        $this->assertSame(2, $mapped['Gudang Besar - Sisa (Satuan Dasar)']);
        $this->assertSame(55, $mapped['Total Stok (Satuan Dasar)']);
    }

    public function test_item_stock_export_downloads(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->seedItem();

        $this->actingAs($user)
            ->get(route('admin.inventory.item-stocks.export'))
            ->assertOk()
            ->assertDownload();
    }

    public function test_item_stock_index_renders_warehouse_columns(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('admin.inventory.item-stocks.index'))
            ->assertOk()
            ->assertSee('Gudang Kecil')
            ->assertSee('Gudang Besar')
            ->assertSee('Total Stok');
    }
}
