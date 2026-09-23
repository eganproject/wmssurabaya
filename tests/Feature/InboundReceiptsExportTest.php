<?php

namespace Tests\Feature;

use App\Exports\InboundReceiptsReportExport;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class InboundReceiptsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_uses_active_filters_and_builds_analysis_sheets(): void
    {
        Carbon::setTestNow('2026-09-23 10:11:12');
        Excel::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $warehouse = Warehouse::query()->where('code', Warehouse::DEFAULT_CODE)->firstOrFail();
        $item = Item::create([
            'sku' => 'RCV-EXPORT-001',
            'name' => 'Item Export Penerimaan',
            'category_id' => null,
        ]);
        $unit = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);

        $included = InboundTransaction::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'INB-RCV-EXPORT-001',
            'type' => 'receipt',
            'ref_no' => 'REF-EXPORT-001',
            'transacted_at' => '2026-09-20 09:00:00',
            'note' => 'Data yang harus masuk laporan',
            'status' => 'approved',
            'approved_at' => now(),
            'created_by' => $user->id,
            'approved_by' => $user->id,
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $included->id,
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'qty_input' => 24,
            'conversion_qty' => 1,
            'qty' => 24,
            'qty_received' => 24,
            'qty_good' => 24,
            'qty_damaged' => 0,
            'qty_missing' => 0,
        ]);

        $excluded = InboundTransaction::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'INB-RCV-EXPORT-002',
            'type' => 'receipt',
            'ref_no' => 'REF-EXPORT-002',
            'transacted_at' => '2026-09-21 09:00:00',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $excluded->id,
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'qty_input' => 99,
            'conversion_qty' => 1,
            'qty' => 99,
            'qty_received' => 99,
            'qty_good' => 99,
            'qty_damaged' => 0,
            'qty_missing' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('admin.inbound.receipts.export', [
                'q' => 'RCV-EXPORT',
                'status' => 'approved',
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]))
            ->assertOk();

        Excel::assertDownloaded(
            'laporan-penerimaan-barang-20260923-101112.xlsx',
            function (InboundReceiptsReportExport $export) {
                $sheets = $export->sheets();

                $this->assertSame(
                    ['Ringkasan', 'Rekap Gudang', 'Rekap SKU', 'Tren Harian', 'Detail Penerimaan'],
                    array_map(fn ($sheet) => $sheet->title(), $sheets),
                );

                $summaryRows = $sheets[0]->array();
                $this->assertSame(1, $summaryRows[12][0]);
                $this->assertSame(24, $summaryRows[12][7]);

                $itemRows = $sheets[2]->collection();
                $this->assertCount(1, $itemRows);
                $this->assertSame('RCV-EXPORT-001', $itemRows->first()[2]);
                $this->assertSame(24, $itemRows->first()[9]);

                $detailRows = $sheets[4]->collection();
                $this->assertCount(1, $detailRows);
                $this->assertSame('INB-RCV-EXPORT-001', $detailRows->first()[1]);
                $this->assertSame('Disetujui', $detailRows->first()[3]);
                $this->assertSame(24, $detailRows->first()[13]);

                return true;
            },
        );
    }
}
