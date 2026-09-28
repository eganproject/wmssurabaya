<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ItemKoliDimensionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_dimensions_default_to_null_and_cbm_is_computed_only_when_complete(): void
    {
        $item = Item::create(['sku' => 'DIM-NULL', 'name' => 'Tanpa Dimensi'])->refresh();

        $this->assertNull($item->koli_length_cm);
        $this->assertNull($item->cbm_per_koli);

        $item->update(['koli_length_cm' => 40, 'koli_width_cm' => 30]);
        $this->assertNull($item->refresh()->cbm_per_koli);

        $item->update(['koli_height_cm' => 25]);
        $this->assertSame(0.03, $item->refresh()->cbm_per_koli);
    }

    public function test_crud_stores_updates_and_clears_dimensions(): void
    {
        $this->actingAs($this->user)->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'DIM-001',
            'name' => 'Item Dimensi',
            'category_id' => 0,
            'is_bundle' => false,
            'koli_length_cm' => '40.5',
            'koli_width_cm' => '30',
            'koli_height_cm' => '20',
        ])->assertOk();

        $item = Item::where('sku', 'DIM-001')->firstOrFail();
        $this->assertSame(40.5, $item->koli_length_cm);
        $this->assertSame(0.0243, $item->cbm_per_koli);

        $this->actingAs($this->user)
            ->getJson(route('admin.masterdata.items.show', $item))
            ->assertOk()
            ->assertJsonPath('koli_length_cm', 40.5)
            ->assertJsonPath('cbm_per_koli', 0.0243);

        $this->actingAs($this->user)
            ->getJson(route('admin.masterdata.items.data', ['length' => -1]))
            ->assertOk()
            ->assertJsonPath('data.0.koli_height_cm', 20)
            ->assertJsonPath('data.0.cbm_per_koli', 0.0243);

        // Form mengirim input kosong sebagai string kosong → disimpan null.
        $this->actingAs($this->user)->putJson(route('admin.masterdata.items.update', $item), [
            'sku' => $item->sku,
            'name' => $item->name,
            'category_id' => 0,
            'is_bundle' => false,
            'koli_length_cm' => '',
            'koli_width_cm' => '',
            'koli_height_cm' => '',
        ])->assertOk();

        $item->refresh();
        $this->assertNull($item->koli_length_cm);
        $this->assertNull($item->cbm_per_koli);
    }

    public function test_update_without_dimension_fields_keeps_existing_dimensions(): void
    {
        $item = Item::create(['sku' => 'DIM-KEEP', 'name' => 'Lama', 'koli_length_cm' => 10, 'koli_width_cm' => 20, 'koli_height_cm' => 30]);

        $this->actingAs($this->user)->putJson(route('admin.masterdata.items.update', $item), [
            'sku' => $item->sku,
            'name' => 'Baru',
            'category_id' => 0,
            'is_bundle' => false,
        ])->assertOk();

        $item->refresh();
        $this->assertSame('Baru', $item->name);
        $this->assertSame(0.006, $item->cbm_per_koli);
    }

    public function test_crud_rejects_invalid_dimensions(): void
    {
        $payload = ['sku' => 'DIM-BAD', 'name' => 'Salah', 'category_id' => 0, 'is_bundle' => false];

        foreach ([['koli_length_cm' => 0], ['koli_width_cm' => -5], ['koli_height_cm' => 'abc'], ['koli_length_cm' => 10.123]] as $invalid) {
            $this->actingAs($this->user)
                ->postJson(route('admin.masterdata.items.store'), $payload + $invalid)
                ->assertStatus(422)
                ->assertJsonValidationErrors(array_keys($invalid));
        }

        $this->assertDatabaseMissing('items', ['sku' => 'DIM-BAD']);
    }

    public function test_items_import_sets_dimensions_and_keeps_them_when_columns_absent(): void
    {
        $this->importItems([
            ['sku', 'name', 'koli_length_cm', 'koli_width_cm', 'koli_height_cm'],
            ['IMP-DIM', 'Import Dimensi', '40,5', 30, ''],
        ])->assertOk();

        $item = Item::where('sku', 'IMP-DIM')->firstOrFail();
        $this->assertSame(40.5, $item->koli_length_cm);
        $this->assertSame(30.0, $item->koli_width_cm);
        $this->assertNull($item->koli_height_cm);

        $this->importItems([
            ['sku', 'name'],
            ['IMP-DIM', 'Nama Baru'],
        ])->assertOk();

        $item->refresh();
        $this->assertSame('Nama Baru', $item->name);
        $this->assertSame(40.5, $item->koli_length_cm);
        $this->assertSame(30.0, $item->koli_width_cm);
    }

    public function test_items_import_rejects_invalid_dimension(): void
    {
        $this->importItems([
            ['sku', 'name', 'koli_length_cm'],
            ['IMP-DIM-BAD', 'Salah', 'empat puluh'],
        ])->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->assertDatabaseMissing('items', ['sku' => 'IMP-DIM-BAD']);
    }

    public function test_items_template_contains_dimension_columns(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.masterdata.items.template'))->assertOk();
        $headers = IOFactory::load($response->getFile()->getPathname())->getActiveSheet()->toArray()[0];

        $this->assertContains('koli_length_cm', $headers);
        $this->assertContains('koli_width_cm', $headers);
        $this->assertContains('koli_height_cm', $headers);
    }

    public function test_bulk_update_template_and_import_handle_dimensions(): void
    {
        $item = Item::create(['sku' => 'BULK-DIM', 'name' => 'Bulk Dimensi', 'koli_length_cm' => 40, 'koli_width_cm' => 30, 'koli_height_cm' => 25]);
        $fields = ['koli_length_cm', 'koli_width_cm', 'koli_height_cm'];

        $response = $this->actingAs($this->user)->get(route('admin.masterdata.items.bulk-update.template', [
            'fields' => $fields,
            'prefill' => 'all',
        ]))->assertOk();
        $rows = IOFactory::load($response->getFile()->getPathname())->getSheet(0)->toArray();
        $this->assertSame(['sku', 'reference_name', ...$fields], $rows[0]);
        $this->assertEquals(['BULK-DIM', 'Bulk Dimensi', 40, 30, 25], $rows[1]);

        $this->importBulk($fields, [
            ['sku', ...$fields],
            ['BULK-DIM', 50, '35,5', ''],
        ])->assertOk()->assertJsonPath('updated', 1);

        $item->refresh();
        $this->assertSame(50.0, $item->koli_length_cm);
        $this->assertSame(35.5, $item->koli_width_cm);
        $this->assertNull($item->koli_height_cm);

        $this->importBulk($fields, [
            ['sku', ...$fields],
            ['BULK-DIM', 0, 10, 10],
        ])->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->assertSame(50.0, $item->refresh()->koli_length_cm);
    }

    private function importItems(array $rows)
    {
        return $this->uploadXlsx(route('admin.masterdata.items.import'), $rows);
    }

    private function importBulk(array $fields, array $rows)
    {
        return $this->uploadXlsx(route('admin.masterdata.items.bulk-update.import'), $rows, ['fields' => $fields]);
    }

    private function uploadXlsx(string $url, array $rows, array $data = [])
    {
        $path = tempnam(sys_get_temp_dir(), 'items-dim-').'.xlsx';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
        (new Xlsx($spreadsheet))->save($path);

        try {
            return $this->actingAs($this->user)->post($url, $data + [
                'file' => new UploadedFile($path, 'items.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ], ['Accept' => 'application/json']);
        } finally {
            @unlink($path);
        }
    }
}
