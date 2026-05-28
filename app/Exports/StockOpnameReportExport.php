<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockOpnameReportExport implements WithMultipleSheets
{
    public function __construct(private array $filters = [])
    {
    }

    public function sheets(): array
    {
        return [
            new StockOpnameReportSummarySheet($this->filters),
            new StockOpnameReportDiffSheet($this->filters, 'plus'),
            new StockOpnameReportDiffSheet($this->filters, 'minus'),
        ];
    }
}
