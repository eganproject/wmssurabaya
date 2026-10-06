<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\ItemWarehouseSetting;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ItemsBulkUpdateImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Item $item;
    private int $smallId;
    private int $largeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->smallId = (int) Warehouse::where('code', 'WH-SMALL')->value('id');
        $this->largeId = (int) Warehouse::where('code', 'WH-BULK')->value('id');
        $pcs = Uom::firstOrCreate(['code' => 'PCS'], ['name' => 'Pieces', 'is_active' => true]);
        $koli = Uom::firstOrCreate(['code' => 'KOLI'], ['name' => 'Koli', 'is_active' => true]);
        Uom::firstOrCreate(['code' => 'SET'], ['name' => 'Set', 'is_active' => true]);

        $this->item = Item::create([
            'sku' => 'BULK-001',
            'name' => 'Nama Lama',
            'procurement_source' => Item::PROCUREMENT_NANGGEWER,
            'description' => 'Deskripsi lama',
        ]);
        ItemUnit::create(['item_id' => $this->item->id, 'uom_id' => $pcs->id, 'name' => 'PCS', 'conversion_qty' => 1, 'is_base' => true]);
        ItemUnit::create(['item_id' => $this->item->id, 'uom_id' => $koli->id, 'name' => 'KOLI', 'conversion_qty' => 24, 'is_base' => false]);
        ItemWarehouseSetting::create(['item_id' => $this->item->id, 'warehouse_id' => $this->smallId, 'safety_stock' => 5, 'location' => 'A-01']);
    }

    public function test_template_contains_sku_reference_and_selected_fields_only(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.masterdata.items.bulk-update.template', [
            'fields' => ['small_warehouse_location', 'status', 'unknown_field'],
        ]));

        $response->assertStatus(302); // unknown field ditolak validasi

        $response = $this->actingAs($this->user)->get(route('admin.masterdata.items.bulk-update.template', [
            'fields' => ['small_warehouse_location', 'status'],
            'prefill' => 'all',
        ]));
        $response->assertOk();

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());
        $this->assertSame(['Update Item', 'Panduan', 'Referensi'], $spreadsheet->getSheetNames());

        $rows = $spreadsheet->getSheet(0)->toArray();
        // Urutan kolom mengikuti urutan baku, bukan urutan pilihan.
        $this->assertSame(['sku', 'reference_name', 'status', 'small_warehouse_location'], $rows[0]);
        $this->assertSame(['BULK-001', 'Nama Lama', 'aktif', 'A-01'], $rows[1]);
        $this->assertNotContains('package_conversion_qty', $rows[0]);
    }

    public function test_import_updates_only_selected_fields_and_never_touches_sku_or_koli(): void
    {
        $category = Category::create(['name' => 'Aksesoris']);

        $response = $this->importFile(
            ['name', 'category', 'status', 'base_unit', 'small_warehouse_location', 'large_warehouse_safety_stock'],
            [
                ['sku', 'name', 'category', 'status', 'base_unit', 'small_warehouse_location', 'large_warehouse_safety_stock', 'package_unit', 'package_conversion_qty', 'description'],
                ['bulk-001', 'Nama Baru', 'aksesoris', 'nonaktif', 'SET', 'B-02', 12, 'DUS', 99, 'harus diabaikan'],
            ]
        );

        $response->assertOk()->assertJsonPath('updated', 1)->assertJsonPath('unchanged', 0);

        $item = $this->item->fresh();
        $this->assertSame('BULK-001', $item->sku);
        $this->assertSame('Nama Baru', $item->name);
        $this->assertSame($category->id, (int) $item->category_id);
        $this->assertFalse($item->is_active);
        $this->assertSame('Deskripsi lama', $item->description);
        $this->assertSame(Item::PROCUREMENT_NANGGEWER, $item->procurement_source);

        $this->assertSame('SET', ItemUnit::where('item_id', $item->id)->where('is_base', true)->value('name'));
        $package = ItemUnit::where('item_id', $item->id)->where('is_base', false)->firstOrFail();
        $this->assertSame('KOLI', $package->name);
        $this->assertSame(24, $package->conversion_qty);

        $small = ItemWarehouseSetting::where('item_id', $item->id)->where('warehouse_id', $this->smallId)->firstOrFail();
        $this->assertSame('B-02', $small->location);
        $this->assertSame(5, $small->safety_stock);
        $large = ItemWarehouseSetting::where('item_id', $item->id)->where('warehouse_id', $this->largeId)->firstOrFail();
        $this->assertSame(12, $large->safety_stock);
        $this->assertNull($large->location);
    }

    public function test_rows_without_changes_are_counted_as_unchanged(): void
    {
        $this->importFile(['name', 'small_warehouse_location'], [
            ['sku', 'name', 'small_warehouse_location'],
            ['BULK-001', 'Nama Lama', 'A-01'],
        ])->assertOk()->assertJsonPath('updated', 0)->assertJsonPath('unchanged', 1);
    }

    public function test_invalid_rows_reject_whole_import_without_partial_changes(): void
    {
        Item::create(['sku' => 'BULK-002', 'name' => 'Item Dua']);

        $response = $this->importFile(['name', 'status', 'small_warehouse_safety_stock'], [
            ['sku', 'name', 'status', 'small_warehouse_safety_stock'],
            ['BULK-001', 'Nama Valid', 'aktif', 10],
            ['BULK-002', '', 'mungkin', -3],
            ['TIDAK-ADA', 'X', 'aktif', 1],
            ['BULK-001', 'Duplikat', 'aktif', 1],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['file']);
        $messages = implode("\n", $response->json('errors.file'));
        $this->assertStringContainsString('Baris 3 (SKU BULK-002): Nama item wajib diisi.', $messages);
        $this->assertStringContainsString('Status harus aktif atau nonaktif.', $messages);
        $this->assertStringContainsString('Safety stock harus angka bulat', $messages);
        $this->assertStringContainsString('Baris 4 (SKU TIDAK-ADA): SKU tidak terdaftar', $messages);
        $this->assertStringContainsString('Baris 5 (SKU BULK-001): SKU duplikat', $messages);

        $this->assertSame('Nama Lama', $this->item->fresh()->name);
        $this->assertSame(5, (int) ItemWarehouseSetting::where('item_id', $this->item->id)->where('warehouse_id', $this->smallId)->value('safety_stock'));
    }

    public function test_missing_selected_column_is_rejected(): void
    {
        $this->importFile(['name', 'description'], [
            ['sku', 'name'],
            ['BULK-001', 'Nama Baru'],
        ])->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->assertSame('Nama Lama', $this->item->fresh()->name);
    }

    public function test_base_unit_cannot_equal_package_unit(): void
    {
        $this->importFile(['base_unit'], [
            ['sku', 'base_unit'],
            ['BULK-001', 'KOLI'],
        ])->assertStatus(422);

        $this->assertSame('PCS', ItemUnit::where('item_id', $this->item->id)->where('is_base', true)->value('name'));
    }

    public function test_sale_status_template_contains_current_value_and_dropdown(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.masterdata.items.bulk-update.template', [
            'fields' => ['sale_status'], 'prefill' => 'all',
        ]))->assertOk();

        $sheet = IOFactory::load($response->getFile()->getPathname())->getSheet(0);
        $this->assertSame(['sku', 'reference_name', 'sale_status'], $sheet->toArray()[0]);
        $this->assertSame('Lanjut Jual', $sheet->getCell('C2')->getValue());
        $this->assertContains('"Lanjut Jual,Tidak Lanjut Jual"', array_map(
            fn ($validation) => $validation->getFormula1(), $sheet->getDataValidationCollection()
        ));
    }

    public function test_bulk_update_changes_sale_status_without_changing_active_status(): void
    {
        foreach (['Tidak Lanjut Jual' => 'tidak_lanjut_jual', 'Lanjut Jual' => 'lanjut_jual'] as $label => $value) {
            $this->importFile(['sale_status'], [
                ['sku', 'sale_status'], ['BULK-001', $label],
            ])->assertOk()->assertJsonPath('updated', 1);
            $this->assertSame($value, $this->item->refresh()->sale_status);
            $this->assertTrue($this->item->is_active);
        }
    }

    public function test_invalid_sale_status_rejects_entire_bulk_update(): void
    {
        $other = Item::create(['sku' => 'BULK-002', 'name' => 'Item Lain']);
        $this->importFile(['sale_status'], [
            ['sku', 'sale_status'], ['BULK-001', 'Tidak Lanjut Jual'], ['BULK-002', 'salah'],
        ])->assertStatus(422);
        $this->assertSame('lanjut_jual', $this->item->refresh()->sale_status);
        $this->assertSame('lanjut_jual', $other->refresh()->sale_status);
    }

    private function importFile(array $fields, array $rows)
    {
        $path = tempnam(sys_get_temp_dir(), 'items-bulk-').'.xlsx';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        (new Xlsx($spreadsheet))->save($path);

        try {
            return $this->actingAs($this->user)->post(route('admin.masterdata.items.bulk-update.import'), [
                'fields' => $fields,
                'file' => new UploadedFile($path, 'bulk.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ], ['Accept' => 'application/json']);
        } finally {
            @unlink($path);
        }
    }
}
