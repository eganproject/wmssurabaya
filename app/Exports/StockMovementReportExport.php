<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockMovementReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $summary,
        private readonly array $filters = [],
        private readonly ?string $generatedBy = null,
    ) {}

    public function sheets(): array
    {
        return [
            new StockMovementDashboardSheet($this->rows, $this->summary, $this->filters, $this->generatedBy),
            new StockMovementSummarySheet($this->rows, 'movement_label', 'Klasifikasi'),
            new StockMovementSummarySheet($this->rows, 'warehouse', 'Cakupan Gudang'),
            new StockMovementSummarySheet($this->rows, 'category', 'Per Kategori'),
            new StockMovementDetailSheet($this->rows),
            new StockMovementMethodologySheet,
        ];
    }
}
