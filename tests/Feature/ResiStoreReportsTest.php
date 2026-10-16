<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\PackerResiScan;
use App\Models\PackerScanOut;
use App\Models\QcScanResi;
use App\Models\QcScanResiItem;
use App\Models\Resi;
use App\Models\StockMutation;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResiStoreReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    private function resi(string $code, ?Store $store, array $extra = []): Resi
    {
        return Resi::create(array_merge([
            'id_pesanan' => 'ORDER-'.$code,
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-'.$code,
            'store_id' => $store?->id,
            'uploader_id' => $this->user->id,
        ], $extra));
    }

    public function test_qc_transit_shows_and_filters_by_store(): void
    {
        $tokoA = Store::create(['name' => 'Toko Alpha']);
        $tokoB = Store::create(['name' => 'Toko Beta']);

        foreach ([['A1', $tokoA], ['B1', $tokoB], ['N1', null]] as [$code, $store]) {
            $qc = QcScanResi::create([
                'resi_id' => $this->resi($code, $store)->id,
                'status' => 'in_progress',
                'scanned_at' => now(),
                'scanned_by' => $this->user->id,
            ]);
            QcScanResiItem::create(['qc_scan_resi_id' => $qc->id, 'sku' => 'SKU-'.$code, 'required_qty' => 2, 'scanned_qty' => 1]);
        }

        $response = $this->actingAs($this->user)
            ->getJson(route('admin.inventory.picker-transit.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 3);
        $this->assertEqualsCanonicalizing(
            ['Toko Alpha', 'Toko Beta', '-'],
            collect($response->json('data'))->pluck('store_name')->all(),
        );

        $this->actingAs($this->user)
            ->getJson(route('admin.inventory.picker-transit.data', ['store_id' => $tokoB->id]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.no_resi', 'RESI-B1')
            ->assertJsonPath('data.0.store_name', 'Toko Beta');

        $this->actingAs($this->user)
            ->getJson(route('admin.inventory.picker-transit.data', ['store_id' => 'none']))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.no_resi', 'RESI-N1');

        $this->actingAs($this->user)
            ->getJson(route('admin.inventory.picker-transit.data', ['q' => 'Alpha']))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.no_resi', 'RESI-A1');

        $this->actingAs($this->user)
            ->get(route('admin.inventory.picker-transit.index'))
            ->assertOk()
            ->assertSee('id="picker_filter_store"', false)
            ->assertSee('Toko Alpha');
    }

    public function test_scan_out_report_breaks_down_import_vs_scan_out_per_store(): void
    {
        $tokoA = Store::create(['name' => 'Toko Alpha']);
        $tokoB = Store::create(['name' => 'Toko Beta']);

        $scanned = $this->resi('A1', $tokoA);
        $this->resi('A2', $tokoA);
        $this->resi('B1', $tokoB);
        $this->resi('B2', $tokoB, ['status' => 'canceled']);

        PackerScanOut::create([
            'resi_id' => $scanned->id,
            'scan_type' => 'resi',
            'scan_code' => 'RESI-A1',
            'scan_date' => now()->toDateString(),
            'scanned_at' => now(),
            'scanned_by' => $this->user->id,
        ]);

        $params = ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()];

        $response = $this->actingAs($this->user)
            ->getJson(route('admin.reports.packer-reports.data', $params))
            ->assertOk()
            ->assertJsonPath('comparison.import_total', 3)
            ->assertJsonPath('comparison.missing_total', 2);

        $perStore = collect($response->json('comparison.per_store'))->keyBy('store_name');
        $this->assertSame(2, $perStore['Toko Alpha']['import_total']);
        $this->assertSame(1, $perStore['Toko Alpha']['scanned_total']);
        $this->assertSame(1, $perStore['Toko Alpha']['missing_total']);
        $this->assertSame(1, $perStore['Toko Beta']['import_total']);
        $this->assertSame(0, $perStore['Toko Beta']['scanned_total']);
        $this->assertEqualsCanonicalizing(
            ['Toko Alpha', 'Toko Beta'],
            collect($response->json('comparison.missing_samples'))->pluck('store_name')->all(),
        );

        $this->actingAs($this->user)
            ->getJson(route('admin.reports.packer-reports.data', $params + ['store_id' => $tokoB->id]))
            ->assertOk()
            ->assertJsonPath('comparison.import_total', 1)
            ->assertJsonPath('comparison.scanned_total', 0)
            ->assertJsonPath('comparison.canceled_total', 1)
            ->assertJsonCount(0, 'data');

        $this->actingAs($this->user)
            ->getJson(route('admin.reports.packer-reports.data', ['date_from' => now()->toDateString(), 'store_id' => $tokoA->id]))
            ->assertOk()
            ->assertJsonPath('data.0.total_scan', 1);

        $this->actingAs($this->user)
            ->get(route('admin.reports.packer-reports.index'))
            ->assertOk()
            ->assertSee('id="filter_store"', false)
            ->assertSee('Rekap per Toko');
    }

    public function test_packing_report_shows_store_in_detail_search_and_breakdown(): void
    {
        $tokoA = Store::create(['name' => 'Toko Alpha']);
        $packed = $this->resi('A1', $tokoA);
        $this->resi('N1', null);

        PackerResiScan::create([
            'resi_id' => $packed->id,
            'scan_type' => 'resi',
            'scan_code' => 'RESI-A1',
            'scan_date' => now()->toDateString(),
            'scanned_at' => now(),
            'scanned_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('admin.reports.packer-packing-reports.data', [
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('comparison.missing_samples.0.no_resi', 'RESI-N1')
            ->assertJsonPath('comparison.missing_samples.0.store_name', '-');
        $perStore = collect($response->json('comparison.per_store'))->keyBy('store_name');
        $this->assertSame(1, $perStore['Toko Alpha']['scanned_total']);
        $this->assertSame(1, $perStore['Tanpa Toko']['missing_total']);

        $this->actingAs($this->user)
            ->getJson(route('admin.reports.packer-packing-reports.detail', [
                'packer_id' => $this->user->id,
                'date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.store_name', 'Toko Alpha');

        $this->actingAs($this->user)
            ->getJson(route('admin.reports.packer-packing-reports.search-resi', ['q' => 'RESI-A1']))
            ->assertOk()
            ->assertJsonPath('data.store_name', 'Toko Alpha');
    }

    public function test_stock_movement_can_be_classified_per_store(): void
    {
        $small = Warehouse::where('code', Warehouse::DEFAULT_CODE)->firstOrFail();
        $tokoA = Store::create(['name' => 'Toko Alpha']);
        $tokoB = Store::create(['name' => 'Toko Beta']);

        $items = collect(['FAST', 'MID', 'SLOW'])->mapWithKeys(function (string $sku) {
            $item = Item::create(['sku' => 'STORE-'.$sku, 'name' => 'Item '.$sku, 'category_id' => null]);
            $unit = ItemUnit::create(['item_id' => $item->id, 'name' => 'PCS', 'conversion_qty' => 1, 'is_base' => true]);

            return [$sku => [$item, $unit]];
        });

        $sequence = 0;
        $resiOut = function (?Store $store, string $sku, int $qty) use ($small, $items, &$sequence): void {
            $sequence++;
            [$item, $unit] = $items[$sku];
            $qc = QcScanResi::create([
                'resi_id' => $this->resi('MOVE'.$sequence, $store)->id,
                'status' => 'completed',
                'scanned_at' => now()->subDay(),
                'scanned_by' => $this->user->id,
                'completed_at' => now()->subDay(),
                'completed_by' => $this->user->id,
            ]);
            StockMutation::create([
                'warehouse_id' => $small->id, 'item_id' => $item->id, 'unit_id' => $unit->id,
                'direction' => 'out', 'qty' => $qty, 'qty_input' => $qty, 'conversion_qty' => 1,
                'stock_before' => 500, 'stock_after' => 500 - $qty,
                'source_type' => 'qc_resi', 'source_subtype' => 'scan', 'source_id' => $qc->id,
                'occurred_at' => now()->subDay(), 'created_by' => $this->user->id,
            ]);
        };

        // Toko Alpha: FAST 70, MID 20, SLOW 10 -> fast / medium / slow.
        $resiOut($tokoA, 'FAST', 70);
        $resiOut($tokoA, 'MID', 20);
        $resiOut($tokoA, 'SLOW', 10);
        // Toko Beta only sells SLOW, so there it is the fast mover.
        $resiOut($tokoB, 'SLOW', 40);

        // Manual outbound has no toko: counted in the overall report, never per toko.
        StockMutation::create([
            'warehouse_id' => $small->id, 'item_id' => $items['MID'][0]->id, 'unit_id' => $items['MID'][1]->id,
            'direction' => 'out', 'qty' => 500, 'qty_input' => 500, 'conversion_qty' => 1,
            'stock_before' => 500, 'stock_after' => 0,
            'source_type' => 'outbound', 'source_subtype' => 'manual', 'source_id' => 99001,
            'occurred_at' => now()->subDay(), 'created_by' => $this->user->id,
        ]);

        $params = [
            'draw' => 1, 'start' => 0, 'length' => 10,
            'date_from' => now()->subDays(29)->toDateString(),
            'date_to' => now()->toDateString(),
        ];

        $overall = $this->actingAs($this->user)
            ->getJson(route('admin.reports.stock.movement-data', $params))
            ->assertOk()
            ->assertJsonPath('summary.total_outbound_qty', 640)
            ->assertJsonPath('summary.outbound_source', 'Outbound manual + import resi selesai');
        $this->assertSame('fast', collect($overall->json('data'))->firstWhere('sku', 'STORE-MID')['movement_key']);

        $alpha = $this->actingAs($this->user)
            ->getJson(route('admin.reports.stock.movement-data', $params + ['store_id' => $tokoA->id]))
            ->assertOk()
            ->assertJsonPath('summary.total_outbound_qty', 100)
            ->assertJsonPath('summary.store', 'Toko Alpha')
            ->assertJsonPath('summary.fast_sku', 1)
            ->assertJsonPath('summary.medium_sku', 1)
            ->assertJsonPath('summary.slow_sku', 1);
        $classes = collect($alpha->json('data'))->pluck('movement_key', 'sku');
        $this->assertSame('fast', $classes['STORE-FAST']);
        $this->assertSame('medium', $classes['STORE-MID']);
        $this->assertSame('slow', $classes['STORE-SLOW']);
        $this->assertSame(100, collect($alpha->json('trend'))->sum('quantity'));

        $breakdown = $this->actingAs($this->user)
            ->getJson(route('admin.reports.stock.movement-store-data', $params))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.store_name', 'Toko Alpha')
            ->assertJsonPath('data.0.total_outbound_qty', 100)
            ->assertJsonPath('data.0.fast_sku', 1)
            ->assertJsonPath('data.0.medium_sku', 1)
            ->assertJsonPath('data.0.slow_sku', 1)
            ->assertJsonPath('data.0.top_fast.0.sku', 'STORE-FAST')
            ->assertJsonPath('data.1.store_name', 'Toko Beta')
            ->assertJsonPath('data.1.fast_sku', 1)
            ->assertJsonPath('data.1.top_fast.0.sku', 'STORE-SLOW');
        $this->assertArrayNotHasKey('items', $breakdown->json('data.0'));

        $this->actingAs($this->user)
            ->get(route('admin.reports.stock.movement-export', $params + ['store_id' => $tokoA->id]))
            ->assertOk();

        $this->actingAs($this->user)
            ->get(route('admin.reports.stock.index'))
            ->assertOk()
            ->assertSee('id="movement_store"', false)
            ->assertSee('Fast / Medium / Slow Moving per Toko');
    }
}
