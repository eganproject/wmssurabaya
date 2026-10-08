<?php

namespace Tests\Feature;

use App\Exports\CbmForecastExport;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class CbmForecastTest extends TestCase
{
    use RefreshDatabase;

    private function seedItems(): void
    {
        Item::create(['sku' => 'CBM-A', 'name' => 'Item A', 'koli_length_cm' => 50, 'koli_width_cm' => 40, 'koli_height_cm' => 30]);
        Item::create(['sku' => 'CBM-B', 'name' => 'Item B', 'koli_length_cm' => 100, 'koli_width_cm' => 100, 'koli_height_cm' => 100]);
        Item::create(['sku' => 'CBM-NODIM', 'name' => 'Item Tanpa Dimensi']);
    }

    public function test_page_search_and_lookup_return_cbm_from_master_item(): void
    {
        $this->seedItems();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get(route('admin.reports.cbm-forecast.index'))->assertOk()->assertSee('Forecast CBM');

        $this->actingAs($user)->getJson(route('admin.reports.cbm-forecast.data', ['q' => 'CBM-A']))
            ->assertOk()
            ->assertJsonPath('results.0.id', 'CBM-A');

        $data = $this->actingAs($user)
            ->getJson(route('admin.reports.cbm-forecast.lookup', ['skus' => 'CBM-A,CBM-NODIM,UNKNOWN']))
            ->assertOk()
            ->json('data');

        $bySku = collect($data)->keyBy('sku');
        $this->assertCount(2, $bySku);
        $this->assertEqualsWithDelta(0.06, $bySku['CBM-A']['cbm_per_koli'], 1e-9);
        $this->assertNull($bySku['CBM-NODIM']['cbm_per_koli']);
    }

    public function test_print_recalculates_totals_and_excel_export_downloads(): void
    {
        $this->freezeTime();
        $this->seedItems();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $payload = ['title' => 'PO Test', 'lines' => [
            ['sku' => 'CBM-A', 'koli' => 10],   // 0.6
            ['sku' => 'CBM-B', 'koli' => 2],    // 2.0
            ['sku' => 'CBM-NODIM', 'koli' => 5],
            ['sku' => 'UNKNOWN', 'koli' => 3],
        ]];

        $response = $this->actingAs($user)->post(route('admin.reports.cbm-forecast.print'), $payload)->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEqualsWithDelta(2.6, $summary['total_cbm'], 1e-9);
        $this->assertSame(20, $summary['total_koli']);
        $this->assertSame(2, $summary['incomplete_count']);
        $this->assertSame(8, $summary['incomplete_koli']);
        $response->assertSee('SKU tidak ditemukan')->assertSee('Dimensi koli belum diisi');

        Excel::fake();
        $this->actingAs($user)->post(route('admin.reports.cbm-forecast.export'), $payload)->assertOk();
        Excel::assertDownloaded('forecast-cbm-'.now()->format('Ymd-His').'.xlsx', function (CbmForecastExport $export) {
            $rows = $export->array();
            return $rows[4][1] === 'CBM-A' && $rows[8][2] === 'TOTAL' && abs($rows[8][9] - 2.6) < 1e-9;
        });
    }

    public function test_export_rejects_invalid_koli_and_does_not_touch_items(): void
    {
        $this->seedItems();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $before = Item::orderBy('id')->get()->toArray();

        $this->actingAs($user)
            ->post(route('admin.reports.cbm-forecast.export'), ['lines' => [['sku' => 'CBM-A', 'koli' => 0]]])
            ->assertSessionHasErrors('lines.0.koli');

        $this->assertSame($before, Item::orderBy('id')->get()->toArray());
    }
}
