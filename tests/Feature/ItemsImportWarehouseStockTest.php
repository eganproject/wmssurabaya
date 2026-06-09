<?php

namespace Tests\Feature;

use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ItemUnit;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ItemsImportWarehouseStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_import_creates_units_and_opening_stock_for_both_warehouses(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $path = tempnam(sys_get_temp_dir(), 'items-import-').'.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            [
                'sku',
                'name',
                'base_unit',
                'package_unit',
                'package_conversion_qty',
                'small_warehouse_stock',
                'large_warehouse_stock',
            ],
            ['IMP-001', 'Produk Import', 'PCS', 'KOLI', 24, 100, 10],
        ]);
        (new Xlsx($spreadsheet))->save($path);

        try {
            $this->actingAs($user)
                ->post(route('admin.masterdata.items.import'), [
                    'file' => new UploadedFile(
                        $path,
                        'items.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ], ['Accept' => 'application/json'])
                ->assertOk()
                ->assertJsonPath('created', 1);
        } finally {
            @unlink($path);
        }

        $item = Item::where('sku', 'IMP-001')->firstOrFail();
        $small = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $large = Warehouse::where('code', 'WH-BULK')->firstOrFail();
        $package = ItemUnit::where('item_id', $item->id)->where('name', 'KOLI')->firstOrFail();

        $this->assertSame(24, $package->conversion_qty);
        $this->assertSame(100, (int) ItemStock::where('warehouse_id', $small->id)->where('item_id', $item->id)->value('stock'));
        $this->assertSame(240, (int) ItemStock::where('warehouse_id', $large->id)->where('item_id', $item->id)->value('stock'));
        $this->assertSame(2, InboundTransaction::where('type', 'opening')->count());

        $largeMutation = StockMutation::where('warehouse_id', $large->id)
            ->where('item_id', $item->id)
            ->firstOrFail();
        $this->assertSame(10, $largeMutation->qty_input);
        $this->assertSame(24, $largeMutation->conversion_qty);
        $this->assertSame(240, $largeMutation->qty);
        $this->assertSame(0, $largeMutation->stock_before);
        $this->assertSame(240, $largeMutation->stock_after);
    }

    public function test_legacy_stock_column_still_posts_to_small_warehouse(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $path = tempnam(sys_get_temp_dir(), 'items-import-legacy-').'.xlsx';

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['sku', 'name', 'stock'],
            ['IMP-LEGACY', 'Produk Legacy', 15],
        ]);
        (new Xlsx($spreadsheet))->save($path);

        try {
            $this->actingAs($user)
                ->post(route('admin.masterdata.items.import'), [
                    'file' => new UploadedFile(
                        $path,
                        'legacy.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ], ['Accept' => 'application/json'])
                ->assertOk();
        } finally {
            @unlink($path);
        }

        $item = Item::where('sku', 'IMP-LEGACY')->firstOrFail();
        $small = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $large = Warehouse::where('code', 'WH-BULK')->firstOrFail();

        $this->assertSame(15, (int) ItemStock::where('warehouse_id', $small->id)->where('item_id', $item->id)->value('stock'));
        $this->assertSame(0, (int) ItemStock::where('warehouse_id', $large->id)->where('item_id', $item->id)->value('stock'));
    }

    public function test_existing_item_keeps_optional_fields_when_headers_are_absent(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $item = Item::create([
            'sku' => 'IMP-KEEP',
            'name' => 'Nama Lama',
            'category_id' => 0,
            'description' => 'Deskripsi lama',
        ]);
        ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'SET',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);

        $response = $this->importRows($user, [
            ['sku', 'name'],
            ['IMP-KEEP', 'Nama Baru'],
        ]);

        $response->assertOk();
        $item->refresh();
        $this->assertSame('Nama Baru', $item->name);
        $this->assertSame('Deskripsi lama', $item->description);
        $this->assertSame('SET', ItemUnit::where('item_id', $item->id)->where('is_base', true)->value('name'));
    }

    public function test_import_rejects_opening_stock_for_item_with_stock_history(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $item = Item::create(['sku' => 'IMP-REPEAT', 'name' => 'Repeat', 'category_id' => 0]);
        ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);
        $warehouse = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        \App\Support\StockService::mutate([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'direction' => 'in',
            'qty' => 10,
            'source_type' => 'test',
            'source_id' => 99,
        ]);

        $this->importRows($user, [
            ['sku', 'name', 'small_warehouse_stock'],
            ['IMP-REPEAT', 'Repeat', 10],
        ])->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->assertSame(10, (int) ItemStock::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('stock'));
    }

    public function test_import_rejects_same_base_and_package_unit(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->importRows($user, [
            ['sku', 'name', 'base_unit', 'package_unit', 'package_conversion_qty'],
            ['IMP-UNIT', 'Invalid Unit', 'PCS', 'PCS', 12],
        ])->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->assertDatabaseMissing('items', ['sku' => 'IMP-UNIT']);
    }

    private function importRows(User $user, array $rows)
    {
        $path = tempnam(sys_get_temp_dir(), 'items-import-test-').'.xlsx';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        (new Xlsx($spreadsheet))->save($path);

        try {
            return $this->actingAs($user)
                ->post(route('admin.masterdata.items.import'), [
                    'file' => new UploadedFile(
                        $path,
                        'items-test.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ], ['Accept' => 'application/json']);
        } finally {
            @unlink($path);
        }
    }
}
