<?php

namespace App\Exports;

use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockOpnameDetailExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private StockOpname $opname)
    {
    }

    public function collection(): Collection
    {
        return $this->opname->items()
            ->with(['item:id,sku,name', 'creator:id,name'])
            ->orderBy('id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Nama Item',
            'System Qty',
            'Counted Qty',
            'Adjustment',
            'Catatan',
            'Input Oleh',
        ];
    }

    public function map($row): array
    {
        /** @var StockOpnameItem $row */
        return [
            $row->item?->sku ?? '-',
            $row->item?->name ?? '-',
            (int) $row->system_qty,
            (int) $row->counted_qty,
            (int) $row->adjustment,
            $row->note ?? '',
            $row->creator?->name ?? '-',
        ];
    }
}
