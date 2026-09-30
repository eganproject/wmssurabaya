<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InboundReturnsReportExport implements WithMultipleSheets
{
    private ?Collection $itemRows = null;

    public function __construct(
        private readonly Collection $transactions,
        private readonly array $filters = [],
        private readonly ?string $generatedBy = null,
    ) {}

    public function sheets(): array
    {
        $itemRows = $this->itemRows ??= $this->buildItemRows();

        return [
            new InboundReturnsSummarySheet($this->transactions, $itemRows, $this->filters, $this->generatedBy),
            new InboundReturnsItemSheet($itemRows),
            new InboundReturnsDailySheet($this->transactions, $itemRows),
            new InboundReturnsFollowUpSheet($this->transactions, $itemRows),
            new InboundReturnsDetailSheet($itemRows),
        ];
    }

    private function buildItemRows(): Collection
    {
        return $this->transactions
            ->flatMap(function ($transaction) {
                return $transaction->items->map(function ($line) use ($transaction) {
                    $qtyExpected = (int) ($line->qty_input ?: $line->qty ?: 0);
                    $qtyReceived = (int) ($line->qty_received ?? $line->qty ?? 0);

                    return [
                        'transaction_id' => (int) $transaction->id,
                        'code' => (string) $transaction->code,
                        'ref_no' => (string) ($transaction->ref_no ?? ''),
                        'transacted_at' => $transaction->transacted_at,
                        'created_at' => $transaction->created_at,
                        'status' => (string) ($transaction->status ?? 'pending'),
                        'approved_at' => $transaction->approved_at,
                        'finalized_at' => $transaction->finalized_at,
                        'warehouse' => (string) ($transaction->warehouse?->name ?? '-'),
                        'submitted_by' => (string) ($transaction->creator?->name ?? '-'),
                        'approved_by' => (string) ($transaction->approver?->name ?? '-'),
                        'finalized_by' => (string) ($transaction->finalizer?->name ?? '-'),
                        'transaction_note' => (string) ($transaction->note ?? ''),
                        'line_id' => (int) $line->id,
                        'item_id' => (int) ($line->item_id ?? 0),
                        'sku' => (string) ($line->item?->sku ?? '-'),
                        'item_name' => (string) ($line->item?->name ?? 'Item tidak ditemukan'),
                        'base_unit' => (string) ($line->item?->baseUnit?->name ?? 'PCS/SET'),
                        'qty_expected' => $qtyExpected,
                        'qty_received' => $qtyReceived,
                        'qty_good' => (int) ($line->qty_good ?? 0),
                        'qty_damaged' => (int) ($line->qty_damaged ?? 0),
                        'qty_missing' => (int) ($line->qty_missing ?? max(0, $qtyExpected - $qtyReceived)),
                        'item_note' => (string) ($line->note ?? ''),
                    ];
                });
            })
            ->values();
    }
}
