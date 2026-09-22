<?php

namespace Tests\Feature;

use App\Models\Resi;
use App\Models\User;
use App\Support\ResiDuplicateReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResiDuplicateReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicates_include_other_dates_and_canceled_records_but_ignore_blank_numbers(): void
    {
        $user = User::factory()->create();
        foreach ([
            ['ORDER-A', ' awb-1 ', '2026-09-01', 'active'],
            ['ORDER-B', 'AWB-1', '2026-09-22', 'canceled'],
            ['ORDER-C', null, '2026-09-22', 'active'],
            ['ORDER-D', ' ', '2026-09-22', 'active'],
            ['ORDER-OLD', 'OLD', '2026-09-01', 'active'],
            ['ORDER-OLD', 'OLD', '2026-09-02', 'active'],
        ] as [$order, $awb, $date, $status]) {
            Resi::create([
                'id_pesanan' => $order, 'no_resi' => $awb,
                'tanggal_upload' => $date, 'tanggal_pesanan' => $date,
                'uploader_id' => $user->id, 'status' => $status,
            ]);
        }

        $report = app(ResiDuplicateReport::class)->forDate('2026-09-22');
        $this->assertCount(1, $report['groups']);
        $this->assertSame('AWB-1', $report['groups'][0]['value']);
        $this->assertCount(2, $report['groups'][0]['rows']);
        $this->assertSame(1, $report['groups'][0]['active_count']);

        $this->actingAs($user)->get(route('admin.dashboard', ['date' => '2026-09-22']))
            ->assertOk()->assertSee('Pemeriksaan Resi Kembar')->assertSee('ORDER-A')->assertSee('ORDER-B');

        $older = app(ResiDuplicateReport::class)->forDate('2026-09-02');
        $this->assertCount(2, $older['groups']);
        $this->assertSame(0, $older['updated_count']);
    }
}
