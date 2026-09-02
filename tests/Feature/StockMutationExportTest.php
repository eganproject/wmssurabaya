<?php

namespace Tests\Feature;

use App\Exports\StockMutationReportExport;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class StockMutationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_mutation_export_uses_active_filters_and_contains_complete_report_sheets(): void
    {
        Carbon::setTestNow('2026-09-02 10:11:12');
        Excel::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $warehouse = Warehouse::query()->where('code', Warehouse::DEFAULT_CODE)->firstOrFail();
        $targetItem = Item::create([
            'sku' => 'MUT-EXPORT-001',
            'name' => 'Item Laporan Mutasi',
            'category_id' => null,
        ]);
        $otherItem = Item::create([
            'sku' => 'MUT-OTHER-001',
            'name' => 'Item Lain',
            'category_id' => null,
        ]);
        $unit = ItemUnit::create([
            'item_id' => $targetItem->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);
        $otherUnit = ItemUnit::create([
            'item_id' => $otherItem->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);

        $included = StockMutation::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $targetItem->id,
            'unit_id' => $unit->id,
            'direction' => 'in',
            'qty' => 12,
            'qty_input' => 12,
            'conversion_qty' => 1,
            'stock_before' => 8,
            'stock_after' => 20,
            'source_type' => 'inbound',
            'source_subtype' => 'receipt',
            'source_id' => 1001,
            'source_code' => 'IN-EXPORT-001',
            'note' => 'Data yang harus masuk laporan',
            'occurred_at' => '2026-08-20 09:30:00',
            'created_by' => $user->id,
        ]);
        StockMutation::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $otherItem->id,
            'unit_id' => $otherUnit->id,
            'direction' => 'out',
            'qty' => 5,
            'qty_input' => 5,
            'conversion_qty' => 1,
            'stock_before' => 10,
            'stock_after' => 5,
            'source_type' => 'outbound',
            'source_id' => 1002,
            'source_code' => 'OUT-OTHER-001',
            'occurred_at' => '2026-08-20 10:00:00',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.inventory.stock-mutations.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'q' => 'MUT-EXPORT',
                'warehouse_id' => $warehouse->id,
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-31',
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.source_code', 'IN-EXPORT-001');

        $this->actingAs($user)
            ->get(route('admin.inventory.stock-mutations.export', [
                'q' => 'MUT-EXPORT',
                'warehouse_id' => $warehouse->id,
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-31',
            ]))
            ->assertOk();

        Excel::assertDownloaded('laporan-mutasi-stok-20260902-101112.xlsx', function (StockMutationReportExport $export) use ($included) {
            $sheets = $export->sheets();

            $this->assertSame(
                ['Ringkasan', 'Rekap Harian', 'Rekap per Item', 'Detail Mutasi'],
                array_map(fn ($sheet) => $sheet->title(), $sheets),
            );

            $detailRows = $sheets[3]->collection();
            $this->assertCount(1, $detailRows);
            $this->assertSame('MUT-'.str_pad((string) $included->id, 7, '0', STR_PAD_LEFT), $detailRows->first()[1]);
            $this->assertSame('MUT-EXPORT-001', $detailRows->first()[4]);
            $this->assertSame(12, $detailRows->first()[10]);
            $this->assertSame(20, $detailRows->first()[14]);
            $this->assertSame('IN-EXPORT-001', $detailRows->first()[18]);

            $itemRows = $sheets[2]->collection();
            $this->assertCount(1, $itemRows);
            $this->assertSame(12, $itemRows->first()[5]);
            $this->assertSame('OK', $itemRows->first()[13]);

            return true;
        });
    }
}
