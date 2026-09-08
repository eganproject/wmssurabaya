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
    public function __construct(
        private string $search = '',
        private ?int $warehouseId = null,
        private string $activeStatus = '1'
    )
    {
    }

    public function collection(): Collection
    {
        $warehouseId = $this->warehouseId;
        $query = Item::with([
            'stocks' => fn ($q) => $q->where('warehouse_id', $warehouseId),
            'warehouseSettings' => fn ($q) => $q->where('warehouse_id', $warehouseId),
        ])->orderBy('name');
        if (in_array($this->activeStatus, ['0', '1'], true)) {
            $query->where('is_active', (int) $this->activeStatus === 1);
        }
        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search, $warehouseId) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('warehouseSettings', fn ($settings) => $settings
                        ->where('warehouse_id', $warehouseId)
                        ->where('location', 'like', "%{$search}%"))
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        return $query->get();
    }

    public function headings(): array
    {
        return ['ID', 'SKU', 'Nama', 'Status Produk', 'Lokasi', 'Safety Stock', 'Stok'];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->sku,
            $row->name,
            $row->is_active ? 'Aktif' : 'Nonaktif',
            $row->warehouseSettings->first()?->location,
            (int) ($row->warehouseSettings->first()?->safety_stock ?? 0),
            (int) ($row->stocks->first()?->stock ?? 0),
        ];
    }
}
