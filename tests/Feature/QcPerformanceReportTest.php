<?php

namespace Tests\Feature;

use App\Exports\QcPerformanceReportExport;
use App\Models\Divisi;
use App\Models\QcScanResi;
use App\Models\QcScanResiItem;
use App\Models\Resi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use ZipArchive;

class QcPerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_calculates_hourly_productivity_peak_hour_and_cycle_time(): void
    {
        $user = User::factory()->create(['name' => 'QC Satu', 'email_verified_at' => now()]);
        $this->createQc($user, 'QC-001', '2026-09-15 08:05:00', '2026-09-15 08:15:00', 2, 2);
        $this->createQc($user, 'QC-002', '2026-09-15 08:25:00', '2026-09-15 08:45:00', 4, 4);
        $this->createQc($user, 'QC-003', '2026-09-15 09:10:00', null, 8, 6);

        $response = $this->actingAs($user)->getJson(route('admin.outbound.picker-reports.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.resi_total', 3)
            ->assertJsonPath('summary.active_hours', 2)
            ->assertJsonPath('summary.resi_per_hour', 1.5)
            ->assertJsonPath('summary.qty_per_hour', 6)
            ->assertJsonPath('summary.peak_hour', '08:00')
            ->assertJsonPath('summary.peak_hour_resi', 2)
            ->assertJsonPath('summary.avg_cycle_minutes', 15)
            ->assertJsonPath('data.0.active_hours', 2)
            ->assertJsonPath('data.0.peak_hour', '08:00')
            ->assertJsonPath('performers.0.petugas', 'QC Satu')
            ->assertJsonPath('performers.0.resi_per_hour', 1.5);

        $this->assertEqualsWithDelta(66.7, $response->json('summary.completion_pct'), 0.01);
    }

    public function test_detail_returns_hourly_breakdown(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->createQc($user, 'DETAIL-001', '2026-09-15 10:05:00', '2026-09-15 10:12:00', 3, 3);
        $this->createQc($user, 'DETAIL-002', '2026-09-15 11:05:00', '2026-09-15 11:14:00', 2, 2);

        $this->actingAs($user)->getJson(route('admin.outbound.picker-reports.detail', [
            'date' => '2026-09-15',
            'user_id' => $user->id,
        ]))->assertOk()
            ->assertJsonPath('active_hours', 2)
            ->assertJsonPath('resi_per_hour', 1)
            ->assertJsonPath('hourly.0.hour', '10:00')
            ->assertJsonPath('hourly.1.hour', '11:00');
    }

    public function test_report_keeps_division_access_scope(): void
    {
        Divisi::create(['name' => 'Tanpa Divisi']);
        $divisionA = Divisi::create(['name' => 'Divisi A']);
        $divisionB = Divisi::create(['name' => 'Divisi B']);
        $viewer = User::factory()->create(['divisi_id' => $divisionA->id, 'email_verified_at' => now()]);
        $qcA = User::factory()->create(['name' => 'QC Divisi A', 'divisi_id' => $divisionA->id]);
        $qcB = User::factory()->create(['name' => 'QC Divisi B', 'divisi_id' => $divisionB->id]);
        $this->createQc($qcA, 'DIV-A', '2026-09-15 08:00:00', '2026-09-15 08:10:00', 1, 1);
        $this->createQc($qcB, 'DIV-B', '2026-09-15 08:00:00', '2026-09-15 08:10:00', 1, 1);

        $this->actingAs($viewer)->getJson(route('admin.outbound.picker-reports.data', [
            'date_from' => '2026-09-15', 'date_to' => '2026-09-15', 'length' => -1,
        ]))->assertOk()
            ->assertJsonPath('summary.resi_total', 1)
            ->assertJsonPath('performers.0.petugas', 'QC Divisi A')
            ->assertJsonMissing(['petugas' => 'QC Divisi B']);
    }

    public function test_report_can_be_exported_with_current_filters(): void
    {
        Excel::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get(route('admin.outbound.picker-reports.export', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-15',
        ]))->assertOk();

        Excel::assertDownloaded(
            'laporan-performa-qc-2026-09-01-2026-09-15.xlsx',
            fn ($export) => $export instanceof QcPerformanceReportExport,
        );
    }

    public function test_export_workbook_contains_analysis_sheets_and_charts(): void
    {
        $summary = [
            'petugas_count' => 0, 'day_count' => 0, 'active_hours' => 0, 'resi_total' => 0,
            'resi_per_hour' => 0, 'completed_total' => 0, 'completion_pct' => 0, 'qty_total' => 0,
            'qty_per_hour' => 0, 'scan_pct' => 0, 'avg_cycle_minutes' => null,
            'peak_hour' => null, 'peak_hour_resi' => 0,
        ];
        $empty = collect();
        $raw = Excel::raw(
            new QcPerformanceReportExport($empty, $empty, $empty, $empty, $summary),
            \Maatwebsite\Excel\Excel::XLSX,
        );
        $path = tempnam(sys_get_temp_dir(), 'qc-performance-');
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

    private function createQc(User $user, string $code, string $scannedAt, ?string $completedAt, int $required, int $scanned): void
    {
        $resi = Resi::create([
            'id_pesanan' => 'ORDER-'.$code,
            'tanggal_pesanan' => '2026-09-15',
            'tanggal_upload' => '2026-09-15',
            'no_resi' => $code,
            'uploader_id' => $user->id,
        ]);
        $qc = QcScanResi::create([
            'resi_id' => $resi->id,
            'status' => $completedAt ? 'completed' : 'in_progress',
            'scanned_at' => $scannedAt,
            'scanned_by' => $user->id,
            'completed_at' => $completedAt,
            'completed_by' => $completedAt ? $user->id : null,
        ]);
        QcScanResiItem::create([
            'qc_scan_resi_id' => $qc->id,
            'sku' => 'SKU-'.$code,
            'required_qty' => $required,
            'scanned_qty' => $scanned,
        ]);
    }
}
