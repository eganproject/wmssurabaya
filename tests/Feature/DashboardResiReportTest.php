<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardResiReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_resi_report_calculates_calendar_day_average_and_daily_rows(): void
    {
        $user = User::factory()->create();
        $now = now();

        foreach ([
            ['ORDER-001', '2026-09-01', 'active'],
            ['ORDER-002', '2026-09-01', 'active'],
            ['ORDER-003', '2026-09-01', 'active'],
            ['ORDER-004', '2026-09-01', 'canceled'],
            ['ORDER-005', '2026-09-03', 'active'],
        ] as [$orderId, $uploadDate, $status]) {
            DB::table('resis')->insert([
                'id_pesanan' => $orderId,
                'tanggal_pesanan' => $uploadDate,
                'tanggal_upload' => $uploadDate,
                'no_resi' => 'RESI-'.$orderId,
                'kurir_id' => 1,
                'status' => $status,
                'uploader_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $request = Request::create('/admin', 'GET', [
            'dashboard_tab' => 'laporan-resi',
            'report_date_from' => '2026-09-01',
            'report_date_to' => '2026-09-03',
        ]);

        $view = app(DashboardController::class)->index($request);
        $data = $view->getData();

        $this->assertSame('2026-09-01', $data['resiReportDateFrom']);
        $this->assertSame('2026-09-03', $data['resiReportDateTo']);
        $this->assertSame(3, $data['resiReportDays']);
        $this->assertSame(4, $data['resiReportTotalActive']);
        $this->assertSame(1, $data['resiReportTotalCanceled']);
        $this->assertSame(2, $data['resiReportActiveDays']);
        $this->assertSame(1.33, $data['resiReportAverage']);
        $this->assertSame(3, $data['resiReportPeakDay']['active_count']);

        $daily = $data['resiReportDaily']->keyBy('date');
        $this->assertSame(3, $daily['2026-09-01']['active_count']);
        $this->assertSame(1, $daily['2026-09-01']['canceled_count']);
        $this->assertSame(0, $daily['2026-09-02']['active_count']);
        $this->assertSame(1, $daily['2026-09-03']['active_count']);

        $this->actingAs($user)
            ->get(route('admin.dashboard', [
                'dashboard_tab' => 'laporan-resi',
                'report_date_from' => '2026-09-01',
                'report_date_to' => '2026-09-03',
            ]))
            ->assertOk()
            ->assertSee('Laporan Resi Harian')
            ->assertSee('1,33');
    }

    public function test_resi_report_normalizes_reversed_date_range(): void
    {
        $request = Request::create('/admin', 'GET', [
            'report_date_from' => '2026-09-10',
            'report_date_to' => '2026-09-08',
        ]);

        $data = app(DashboardController::class)->index($request)->getData();

        $this->assertSame('2026-09-08', $data['resiReportDateFrom']);
        $this->assertSame('2026-09-10', $data['resiReportDateTo']);
        $this->assertSame(3, $data['resiReportDays']);
    }
}
