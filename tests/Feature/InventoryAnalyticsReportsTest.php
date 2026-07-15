<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ItemUnit;
use App\Models\ItemWarehouseSetting;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class InventoryAnalyticsReportsTest extends TestCase
{
    use RefreshDatabase;

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
}
