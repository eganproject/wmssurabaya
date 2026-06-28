<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PackerResiScan;
use App\Models\PackerScanException;
use App\Models\PackerScanOut;
use App\Models\QcScanResi;
use App\Models\QcScanResiItem;
use App\Models\QcTransitItem;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackerScanOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_out_matches_sku_case_insensitively_and_reduces_qc_transit(): void
    {
        $user = User::create([
            'name' => 'Scanner',
            'email' => 'scanner@example.test',
            'password' => 'password',
        ]);

        $item = Item::create([
            'sku' => 'ABC-001',
            'name' => 'Sample Item',
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORDER-001',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'TRACK-001',
            'uploader_id' => $user->id,
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => 'abc-001',
            'qty' => 2,
        ]);

        QcScanResi::create([
            'resi_id' => $resi->id,
            'status' => 'completed',
            'scanned_by' => $user->id,
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);

        $transit = QcTransitItem::create([
            'item_id' => $item->id,
            'transit_date' => now()->toDateString(),
            'qty' => 3,
            'remaining_qty' => 3,
            'last_qc_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('picker.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => 'TRACK-001',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Scan out berhasil.');

        $this->assertDatabaseHas('packer_scan_outs', [
            'resi_id' => $resi->id,
            'scan_code' => 'TRACK-001',
        ]);
        $this->assertSame(1, $transit->fresh()->remaining_qty);
    }

    public function test_cancel_resi_after_qc_and_scan_out_rolls_back_stock_and_related_process_data(): void
    {
        $user = User::create([
            'name' => 'Admin Scanner',
            'email' => 'admin-scanner@example.test',
            'password' => 'password',
        ]);

        $warehouse = Warehouse::create([
            'code' => Warehouse::DEFAULT_CODE,
            'name' => 'Gudang Kecil',
            'type' => Warehouse::TYPE_FULFILLMENT,
            'is_active' => true,
            'is_default' => true,
        ]);

        $item = Item::create([
            'sku' => 'CANCEL-QC-001',
            'name' => 'Cancel QC Item',
        ]);

        ItemStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'stock' => 10,
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORDER-CANCEL-QC',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'TRACK-CANCEL-QC',
            'uploader_id' => $user->id,
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => 'CANCEL-QC-001',
            'qty' => 2,
        ]);

        $qcResi = QcScanResi::create([
            'resi_id' => $resi->id,
            'status' => 'completed',
            'scanned_at' => now(),
            'scanned_by' => $user->id,
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);

        QcScanResiItem::create([
            'qc_scan_resi_id' => $qcResi->id,
            'item_id' => $item->id,
            'sku' => 'CANCEL-QC-001',
            'required_qty' => 2,
            'scanned_qty' => 2,
        ]);

        $olderTransit = QcTransitItem::create([
            'item_id' => $item->id,
            'transit_date' => now()->subDay()->toDateString(),
            'qty' => 2,
            'remaining_qty' => 0,
            'last_qc_at' => now()->subDay(),
        ]);

        QcTransitItem::create([
            'item_id' => $item->id,
            'transit_date' => now()->toDateString(),
            'qty' => 2,
            'remaining_qty' => 0,
            'last_qc_at' => now(),
        ]);

        StockService::mutate([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'direction' => 'out',
            'qty' => 2,
            'source_type' => 'qc_resi',
            'source_subtype' => 'scan',
            'source_id' => $qcResi->id,
            'source_code' => $resi->no_resi,
            'created_by' => $user->id,
        ]);

        PackerScanOut::create([
            'resi_id' => $resi->id,
            'scan_type' => 'no_resi',
            'scan_code' => $resi->no_resi,
            'scan_date' => now()->toDateString(),
            'scanned_at' => now(),
            'scanned_by' => $user->id,
        ]);

        $this->assertSame(8, (int) ItemStock::where('item_id', $item->id)->value('stock'));

        $this->actingAs($user)
            ->postJson(route('admin.inventory.resi-import.cancel'), [
                'no_resi' => $resi->no_resi,
                'reason' => 'Paket dibatalkan setelah scan',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Resi berhasil dibatalkan.');

        $this->assertSame('canceled', $resi->fresh()->status);
        $this->assertSame(10, (int) ItemStock::where('item_id', $item->id)->value('stock'));
        $this->assertFalse(PackerScanOut::where('resi_id', $resi->id)->exists());
        $this->assertFalse(QcScanResi::where('resi_id', $resi->id)->exists());
        $this->assertSame(2, (int) $olderTransit->fresh()->remaining_qty);
        $this->assertFalse(QcTransitItem::where('item_id', $item->id)->whereDate('transit_date', now()->toDateString())->exists());
        $this->assertFalse(StockMutation::where('source_type', 'qc_resi')->where('source_id', $qcResi->id)->exists());
    }

    public function test_scan_out_rejects_resi_before_completed_qc_scan(): void
    {
        $user = User::create([
            'name' => 'Scanner',
            'email' => 'scanner2@example.test',
            'password' => 'password',
        ]);

        Item::create([
            'sku' => 'SKU-002',
            'name' => 'Sample Item 2',
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORDER-002',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'TRACK-002',
            'uploader_id' => $user->id,
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => 'SKU-002',
            'qty' => 1,
        ]);

        $this->actingAs($user)
            ->postJson(route('picker.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => 'TRACK-002',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Resi belum selesai QC scan resi.');

        $this->assertFalse(PackerScanOut::where('resi_id', $resi->id)->exists());
    }

    public function test_scan_out_rejects_legacy_packer_scan_when_qc_is_not_completed(): void
    {
        $user = User::create([
            'name' => 'Scanner',
            'email' => 'legacy-scanner@example.test',
            'password' => 'password',
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORDER-LEGACY',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'TRACK-LEGACY',
            'uploader_id' => $user->id,
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => 'SKU-LEGACY',
            'qty' => 1,
        ]);

        PackerResiScan::create([
            'resi_id' => $resi->id,
            'scan_type' => 'no_resi',
            'scan_code' => 'TRACK-LEGACY',
            'scan_date' => now()->toDateString(),
            'scanned_at' => now(),
            'scanned_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('picker.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => 'TRACK-LEGACY',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Resi belum selesai QC scan resi.');

        $this->assertFalse(PackerScanOut::where('resi_id', $resi->id)->exists());
    }

    public function test_scan_out_exception_sku_still_requires_completed_qc_scan(): void
    {
        $user = User::create([
            'name' => 'Scanner',
            'email' => 'exception-scanner@example.test',
            'password' => 'password',
        ]);

        PackerScanException::create([
            'sku' => 'SKU-EXCEPTION',
            'note' => 'No transit deduction',
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORDER-EXCEPTION',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'TRACK-EXCEPTION',
            'uploader_id' => $user->id,
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => 'SKU-EXCEPTION',
            'qty' => 1,
        ]);

        $this->actingAs($user)
            ->postJson(route('picker.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => 'TRACK-EXCEPTION',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Resi belum selesai QC scan resi.');

        QcScanResi::create([
            'resi_id' => $resi->id,
            'status' => 'completed',
            'scanned_by' => $user->id,
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('picker.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => 'TRACK-EXCEPTION',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Scan out berhasil.');
    }
}
