<?php

namespace App\Exports;

use App\Models\Item;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ItemStocksExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private string $search = '')
    {
    }

    public function collection(): Collection
    {
        $query = Item::with('stock')->orderBy('name');
        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        return $query->get();
    }

    public function headings(): array
    {
        return ['ID', 'SKU', 'Nama', 'Stok'];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->sku,
            $row->name,
            (int) ($row->stock?->stock ?? 0),
        ];
    }
}
