<?php

namespace App\Exports;

use App\Support\StockMutationReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockMutationReportExport implements WithMultipleSheets
{
    private ?Collection $mutations = null;

    public function __construct(
        private readonly array $filters = [],
        private readonly ?string $generatedBy = null,
    ) {}

    public function sheets(): array
    {
        $mutations = $this->mutations ??= StockMutationReport::query($this->filters)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        return [
            new StockMutationSummarySheet($mutations, $this->filters, $this->generatedBy),
            new StockMutationDailySummarySheet($mutations),
            new StockMutationItemSummarySheet($mutations),
            new StockMutationDetailSheet($mutations),
        ];
    }
}
