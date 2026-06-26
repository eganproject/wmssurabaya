<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class StockOpnamesTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    public function headings(): array
    {
        return [
            'sku',
            'counted_qty_input',
            'unit',
            'note',
            'item_note',
            'transacted_at',
        ];
    }

    public function array(): array
    {
        $samples = Item::orderBy('name')->limit(2)->pluck('sku')->all();
        $defaults = $samples ?: ['SKU-CONTOH-1', 'SKU-CONTOH-2'];
        $now = now()->format('Y-m-d H:i');

        return [
            [$defaults[0], 10, 'PCS', 'Stock opname gudang', 'Hasil hitung rak A', $now],
            [$defaults[1] ?? $defaults[0], 2, 'KOLI', 'Stock opname gudang', 'Hasil hitung area bulk', $now],
        ];
    }

    public function title(): string
    {
        return 'Template Opname';
    }
}
