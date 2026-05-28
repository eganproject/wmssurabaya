<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class InboundReturnsTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    public function headings(): array
    {
        return [
            'sku',
            'qty_diterima',
            'qty_bagus',
            'qty_rusak',
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

        $rows = [];
        $defaults = $samples ?: ['SKU-CONTOH-1', 'SKU-CONTOH-2'];

        // Baris 1: ada barang bagus dan rusak
        $rows[] = [$defaults[0], 10, 7, 3, 'RET-001', 'Retur dari customer', 'Sebagian kemasan rusak', $now];
        // Baris 2: semua barang bagus, ref_no sama agar tergabung dalam 1 transaksi
        $rows[] = [$defaults[1] ?? $defaults[0], 5, 5, 0, 'RET-001', '', '', $now];

        return $rows;
    }

    public function title(): string
    {
        return 'Template Retur Inbound';
    }
}
