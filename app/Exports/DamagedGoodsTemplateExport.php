<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class DamagedGoodsTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    public function headings(): array
    {
        return [
            'sku',
            'qty_input',
            'source_type',
            'source_ref',
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
            // source_type: display = stok normal pindah ke stok rusak
            [$defaults[0], 3, 'display', 'DSP-001', 'Barang rusak dari display', 'Penyok', $now],
            // source_ref sama -> baris ini tergabung dalam 1 transaksi barang rusak
            [$defaults[1] ?? $defaults[0], 2, 'display', 'DSP-001', '', '', $now],
        ];
    }

    public function title(): string
    {
        return 'Template Barang Rusak';
    }
}
