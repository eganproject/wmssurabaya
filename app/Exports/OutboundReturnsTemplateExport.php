<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OutboundReturnsTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    public function headings(): array
    {
        return [
            'sku',
            'qty',
            'ref_no',
            'note',
            'item_note',
            'transacted_at',
        ];
    }

    public function array(): array
    {
        $samples = Item::orderBy('name')->limit(2)->pluck('sku')->all();
        $now = now()->format('Y-m-d H:i');
        $defaults = $samples ?: ['SKU-CONTOH-1', 'SKU-CONTOH-2'];

        return [
            [$defaults[0], 4, 'RET-001', 'Retur outbound dari customer', 'Barang dikembalikan', $now],
            // ref_no sama -> baris ini tergabung dalam 1 transaksi retur
            [$defaults[1] ?? $defaults[0], 2, 'RET-001', '', '', $now],
        ];
    }

    public function title(): string
    {
        return 'Template Retur Outbound';
    }
}
