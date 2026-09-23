<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InboundReceiptsReportExport implements WithMultipleSheets
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
            new InboundReceiptsSummarySheet($this->transactions, $itemRows, $this->filters, $this->generatedBy),
            new InboundReceiptsWarehouseSheet($this->transactions, $itemRows),
            new InboundReceiptsItemSheet($itemRows),
            new InboundReceiptsDailySheet($this->transactions, $itemRows),
            new InboundReceiptsDetailSheet($itemRows),
        ];
    }

    private function buildItemRows(): Collection
    {
        return $this->transactions
            ->flatMap(function ($transaction) {
                return $transaction->items->map(function ($line) use ($transaction) {
                    $qtyBase = (int) ($line->qty ?? 0);
                    $qtyReceived = (int) ($line->qty_received ?? $qtyBase);

                    return [
                        'transaction_id' => (int) $transaction->id,
                        'code' => (string) $transaction->code,
                        'ref_no' => (string) ($transaction->ref_no ?? ''),
                        'transacted_at' => $transaction->transacted_at,
                        'created_at' => $transaction->created_at,
                        'status' => (string) ($transaction->status ?? 'pending'),
                        'approved_at' => $transaction->approved_at,
                        'warehouse_id' => (int) ($transaction->warehouse_id ?? 0),
                        'warehouse' => (string) ($transaction->warehouse?->name ?? '-'),
                        'warehouse_type' => (string) ($transaction->warehouse?->type ?? '-'),
                        'submitted_by' => (string) ($transaction->creator?->name ?? '-'),
                        'approved_by' => (string) ($transaction->approver?->name ?? '-'),
                        'transaction_note' => (string) ($transaction->note ?? ''),
                        'line_id' => (int) $line->id,
                        'item_id' => (int) ($line->item_id ?? 0),
                        'sku' => (string) ($line->item?->sku ?? '-'),
                        'item_name' => (string) ($line->item?->name ?? 'Item tidak ditemukan'),
                        'base_unit' => (string) ($line->item?->baseUnit?->name ?? 'PCS/SET'),
                        'qty_input' => (int) ($line->qty_input ?: $qtyBase),
                        'input_unit' => (string) ($line->unit?->name ?? $line->item?->baseUnit?->name ?? 'PCS/SET'),
                        'conversion_qty' => (int) ($line->conversion_qty ?: 1),
                        'qty_base' => $qtyBase,
                        'qty_received' => $qtyReceived,
                        'qty_good' => (int) ($line->qty_good ?? $qtyReceived),
                        'qty_damaged' => (int) ($line->qty_damaged ?? 0),
                        'qty_missing' => (int) ($line->qty_missing ?? 0),
                        'item_note' => (string) ($line->note ?? ''),
                    ];
                });
            })
            ->values();
    }
}
