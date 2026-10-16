<?php

namespace App\Exports;

use App\Models\Item;
use App\Models\Store;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ResiTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    public function headings(): array
    {
        return [
            'ID Pesanan',
            'AWB/No. Tracking',
            'Kurir',
            'SKU',
            'Jumlah',
            'Tanggal Pembuatan',
            'Catatan Pembeli',
            'Toko',
        ];
    }

    public function array(): array
    {
        $samples = Item::orderBy('name')->limit(2)->pluck('sku')->all();
        $defaults = $samples ?: ['SKU-CONTOH-1', 'SKU-CONTOH-2'];
        $date = now()->format('Y-m-d');
        $store = Store::orderBy('name')->value('name') ?? 'Nama Toko';

        return [
            ['ORD-001', 'RESI001', 'JNE', $defaults[0], 2, $date, 'Tolong packing aman', $store],
            ['ORD-001', 'RESI001', 'JNE', $defaults[1] ?? $defaults[0], 1, $date, '', $store],
        ];
    }

    public function title(): string
    {
        return 'Template Resi';
    }
}
