<?php

namespace Tests\Feature;

use App\Exports\StockMovementReportExport;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ItemUnit;
use App\Models\ItemWarehouseSetting;
use App\Models\QcScanResi;
use App\Models\Resi;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use ZipArchive;

class InventoryAnalyticsReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movement_report_classifies_operational_outbound_contribution(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $warehouse = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $quantities = [
            'MOVE-FAST' => 70,
            'MOVE-MEDIUM' => 20,
            'MOVE-SLOW' => 10,
            'MOVE-NONE' => 0,
        ];

        foreach ($quantities as $sku => $qty) {
            $item = Item::create(['sku' => $sku, 'name' => "Item {$sku}", 'category_id' => null]);
            $unit = ItemUnit::create([
                'item_id' => $item->id,
                'name' => 'PCS',
                'conversion_qty' => 1,
                'is_base' => true,
            ]);
            ItemStock::create([
                'warehouse_id' => $warehouse->id,
                'item_id' => $item->id,
                'stock' => 100,
            ]);

            if ($qty > 0) {
                StockMutation::create([
                    'warehouse_id' => $warehouse->id,
                    'item_id' => $item->id,
                    'unit_id' => $unit->id,
                    'direction' => 'out',
                    'qty' => $qty,
                    'qty_input' => $qty,
                    'conversion_qty' => 1,
                    'stock_before' => 100 + $qty,
                    'stock_after' => 100,
                    'source_type' => 'outbound',
                    'source_subtype' => 'manual',
                    'source_id' => 8000 + $item->id,
                    'occurred_at' => now()->subDay(),
                    'created_by' => $user->id,
                ]);
            }
        }

        $response = $this->actingAs($user)
            ->getJson(route('admin.reports.stock.movement-data', [
                'draw' => 1,
                'start' => 0,
                'length' => -1,
                'warehouse_id' => $warehouse->id,
                'date_from' => now()->subDays(29)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('summary.total_sku', 4)
            ->assertJsonPath('summary.total_outbound_qty', 100)
            ->assertJsonPath('summary.fast_sku', 1)
            ->assertJsonPath('summary.medium_sku', 1)
            ->assertJsonPath('summary.slow_sku', 1)
            ->assertJsonPath('summary.non_moving_sku', 1)
            ->assertJsonCount(30, 'trend');

        $this->assertSame(100, collect($response->json('trend'))->sum('quantity'));
        $this->assertSame(
            now()->subDays(29)->toDateString(),
            $response->json('trend.0.date'),
        );
        $this->assertSame(
            now()->toDateString(),
            $response->json('trend.29.date'),
        );

        $classes = collect($response->json('data'))->pluck('movement_key', 'sku');
        $this->assertSame('fast', $classes['MOVE-FAST']);
        $this->assertSame('medium', $classes['MOVE-MEDIUM']);
        $this->assertSame('slow', $classes['MOVE-SLOW']);
        $this->assertSame('non_moving', $classes['MOVE-NONE']);

        $exactSkuResponse = $this->actingAs($user)
            ->getJson(route('admin.reports.stock.movement-data', [
                'draw' => 2,
                'start' => 0,
                'length' => -1,
                'warehouse_id' => $warehouse->id,
                'q' => 'MOVE-FAST',
                'date_from' => now()->subDays(29)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'MOVE-FAST');

        $this->actingAs($user)
            ->getJson(route('admin.reports.stock.movement-data', [
                'draw' => 3,
                'start' => 0,
                'length' => -1,
                'warehouse_id' => $warehouse->id,
                'q' => 'MOVE',
                'date_from' => now()->subDays(29)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $fastOnlyResponse = $this->actingAs($user)
            ->getJson(route('admin.reports.stock.movement-data', [
                'draw' => 2,
                'start' => 0,
                'length' => -1,
                'warehouse_id' => $warehouse->id,
                'movement' => 'fast',
                'date_from' => now()->subDays(29)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame(70, collect($fastOnlyResponse->json('trend'))->sum('quantity'));

        $sortedResponse = $this->actingAs($user)
            ->getJson(route('admin.reports.stock.movement-data', [
                'draw' => 2,
                'start' => 0,
                'length' => 2,
                'warehouse_id' => $warehouse->id,
                'date_from' => now()->subDays(29)->toDateString(),
                'date_to' => now()->toDateString(),
                'order' => [
                    ['column' => 4, 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 4);

        $this->assertSame([0, 10], collect($sortedResponse->json('data'))->pluck('outbound_qty')->all());
    }

    public function test_stock_movement_combines_two_warehouses_and_only_uses_manual_and_completed_resi_outbound(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $small = Warehouse::where('code', Warehouse::DEFAULT_CODE)->firstOrFail();
        $bulk = Warehouse::where('code', Warehouse::BULK_CODE)->firstOrFail();
        $item = Item::create([
            'sku' => 'MOVE-COMBINED',
            'name' => 'Item Pergerakan Gabungan',
            'category_id' => null,
        ]);
        $unit = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);

        ItemStock::create(['warehouse_id' => $small->id, 'item_id' => $item->id, 'stock' => 40]);
        ItemStock::create(['warehouse_id' => $bulk->id, 'item_id' => $item->id, 'stock' => 60]);
        ItemWarehouseSetting::create([
            'warehouse_id' => $small->id,
            'item_id' => $item->id,
            'safety_stock' => 3,
            'location' => 'SMALL-01',
        ]);
        ItemWarehouseSetting::create([
            'warehouse_id' => $bulk->id,
            'item_id' => $item->id,
            'safety_stock' => 2,
            'location' => 'BULK-01',
        ]);

        $completedResi = Resi::create([
            'id_pesanan' => 'ORDER-MOVE-COMPLETED',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-MOVE-COMPLETED',
            'uploader_id' => $user->id,
        ]);
        $completedQc = QcScanResi::create([
            'resi_id' => $completedResi->id,
            'status' => 'completed',
            'scanned_at' => now()->subDay(),
            'scanned_by' => $user->id,
            'completed_at' => now()->subDay(),
            'completed_by' => $user->id,
        ]);

        $inProgressResi = Resi::create([
            'id_pesanan' => 'ORDER-MOVE-IN-PROGRESS',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-MOVE-IN-PROGRESS',
            'uploader_id' => $user->id,
        ]);
        $inProgressQc = QcScanResi::create([
            'resi_id' => $inProgressResi->id,
            'status' => 'in_progress',
            'scanned_at' => now()->subDay(),
            'scanned_by' => $user->id,
        ]);

        $createMutation = function (
            int $warehouseId,
            int $qty,
            string $sourceType,
            ?string $sourceSubtype,
            int $sourceId
        ) use ($item, $unit, $user): void {
            StockMutation::create([
                'warehouse_id' => $warehouseId,
                'item_id' => $item->id,
                'unit_id' => $unit->id,
                'direction' => 'out',
                'qty' => $qty,
                'qty_input' => $qty,
                'conversion_qty' => 1,
                'stock_before' => 200,
                'stock_after' => 200 - $qty,
                'source_type' => $sourceType,
                'source_subtype' => $sourceSubtype,
                'source_id' => $sourceId,
                'occurred_at' => now()->subDay(),
                'created_by' => $user->id,
            ]);
        };

        $createMutation($bulk->id, 30, 'outbound', 'manual', 91001);
        $createMutation($small->id, 20, 'qc_resi', 'scan', $completedQc->id);
        $createMutation($small->id, 40, 'qc_resi', 'scan', $inProgressQc->id);
        $createMutation($small->id, 70, 'outbound', 'marketplace', 91002);
        $createMutation($small->id, 80, 'picker', null, 91003);

        $response = $this->actingAs($user)
            ->getJson(route('admin.reports.stock.movement-data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'warehouse_id' => $small->id,
                'date_from' => now()->subDays(29)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('summary.warehouse', 'Gudang Besar + Gudang Kecil')
            ->assertJsonPath('summary.outbound_source', 'Outbound manual + import resi selesai')
            ->assertJsonPath('summary.total_stock', 100)
            ->assertJsonPath('summary.total_outbound_qty', 50)
            ->assertJsonPath('data.0.sku', 'MOVE-COMBINED')
            ->assertJsonPath('data.0.warehouse', 'Gudang Besar + Gudang Kecil')
            ->assertJsonPath('data.0.warehouse_type', 'Akumulasi')
            ->assertJsonPath('data.0.stock', 100)
            ->assertJsonPath('data.0.safety_stock', 5)
            ->assertJsonPath('data.0.outbound_qty', 50)
            ->assertJsonPath('data.0.outbound_transactions', 2)
            ->assertJsonPath('data.0.average_daily_outbound', 1.67)
            ->assertJsonPath('data.0.days_cover', 60);

        $this->assertSame(50, collect($response->json('trend'))->sum('quantity'));

        $this->actingAs($user)
            ->get(route('admin.reports.stock.index', ['tab' => 'movement']))
            ->assertOk()
            ->assertDontSee('id="movement_warehouse"', false)
            ->assertSee('Stok otomatis diakumulasi dari Gudang Besar + Gudang Kecil.');
    }

    public function test_stock_movement_report_can_be_exported_to_excel_with_active_filters(): void
    {
        Excel::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $warehouse = Warehouse::where('code', 'WH-SMALL')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.reports.stock.movement-export', [
                'warehouse_id' => $warehouse->id,
                'movement' => 'non_moving',
                'is_active' => '1',
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-31',
            ]))
            ->assertOk();

        Excel::assertDownloaded(
            'analisis-pergerakan-stok-20260801-20260831.xlsx',
            fn ($export) => $export instanceof StockMovementReportExport,
        );
    }

    public function test_stock_movement_workbook_contains_dashboard_sheets_and_charts(): void
    {
        $summary = [
            'total_sku' => 0, 'total_outbound_qty' => 0, 'total_stock' => 0, 'total_transactions' => 0,
            'fast_sku' => 0, 'medium_sku' => 0, 'slow_sku' => 0, 'non_moving_sku' => 0,
            'non_moving_stock' => 0, 'below_safety_sku' => 0, 'critical_cover_sku' => 0,
            'out_of_stock_sku' => 0, 'period_days' => 31,
            'date_from' => '2026-08-01', 'date_to' => '2026-08-31',
        ];
        $raw = Excel::raw(new StockMovementReportExport(collect(), $summary), \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'stock-movement-');
        file_put_contents($path, $raw);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        try {
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $this->assertIsString($workbookXml);
            $this->assertSame(6, substr_count($workbookXml, '<sheet '));
            $this->assertNotFalse($zip->locateName('xl/charts/chart1.xml'));
            $this->assertNotFalse($zip->locateName('xl/charts/chart2.xml'));
        } finally {
            $zip->close();
            unlink($path);
        }
    }

    public function test_stock_as_of_date_report_returns_closing_stock_for_selected_day(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $warehouse = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $item = Item::create(['sku' => 'ASOF-001', 'name' => 'Item Saldo Harian', 'category_id' => null]);
        $unit = ItemUnit::create(['item_id' => $item->id, 'name' => 'PCS', 'conversion_qty' => 1, 'is_base' => true]);
        ItemStock::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'stock' => 25]);

        StockMutation::create([
            'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'unit_id' => $unit->id,
            'direction' => 'in', 'qty' => 15, 'qty_input' => 15, 'conversion_qty' => 1,
            'stock_before' => 20, 'stock_after' => 35, 'source_type' => 'inbound', 'source_id' => 7101,
            'occurred_at' => '2026-07-12 10:00:00', 'created_by' => $user->id,
        ]);
        StockMutation::create([
            'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'unit_id' => $unit->id,
            'direction' => 'out', 'qty' => 10, 'qty_input' => 10, 'conversion_qty' => 1,
            'stock_before' => 35, 'stock_after' => 25, 'source_type' => 'outbound', 'source_id' => 7102,
            'occurred_at' => '2026-07-14 09:00:00', 'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.reports.stock-as-of-date.data', [
                'draw' => 1, 'start' => 0, 'length' => 10,
                'warehouse_id' => $warehouse->id, 'date' => '2026-07-12',
            ]))
            ->assertOk()
            ->assertJsonPath('as_of_date', '2026-07-12')
            ->assertJsonPath('data.0.sku', 'ASOF-001')
            ->assertJsonPath('data.0.stock', 35)
            ->assertJsonPath('summary.total_stock', 35);
    }

    public function test_stock_as_of_date_report_can_be_exported_to_excel(): void
    {
        Excel::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('admin.reports.stock-as-of-date.export', ['date' => '2026-07-12']))
            ->assertOk();

        Excel::assertDownloaded('laporan-stok-per-tanggal-2026-07-12.xlsx');
    }

    public function test_transfer_analytics_reports_accuracy_fill_rate_and_discrepancy(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $source = Warehouse::where('code', 'WH-BULK')->firstOrFail();
        $destination = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $item = Item::create([
            'sku' => 'ANL-TRF-001',
            'name' => 'Item Analitik Transfer',
            'category_id' => null,
        ]);
        $unit = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);

        $complete = StockTransfer::create([
            'code' => 'TRF-ANL-COMPLETE',
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'status' => 'received',
            'transacted_at' => now()->subDays(2),
            'shipped_at' => now()->subDays(2)->addHour(),
            'received_at' => now()->subDays(2)->addHours(5),
            'created_by' => $user->id,
            'shipped_by' => $user->id,
            'received_by' => $user->id,
        ]);
        StockTransferItem::create([
            'stock_transfer_id' => $complete->id,
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'qty_input' => 50,
            'conversion_qty' => 1,
            'qty_base' => 50,
            'received_unit_id' => $unit->id,
            'qty_received_unit' => 50,
            'qty_received_base' => 50,
            'qty_discrepancy_base' => 0,
        ]);

        $discrepancy = StockTransfer::create([
            'code' => 'TRF-ANL-DIFF',
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'status' => 'received_with_discrepancy',
            'transacted_at' => now()->subDay(),
            'shipped_at' => now()->subDay()->addHour(),
            'received_at' => now()->subDay()->addHours(4),
            'created_by' => $user->id,
            'shipped_by' => $user->id,
            'received_by' => $user->id,
            'discrepancy_note' => 'Kurang saat pembongkaran',
        ]);
        StockTransferItem::create([
            'stock_transfer_id' => $discrepancy->id,
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'qty_input' => 100,
            'conversion_qty' => 1,
            'qty_base' => 100,
            'received_unit_id' => $unit->id,
            'qty_received_unit' => 90,
            'qty_received_base' => 90,
            'qty_discrepancy_base' => 10,
            'discrepancy_note' => 'Kurang 10 PCS',
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.reports.transfer-analytics.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('summary.completed_transfers', 2)
            ->assertJsonPath('summary.complete_transfers', 1)
            ->assertJsonPath('summary.discrepancy_transfers', 1)
            ->assertJsonPath('summary.document_accuracy', 50)
            ->assertJsonPath('summary.sent_base', 150)
            ->assertJsonPath('summary.received_base', 140)
            ->assertJsonPath('summary.discrepancy_base', 10)
            ->assertJsonPath('summary.fill_rate', 93.33)
            ->assertJsonPath('analytics.top_items.0.sku', 'ANL-TRF-001')
            ->assertJsonPath('analytics.top_items.0.discrepancy', 10);
    }

    public function test_stock_planning_uses_usage_safety_stock_and_incoming_transfer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $warehouse = Warehouse::where('code', 'WH-SMALL')->firstOrFail();
        $source = Warehouse::where('code', 'WH-BULK')->firstOrFail();
        $item = Item::create([
            'sku' => 'PLAN-001',
            'name' => 'Item Rencana Pengadaan',
            'category_id' => null,
        ]);
        $unit = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);
        ItemStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'stock' => 0,
        ]);
        ItemWarehouseSetting::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'safety_stock' => 5,
            'location' => 'A-01',
        ]);
        StockMutation::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'direction' => 'out',
            'qty' => 60,
            'qty_input' => 60,
            'conversion_qty' => 1,
            'stock_before' => 60,
            'stock_after' => 0,
            'source_type' => 'outbound',
            'source_subtype' => 'manual',
            'source_id' => 9001,
            'source_code' => 'OUT-PLAN-001',
            'occurred_at' => now()->subDays(10),
            'created_by' => $user->id,
        ]);

        $transfer = StockTransfer::create([
            'code' => 'TRF-INCOMING-PLAN',
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $warehouse->id,
            'status' => 'shipped',
            'transacted_at' => now(),
            'shipped_at' => now(),
            'created_by' => $user->id,
            'shipped_by' => $user->id,
        ]);
        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'qty_input' => 10,
            'conversion_qty' => 1,
            'qty_base' => 10,
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.reports.stock-planning.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'warehouse_id' => $warehouse->id,
                'date_from' => now()->subDays(29)->toDateString(),
                'date_to' => now()->toDateString(),
                'lead_days' => 7,
                'target_days' => 30,
            ]))
            ->assertOk()
            ->assertJsonPath('summary.period_days', 30)
            ->assertJsonPath('summary.reorder_sku', 1)
            ->assertJsonPath('summary.incoming_qty', 10)
            ->assertJsonPath('summary.recommended_qty', 50)
            ->assertJsonPath('data.0.sku', 'PLAN-001')
            ->assertJsonPath('data.0.projected_stock', 10)
            ->assertJsonPath('data.0.average_daily_usage', 2)
            ->assertJsonPath('data.0.reorder_point', 14)
            ->assertJsonPath('data.0.target_stock', 60)
            ->assertJsonPath('data.0.recommended_qty', 50)
            ->assertJsonPath('data.0.status', 'reorder');
    }

    public function test_stock_planning_defaults_to_combined_warehouses_and_completed_outbound_sources(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $small = Warehouse::where('code', Warehouse::DEFAULT_CODE)->firstOrFail();
        $bulk = Warehouse::where('code', Warehouse::BULK_CODE)->firstOrFail();
        $item = Item::create([
            'sku' => 'PLAN-COMBINED',
            'name' => 'Item Rencana Gabungan',
            'category_id' => null,
        ]);
        $unit = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);

        ItemStock::create(['warehouse_id' => $small->id, 'item_id' => $item->id, 'stock' => 40]);
        ItemStock::create(['warehouse_id' => $bulk->id, 'item_id' => $item->id, 'stock' => 60]);
        ItemWarehouseSetting::create([
            'warehouse_id' => $small->id,
            'item_id' => $item->id,
            'safety_stock' => 3,
            'location' => 'SMALL-01',
        ]);
        ItemWarehouseSetting::create([
            'warehouse_id' => $bulk->id,
            'item_id' => $item->id,
            'safety_stock' => 2,
            'location' => 'BULK-01',
        ]);

        $completedResi = Resi::create([
            'id_pesanan' => 'ORDER-PLAN-COMPLETED',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-PLAN-COMPLETED',
            'uploader_id' => $user->id,
        ]);
        $completedQc = QcScanResi::create([
            'resi_id' => $completedResi->id,
            'status' => 'completed',
            'scanned_at' => now()->subDay(),
            'scanned_by' => $user->id,
            'completed_at' => now()->subDay(),
            'completed_by' => $user->id,
        ]);

        $inProgressResi = Resi::create([
            'id_pesanan' => 'ORDER-PLAN-IN-PROGRESS',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-PLAN-IN-PROGRESS',
            'uploader_id' => $user->id,
        ]);
        $inProgressQc = QcScanResi::create([
            'resi_id' => $inProgressResi->id,
            'status' => 'in_progress',
            'scanned_at' => now()->subDay(),
            'scanned_by' => $user->id,
        ]);

        $createMutation = function (int $warehouseId, int $qty, string $sourceType, string $sourceSubtype, int $sourceId) use ($item, $unit, $user): void {
            StockMutation::create([
                'warehouse_id' => $warehouseId,
                'item_id' => $item->id,
                'unit_id' => $unit->id,
                'direction' => 'out',
                'qty' => $qty,
                'qty_input' => $qty,
                'conversion_qty' => 1,
                'stock_before' => 200,
                'stock_after' => 200 - $qty,
                'source_type' => $sourceType,
                'source_subtype' => $sourceSubtype,
                'source_id' => $sourceId,
                'occurred_at' => now()->subDay(),
                'created_by' => $user->id,
            ]);
        };

        $createMutation($bulk->id, 30, 'outbound', 'manual', 1001);
        $createMutation($small->id, 20, 'qc_resi', 'scan', $completedQc->id);
        $createMutation($small->id, 40, 'qc_resi', 'scan', $inProgressQc->id);
        $createMutation($small->id, 70, 'outbound', 'marketplace', 1002);

        $this->actingAs($user)
            ->getJson(route('admin.reports.stock-planning.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'date_from' => now()->subDays(29)->toDateString(),
                'date_to' => now()->toDateString(),
                'lead_days' => 7,
                'target_days' => 30,
            ]))
            ->assertOk()
            ->assertJsonPath('summary.warehouse', 'Gudang Besar + Gudang Kecil')
            ->assertJsonPath('summary.period_days', 30)
            ->assertJsonPath('data.0.sku', 'PLAN-COMBINED')
            ->assertJsonPath('data.0.current_stock', 100)
            ->assertJsonPath('data.0.safety_stock', 5)
            ->assertJsonPath('data.0.usage_qty', 50)
            ->assertJsonPath('data.0.average_daily_usage', 1.67)
            ->assertJsonPath('data.0.target_stock', 50)
            ->assertJsonPath('data.0.status', 'healthy');
    }

    public function test_stock_forecast_uses_weighted_demand_and_distinct_procurement_lead_times_without_safety_stock(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $small = Warehouse::where('code', Warehouse::DEFAULT_CODE)->firstOrFail();
        $bulk = Warehouse::where('code', Warehouse::BULK_CODE)->firstOrFail();
        $item = Item::create([
            'sku' => 'FORECAST-001',
            'name' => 'Item Forecast',
            'category_id' => null,
            'procurement_source' => Item::PROCUREMENT_IMPORT,
        ]);
        $unit = ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);
        ItemUnit::create([
            'item_id' => $item->id,
            'name' => 'KOLI',
            'conversion_qty' => 10,
            'is_base' => false,
        ]);
        ItemStock::create(['warehouse_id' => $small->id, 'item_id' => $item->id, 'stock' => 20]);
        ItemStock::create(['warehouse_id' => $bulk->id, 'item_id' => $item->id, 'stock' => 30]);
        ItemWarehouseSetting::create([
            'warehouse_id' => $small->id,
            'item_id' => $item->id,
            'safety_stock' => 999,
            'location' => 'F-01',
        ]);

        foreach ([[10, 60], [40, 30], [70, 20]] as $index => [$daysAgo, $qty]) {
            StockMutation::create([
                'warehouse_id' => $index === 2 ? $bulk->id : $small->id,
                'item_id' => $item->id,
                'unit_id' => $unit->id,
                'direction' => 'out',
                'qty' => $qty,
                'qty_input' => $qty,
                'conversion_qty' => 1,
                'stock_before' => 200,
                'stock_after' => 200 - $qty,
                'source_type' => 'outbound',
                'source_subtype' => 'manual',
                'source_id' => 2000 + $index,
                'occurred_at' => now()->subDays($daysAgo),
                'created_by' => $user->id,
            ]);
        }

        $this->actingAs($user)
            ->getJson(route('admin.reports.stock-planning.forecast-data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'history_days' => 90,
                'import_lead_days' => 90,
                'production_lead_days' => 14,
                'review_days' => 30,
            ]))
            ->assertOk()
            ->assertJsonPath('methodology.uses_safety_stock', false)
            ->assertJsonPath('summary.warehouse', 'Gudang Besar + Gudang Kecil')
            ->assertJsonPath('data.0.sku', 'FORECAST-001')
            ->assertJsonPath('data.0.stock_position', 50)
            ->assertJsonPath('data.0.history_qty', 110)
            ->assertJsonPath('data.0.forecast_daily', 1.43)
            ->assertJsonPath('data.0.trend', 'growing')
            ->assertJsonPath('data.0.trend_percent', 100)
            ->assertJsonPath('data.0.procurement_source', Item::PROCUREMENT_IMPORT)
            ->assertJsonPath('data.0.procurement_source_label', 'Import')
            ->assertJsonPath('data.0.recommendation.status', 'order_now')
            ->assertJsonPath('data.0.recommendation.recommended_qty', 130)
            ->assertJsonPath('summary.import_order_now_sku', 1)
            ->assertJsonPath('summary.production_order_now_sku', 0)
            ->assertJsonPath('data.0.import.status', 'order_now')
            ->assertJsonPath('data.0.import.recommended_qty', 130)
            ->assertJsonPath('data.0.import.recommended_packages', 13)
            ->assertJsonPath('data.0.production.status', 'plan')
            ->assertJsonPath('data.0.production.recommended_qty', 14);
        $item->update(['procurement_source' => Item::PROCUREMENT_NANGGEWER]);

        $this->actingAs($user)
            ->getJson(route('admin.reports.stock-planning.forecast-data', [
                'draw' => 2,
                'start' => 0,
                'length' => 10,
                'history_days' => 90,
                'import_lead_days' => 90,
                'production_lead_days' => 14,
                'review_days' => 30,
                'procurement_source' => Item::PROCUREMENT_NANGGEWER,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.procurement_source', Item::PROCUREMENT_NANGGEWER)
            ->assertJsonPath('data.0.recommendation.status', 'plan')
            ->assertJsonPath('data.0.recommendation.recommended_qty', 14)
            ->assertJsonPath('summary.import_order_now_sku', 0)
            ->assertJsonPath('summary.production_recommended_qty', 14);
    }
}
