<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use App\Models\Divisi;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QcPerformanceDashboardSheet extends DefaultValueBinder implements FromArray, WithCharts, WithCustomValueBinder, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    public function __construct(
        private readonly Collection $hourlyRows,
        private readonly Collection $perUserRows,
        private readonly array $summary,
        private readonly array $filters = [],
        private readonly ?string $generatedBy = null,
    ) {}

    public function title(): string
    {
        return 'Dashboard';
    }

    public function array(): array
    {
        $rows = [
            ['DASHBOARD PERFORMA QC SCAN', '', '', '', '', '', '', '', '', '', '', ''],
            [config('app.name').' | Produktivitas per akun dan per jam aktif', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', ''],
            ['FILTER LAPORAN', '', '', '', '', '', '', '', '', '', '', ''],
            ['Periode', $this->periodLabel(), '', '', '', '', '', '', '', '', '', ''],
            ['Divisi', $this->divisionLabel(), '', '', '', '', '', '', '', '', '', ''],
            ['Pencarian akun', trim((string) ($this->filters['q'] ?? '')) ?: 'Semua akun', '', '', '', '', '', '', '', '', '', ''],
            ['Dibuat', now()->format('d/m/Y H:i:s').' oleh '.($this->generatedBy ?: '-'), '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', ''],
            ['KPI UTAMA', '', '', '', '', '', '', '', '', '', '', ''],
            ['Petugas', 'Hari Aktif', 'Jam Aktif', 'Total Resi', 'Resi/Jam Aktif', 'QC Selesai', 'Completion', 'Qty Scan', 'Qty/Jam Aktif', 'Kesesuaian Qty', 'Rata-rata Durasi', 'Jam Tersibuk'],
            [
                $this->summary['petugas_count'], $this->summary['day_count'], $this->summary['active_hours'],
                $this->summary['resi_total'], $this->summary['resi_per_hour'], $this->summary['completed_total'],
                $this->summary['completion_pct'] / 100, $this->summary['qty_total'], $this->summary['qty_per_hour'],
                $this->summary['scan_pct'] / 100, $this->summary['avg_cycle_minutes'],
                ($this->summary['peak_hour'] ?: '-').' ('.$this->summary['peak_hour_resi'].' resi)',
            ],
            ['', '', '', '', '', '', '', '', '', '', '', ''],
            ['PERFORMA PER AKUN', '', '', '', '', '', '', '', '', '', '', ''],
            ['Petugas QC', 'Divisi', 'Hari Aktif', 'Jam Aktif', 'Total Resi', 'Resi/Jam', 'Qty/Jam', 'Completion', 'Rata-rata Durasi', '', '', ''],
        ];

        $topUsers = $this->perUserRows->take(10)->values();
        for ($i = 0; $i < 10; $i++) {
            $row = $topUsers->get($i);
            $rows[] = $row ? [
                $row->petugas, $row->divisi, $row->active_days, $row->active_hours, $row->total_resi,
                $row->resi_per_hour, $row->qty_per_hour, $row->completion_pct / 100, $row->avg_cycle_minutes,
                '', '', '',
            ] : ['', '', '', '', '', '', '', '', '', '', '', ''];
        }

        $rows[] = ['', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['POLA VOLUME BERDASARKAN JAM', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['Jam', 'Total Resi', 'QC Selesai', 'Qty Scan', 'Completion', '', '', '', '', '', '', ''];

        $byHour = $this->hourlyRows->groupBy('hour_label');
        foreach (range(0, 23) as $hour) {
            $label = str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00';
            $group = $byHour->get($label, collect());
            $total = (int) $group->sum('total_resi');
            $completed = (int) $group->sum('completed_resi');
            $rows[] = [$label, $total, $completed, (int) $group->sum('scanned_qty'), $total > 0 ? $completed / $total : 0, '', '', '', '', '', '', ''];
        }

        return $rows;
    }

    public function charts(): array
    {
        $userCount = max(1, min(10, $this->perUserRows->count()));
        $userEnd = 15 + $userCount;
        $userSeries = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            [new DataSeriesValues('String', "'Dashboard'!\$F\$15", null, 1)],
            [new DataSeriesValues('String', "'Dashboard'!\$A\$16:\$A\${$userEnd}", null, $userCount)],
            [new DataSeriesValues('Number', "'Dashboard'!\$F\$16:\$F\${$userEnd}", null, $userCount)],
            DataSeries::DIRECTION_BAR,
        );
        $userChart = new Chart('qc_user_productivity', new Title('Produktivitas Resi per Jam Aktif'), null, new PlotArea(null, [$userSeries]));
        $userChart->setTopLeftPosition('J14');
        $userChart->setBottomRightPosition('N27');

        $hourSeries = new DataSeries(
            DataSeries::TYPE_LINECHART,
            DataSeries::GROUPING_STANDARD,
            [0],
            [new DataSeriesValues('String', "'Dashboard'!\$B\$28", null, 1)],
            [new DataSeriesValues('String', "'Dashboard'!\$A\$29:\$A\$52", null, 24)],
            [new DataSeriesValues('Number', "'Dashboard'!\$B\$29:\$B\$52", null, 24)],
        );
        $hourChart = new Chart('qc_hourly_volume', new Title('Distribusi Resi Berdasarkan Jam'), null, new PlotArea(null, [$hourSeries]));
        $hourChart->setTopLeftPosition('G29');
        $hourChart->setBottomRightPosition('N45');

        return [$userChart, $hourChart];
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ([1, 2, 4, 10, 14, 27] as $row) {
            $sheet->mergeCells("A{$row}:L{$row}");
        }
        foreach (range(5, 8) as $row) {
            $sheet->mergeCells("B{$row}:L{$row}");
        }
        $sheet->setShowGridlines(false);
        $sheet->freezePane('A15');
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(20);
        foreach (range('C', 'L') as $column) {
            $sheet->getColumnDimension($column)->setWidth(16);
        }
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 20, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A2:L2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach ([4, 10, 14, 27] as $row) {
            $sheet->getStyle("A{$row}:L{$row}")->applyFromArray($this->sectionStyle());
        }
        foreach ([11, 15, 28] as $row) {
            $sheet->getStyle("A{$row}:L{$row}")->applyFromArray($this->headerStyle());
        }
        $sheet->getStyle('A12:L12')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A12:L12')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAF7');
        $sheet->getStyle('G12:G12')->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getStyle('J12:J12')->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getStyle('F16:G25')->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('H16:H25')->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getStyle('E29:E52')->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getStyle('A4:L52')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle('A1:L52')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A5:A8')->getFont()->setBold(true);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);

        return [];
    }

    private function periodLabel(): string
    {
        $from = $this->filters['date_from'] ?? null;
        $to = $this->filters['date_to'] ?? null;
        return $from && $to ? "{$from} s.d. {$to}" : ($from ? "Mulai {$from}" : ($to ? "Sampai {$to}" : 'Semua tanggal'));
    }

    private function divisionLabel(): string
    {
        $id = (int) ($this->filters['divisi_id'] ?? 0);
        return $id ? (Divisi::find($id)?->name ?? "Divisi #{$id}") : 'Semua divisi yang diizinkan';
    }

    private function sectionStyle(): array
    {
        return ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']]];
    }

    private function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B9BD5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ];
    }
}
