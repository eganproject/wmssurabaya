<?php

namespace Tests\Feature;

use App\Exports\StockForecastReportExport;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ItemUnit;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class StockForecastExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_forecast_export_uses_active_filters_and_builds_analysis_sheets(): void
    {
        Carbon::setTestNow('2026-09-24 10:11:12');
        Excel::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->seedForecastItems($user);

        $this->actingAs($user)
            ->get(route('admin.reports.stock-planning.forecast-export', [
                'history_days' => 90,
                'import_lead_days' => 75,
                'production_lead_days' => 12,
                'review_days' => 21,
                'procurement_source' => Item::PROCUREMENT_IMPORT,
                'action' => 'any_action',
            ]))
            ->assertOk();

        Excel::assertDownloaded(
            'analisa-forecast-pengadaan-stok-20260924-101112.xlsx',
            function (StockForecastReportExport $export) {
                $sheets = $export->sheets();

                $this->assertSame(
                    [
                        'Dashboard',
                        'Prioritas Pengadaan',
                        'Detail Forecast',
                        'Analisis Kategori',
                        'Kualitas Data',
                        'Panduan Metodologi',
                    ],
                    array_map(fn ($sheet) => $sheet->title(), $sheets),
                );

                $dashboard = $sheets[0]->array();
                $this->assertSame('Import', $dashboard[9][1]);
                $this->assertSame('Ada rekomendasi', $dashboard[10][1]);
                $this->assertSame('Import 75 hari | Nanggewer 12 hari', $dashboard[6][1]);
                $this->assertSame('21 hari', $dashboard[7][1]);

                $priority = $sheets[1]->array();
                $this->assertCount(2, $priority);
                $this->assertSame('FORECAST-EXPORT-IMPORT', $priority[1][2]);
                $this->assertSame('Import', $priority[1][5]);
                $this->assertSame('Order Sekarang', $priority[1][1]);
                $this->assertGreaterThan(0, $priority[1][16]);

                $detail = $sheets[2]->array();
                $this->assertCount(2, $detail);
                $this->assertSame('FORECAST-EXPORT-IMPORT', $detail[1][0]);
                $this->assertSame('Import', $detail[1][3]);

                $category = $sheets[3]->array();
                $this->assertCount(2, $category);
                $this->assertSame('Import', $category[1][1]);
                $this->assertEquals(1.0, $category[1][11]);

                $quality = $sheets[4]->array();
                $this->assertCount(2, $quality);
                $this->assertSame('FORECAST-EXPORT-IMPORT', $quality[1][0]);
                $this->assertSame('Rendah', $quality[1][3]);

                $methodology = $sheets[5]->array();
                $this->assertStringContainsString('Tidak memakai safety stock', $methodology[10][2]);

                return true;
            },
        );

        $this->assertSame(
            'admin.reports.stock-planning.index',
            Permission::resolveBaseRoute('admin.reports.stock-planning.forecast-export'),
        );
    }

    public function test_forecast_export_returns_a_real_downloadable_xlsx_with_charts(): void
    {
        Carbon::setTestNow('2026-09-24 10:11:12');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->seedForecastItems($user);

        $this->actingAs($user)
            ->get(route('admin.reports.stock-planning.forecast-export', [
                'history_days' => 90,
                'import_lead_days' => 90,
                'production_lead_days' => 14,
                'review_days' => 30,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('analisa-forecast-pengadaan-stok-20260924-101112.xlsx');
    }

    private function seedForecastItems(User $user): void
    {
        $warehouse = Warehouse::query()->where('code', Warehouse::DEFAULT_CODE)->firstOrFail();

        $importItem = Item::create([
            'sku' => 'FORECAST-EXPORT-IMPORT',
            'name' => 'Item Forecast Import',
            'category_id' => null,
            'procurement_source' => Item::PROCUREMENT_IMPORT,
        ]);
        $importUnit = ItemUnit::create([
            'item_id' => $importItem->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);
        ItemUnit::create([
            'item_id' => $importItem->id,
            'name' => 'KOLI',
            'conversion_qty' => 10,
            'is_base' => false,
        ]);
        ItemStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $importItem->id,
            'stock' => 5,
        ]);
        StockMutation::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $importItem->id,
            'unit_id' => $importUnit->id,
            'direction' => 'out',
            'qty' => 60,
            'qty_input' => 60,
            'conversion_qty' => 1,
            'stock_before' => 65,
            'stock_after' => 5,
            'source_type' => 'outbound',
            'source_subtype' => 'manual',
            'source_id' => 99001,
            'occurred_at' => now()->subDays(5),
            'created_by' => $user->id,
        ]);

        $productionItem = Item::create([
            'sku' => 'FORECAST-EXPORT-PRODUCTION',
            'name' => 'Item Forecast Produksi',
            'category_id' => null,
            'procurement_source' => Item::PROCUREMENT_NANGGEWER,
        ]);
        $productionUnit = ItemUnit::create([
            'item_id' => $productionItem->id,
            'name' => 'PCS',
            'conversion_qty' => 1,
            'is_base' => true,
        ]);
        ItemStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $productionItem->id,
            'stock' => 5,
        ]);
        StockMutation::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $productionItem->id,
            'unit_id' => $productionUnit->id,
            'direction' => 'out',
            'qty' => 30,
            'qty_input' => 30,
            'conversion_qty' => 1,
            'stock_before' => 35,
            'stock_after' => 5,
            'source_type' => 'outbound',
            'source_subtype' => 'manual',
            'source_id' => 99002,
            'occurred_at' => now()->subDays(5),
            'created_by' => $user->id,
        ]);
    }
}
