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

    public function test_marketplace_export_with_merged_order_cells_and_nama_toko_header(): void
    {
        $store = Store::create(['name' => 'Media Gudang Acc']);

        $this->importFile([
            ['NO.', 'Tanggal Pembuatan', 'ID Pesanan', 'SKU', 'Jumlah', 'Kurir', 'AWB/No. Tracking', 'Metode Pengiriman', 'Catatan Pembeli', 'Nama Toko', 'Channel'],
            [1, '08-09-2026 20:33', '260908GP7169MR', 'TRIP1', 1, 'SPX Hemat', 'SPXID062325577529', '', '', 'Media Gudang Acc', 'Shopee'],
            [2, '10-09-2026 21:30', '260910NTAWE64P', 'PACK1', 4, 'SPX Hemat', 'SPXID066841516909', '', '', 'Media Gudang Acc', 'Shopee'],
            ['', '', '', 'PEMB8', 4, '', '', '', '', '', ''],
            [3, '04-10-2026 09:24', '261004PPAFCE6G', 'BILL1', 6, 'SPX Hemat', 'SPXID06678002573A', '', '', 'Media Gudang Acc', 'Shopee'],
            ['', '', '', '', 6, '', '', '', '', '', ''],
            ['', '', '', '', 3, '', '', '', '', '', ''],
        ])->assertOk()->assertJsonPath('resis', 3)->assertJsonPath('details', 4);

        $this->assertSame(15, (int) Resi::with('details')->where('id_pesanan', '261004PPAFCE6G')->firstOrFail()->details->sum('qty'));

        $resi = Resi::with('details')->where('id_pesanan', '260910NTAWE64P')->firstOrFail();
        $this->assertSame($store->id, $resi->store_id);
        $this->assertSame('SPXID066841516909', $resi->no_resi);
        $this->assertSame('2026-09-10', $resi->tanggal_pesanan->format('Y-m-d'));
        $this->assertEqualsCanonicalizing(['PACK1' => 4, 'PEMB8' => 4], $resi->details->pluck('qty', 'sku')->all());
    }

    public function test_reimport_without_toko_keeps_existing_store_and_new_toko_is_created(): void
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
            ['ORD-8', 'SKU-B', 1, $date, 'Toko Baru'],
            ['ORD-9', 'SKU-C', 1, $date, 'TOKO BARU'],
            ['ORD-10', 'SKU-D', 1, $date, 'toko alpha'],
        ])->assertOk()->assertJsonPath('created_stores', ['Toko Baru']);

        $this->assertSame(1, Store::where('name', 'Toko Baru')->count());
        $this->assertSame(2, Store::count());
        $newId = Store::where('name', 'Toko Baru')->value('id');
        $this->assertSame($newId, Resi::where('id_pesanan', 'ORD-8')->value('store_id'));
        $this->assertSame($newId, Resi::where('id_pesanan', 'ORD-9')->value('store_id'));
        $this->assertSame($alpha->id, Resi::where('id_pesanan', 'ORD-10')->value('store_id'));

        $this->actingAs($this->user)
            ->get(route('admin.inventory.resi-import.index'))
            ->assertOk()
            ->assertSee('id="import_resi_store"', false)
            ->assertSee('id="filter_store"', false);
    }
}
