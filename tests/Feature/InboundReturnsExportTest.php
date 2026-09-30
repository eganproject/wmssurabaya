<?php

namespace Tests\Feature;

use App\Exports\InboundReturnsReportExport;
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

class InboundReturnsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_uses_active_filters_and_builds_return_analysis_sheets(): void
    {
        Carbon::setTestNow('2026-09-30 10:11:12');
        Excel::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $warehouse = Warehouse::query()->where('code', Warehouse::DEFAULT_CODE)->firstOrFail();
        $item = Item::create([
            'sku' => 'RET-EXPORT-001',
            'name' => 'Item Export Retur',
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
            'code' => 'INB-RET-EXPORT-001',
            'type' => 'return',
            'ref_no' => 'RESI-EXPORT-001',
            'transacted_at' => '2026-09-20 09:00:00',
            'note' => 'Data retur yang harus masuk laporan',
            'status' => 'finalized',
            'approved_at' => '2026-09-20 10:00:00',
            'finalized_at' => '2026-09-20 14:00:00',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'finalized_by' => $user->id,
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $included->id,
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'qty_input' => 10,
            'conversion_qty' => 1,
            'qty' => 10,
            'qty_received' => 8,
            'qty_good' => 6,
            'qty_damaged' => 2,
            'qty_missing' => 2,
        ]);

        $excluded = InboundTransaction::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'INB-RET-EXPORT-002',
            'type' => 'return',
            'ref_no' => 'RESI-EXPORT-002',
            'transacted_at' => '2026-09-21 09:00:00',
            'status' => 'approved',
            'approved_at' => now(),
            'created_by' => $user->id,
            'approved_by' => $user->id,
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
            ->get(route('admin.inbound.returns.export', [
                'q' => 'RET-EXPORT',
                'status' => 'finalized',
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]))
            ->assertOk();

        Excel::assertDownloaded(
            'laporan-retur-inbound-20260930-101112.xlsx',
            function (InboundReturnsReportExport $export) {
                $sheets = $export->sheets();

                $this->assertSame(
                    ['Ringkasan', 'Analisis SKU', 'Tren Harian', 'Tindak Lanjut', 'Detail Retur'],
                    array_map(fn ($sheet) => $sheet->title(), $sheets),
                );

                $summaryRows = $sheets[0]->array();
                $this->assertSame(1, $summaryRows[12][0]);
                $this->assertSame(1, $summaryRows[12][1]);
                $this->assertSame(4, $summaryRows[12][8]);
                $this->assertSame(10, $summaryRows[16][0]);
                $this->assertSame(8, $summaryRows[16][1]);
                $this->assertSame(2, $summaryRows[16][3]);
                $this->assertSame(2, $summaryRows[16][4]);

                $itemRows = $sheets[1]->collection();
                $this->assertCount(1, $itemRows);
                $this->assertSame('RET-EXPORT-001', $itemRows->first()[1]);
                $this->assertSame(10, $itemRows->first()[5]);
                $this->assertSame(2, $itemRows->first()[8]);
                $this->assertSame(2, $itemRows->first()[9]);

                $followUpRows = $sheets[3]->collection();
                $this->assertCount(1, $followUpRows);
                $this->assertSame('Selesai', $followUpRows->first()[0]);
                $this->assertSame('INB-RET-EXPORT-001', $followUpRows->first()[1]);

                $detailRows = $sheets[4]->collection();
                $this->assertCount(1, $detailRows);
                $this->assertSame('Finalisasi', $detailRows->first()[3]);
                $this->assertSame(10, $detailRows->first()[8]);
                $this->assertSame(8, $detailRows->first()[9]);
                $this->assertSame(2, $detailRows->first()[11]);
                $this->assertSame(2, $detailRows->first()[12]);

                return true;
            },
        );
    }
}
