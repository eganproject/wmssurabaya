<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\QcScanResi;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QcScanFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_resis_table_has_lookup_indexes(): void
    {
        $this->assertTrue(Schema::hasIndex('resis', 'resis_no_resi_index'));
        $this->assertTrue(Schema::hasIndex('resis', 'resis_id_pesanan_index'));
    }

    public function test_scan_flow_returns_only_todays_resis_in_summary(): void
    {
        $user = User::create(['name' => 'QC', 'email' => 'qc@example.test', 'password' => 'password']);
        $warehouse = Warehouse::findOrFail(Warehouse::defaultId());
        $item = Item::create(['sku' => 'QC-SKU-1', 'name' => 'QC Item']);
        ItemStock::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'stock' => 10]);
        DB::table('picking_lists')->insert([
            'list_date' => now()->toDateString(),
            'sku' => 'QC-SKU-1',
            'qty' => 5,
            'remaining_qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Resi QC kemarin (dan kemarin pukul 23:59) tidak boleh ikut ringkasan hari ini.
        $yesterday = $this->makeResi($user, 'OLD-1');
        QcScanResi::create([
            'resi_id' => $yesterday->id,
            'status' => 'completed',
            'scanned_at' => now()->startOfDay()->subSecond(),
            'scanned_by' => $user->id,
        ]);

        $resi = $this->makeResi($user, 'NEW-1');
        ResiDetail::create(['resi_id' => $resi->id, 'sku' => 'QC-SKU-1', 'qty' => 2]);

        $this->actingAs($user)
            ->getJson(route('qc.resi-lookup', ['type' => 'no_resi', 'code' => 'NEW-1']))
            ->assertOk()
            ->assertJsonPath('resi.id', $resi->id);

        $this->actingAs($user)
            ->postJson(route('qc.resi-record'), ['resi_id' => $resi->id])
            ->assertOk()
            ->assertJsonCount(1, 'session.resis');

        $requestId = 'req-1';
        $response = $this->actingAs($user)->postJson(route('qc.scan-item'), [
            'code' => 'QC-SKU-1', 'qty' => 1, 'resi_id' => $resi->id, 'request_id' => $requestId,
        ]);
        $response->assertOk()
            ->assertJsonCount(1, 'session.resis')
            ->assertJsonPath('session.resis.0.no_resi', 'NEW-1')
            ->assertJsonPath('session.resis.0.scanned_qty', 1);

        // Request ulang dengan request_id sama: tidak dobel potong stok, ringkasan tetap dikirim.
        $this->actingAs($user)->postJson(route('qc.scan-item'), [
            'code' => 'QC-SKU-1', 'qty' => 1, 'resi_id' => $resi->id, 'request_id' => $requestId,
        ])->assertOk()->assertJsonPath('session.resis.0.scanned_qty', 1);

        $this->assertSame(9, (int) ItemStock::where('item_id', $item->id)->value('stock'));
    }

    private function makeResi(User $user, string $noResi): Resi
    {
        return Resi::create([
            'id_pesanan' => 'ORDER-'.$noResi,
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => $noResi,
            'uploader_id' => $user->id,
        ]);
    }
}
