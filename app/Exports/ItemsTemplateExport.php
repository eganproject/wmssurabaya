<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ItemsTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'sku',
            'name',
            'parent_category',
            'category',
            'base_unit',
            'package_unit',
            'package_conversion_qty',
            'small_warehouse_stock',
            'large_warehouse_stock',
            'small_warehouse_safety_stock',
            'small_warehouse_location',
            'large_warehouse_safety_stock',
            'large_warehouse_location',
            'description',
        ];
    }

    public function array(): array
    {
        return [
            [
                'SKU-001',
                'Produk A',
                'Produk',
                'Kategori A',
                'PCS',
                'KOLI',
                24,
                100,
                10,
                20,
                'Rak Kecil A-01',
                5,
                'Blok Besar B-01',
                'Contoh: Gudang Kecil 100 PCS, Gudang Besar 10 KOLI = 240 PCS',
            ],
        ];
    }
}
