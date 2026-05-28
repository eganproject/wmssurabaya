<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PackerResiScan;
use App\Models\PackerScanException;
use App\Models\PackerScanOut;
use App\Models\QcScanResi;
use App\Models\QcTransitItem;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\User;
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
