<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class QcPerformanceReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $dailyRows,
        private readonly Collection $hourlyRows,
        private readonly Collection $perUserRows,
        private readonly Collection $detailRows,
        private readonly array $summary,
        private readonly array $filters = [],
        private readonly ?string $generatedBy = null,
    ) {}

    public function sheets(): array
    {
        return [
            new QcPerformanceDashboardSheet($this->hourlyRows, $this->perUserRows, $this->summary, $this->filters, $this->generatedBy),
            new QcPerformanceDataSheet($this->perUserRows, 'Per Akun', 'user'),
            new QcPerformanceDataSheet($this->dailyRows, 'Harian', 'daily'),
            new QcPerformanceDataSheet($this->hourlyRows, 'Per Jam', 'hourly'),
            new QcPerformanceDataSheet($this->detailRows, 'Detail Resi', 'detail'),
            new QcPerformanceGuideSheet,
        ];
    }
}
