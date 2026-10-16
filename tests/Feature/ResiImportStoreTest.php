<?php

namespace Tests\Feature;

use App\Models\Resi;
use App\Models\ResiImportBatchItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ResiImportStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    private function importFile(array $rows, array $extra = [])
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'resi-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $this->actingAs($this->user)->postJson(route('admin.inventory.resi-import.import'), array_merge([
            'file' => new UploadedFile($path, 'resi.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ], $extra));
    }

    public function test_import_reads_toko_column_case_insensitively_and_falls_back_to_form_store(): void
    {
        $alpha = Store::create(['name' => 'Toko Alpha']);
        $beta = Store::create(['name' => 'Toko Beta']);
        $date = now()->toDateString();

        $this->importFile([
            ['ID Pesanan', 'AWB/No. Tracking', 'Kurir', 'SKU', 'Jumlah', 'Tanggal Pembuatan', 'Toko'],
            ['ORD-1', 'RESI-1', 'JNE', 'SKU-A', 1, $date, 'toko alpha'],
            ['ORD-1', 'RESI-1', 'JNE', 'SKU-B', 2, $date, ''],
            ['ORD-2', 'RESI-2', 'JNE', 'SKU-C', 1, $date, ''],
        ], ['store_id' => $beta->id])->assertOk();

        $this->assertSame($alpha->id, Resi::where('id_pesanan', 'ORD-1')->value('store_id'));
        $this->assertSame($beta->id, Resi::where('id_pesanan', 'ORD-2')->value('store_id'));
        $this->assertSame('Toko Alpha', ResiImportBatchItem::where('id_pesanan', 'ORD-1')->first()->snapshot['toko']);

        $this->actingAs($this->user)
            ->getJson(route('admin.inventory.resi-import.data', ['date' => $date, 'store_id' => $alpha->id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.id_pesanan', 'ORD-1')
            ->assertJsonPath('data.0.toko', 'Toko Alpha');

        $this->actingAs($this->user)
            ->getJson(route('admin.inventory.resi-import.data', ['date' => $date, 'q' => 'Beta']))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.id_pesanan', 'ORD-2');
    }

    public function test_reimport_without_toko_keeps_existing_store_and_unknown_toko_is_rejected(): void
    {
        $alpha = Store::create(['name' => 'Toko Alpha']);
        $date = now()->toDateString();
        $header = ['ID Pesanan', 'SKU', 'Jumlah', 'Tanggal Pembuatan'];

        Resi::create([
            'id_pesanan' => 'ORD-1',
            'tanggal_pesanan' => $date,
            'tanggal_upload' => now()->subDay()->toDateString(),
            'store_id' => $alpha->id,
            'uploader_id' => $this->user->id,
        ]);
        $this->importFile([$header, ['ORD-1', 'SKU-A', 3, $date]])->assertOk();
        $this->assertSame($alpha->id, Resi::where('id_pesanan', 'ORD-1')->value('store_id'));

        $this->importFile([
            [...$header, 'Toko'],
            ['ORD-9', 'SKU-A', 1, $date, 'Toko Salah'],
        ])->assertStatus(422)->assertJsonValidationErrors('file');
        $this->assertFalse(Resi::where('id_pesanan', 'ORD-9')->exists());

        $this->actingAs($this->user)
            ->get(route('admin.inventory.resi-import.index'))
            ->assertOk()
            ->assertSee('id="import_resi_store"', false)
            ->assertSee('id="filter_store"', false);
    }
}
