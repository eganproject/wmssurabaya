<?php

namespace App\Exports;

use App\Models\Warehouse;
use App\Support\ItemStockOverview;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemStocksExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, ShouldAutoSize
{
    private Collection $warehouses;

    public function __construct(
        private string $search = '',
        private string $activeStatus = '1'
    )
    {
        $this->warehouses = ItemStockOverview::warehouses();
    }

    public function collection(): Collection
    {
        $query = ItemStockOverview::query($this->warehouses, $this->activeStatus);
        ItemStockOverview::applySearch($query, $this->warehouses, $this->search);

        return ItemStockOverview::rows($query->get(), $this->warehouses);
    }

    public function headings(): array
    {
        $headings = ['ID', 'SKU', 'Nama', 'Status Produk', 'Jenis', 'Satuan Dasar', 'Satuan Koli', 'Isi per Koli'];

        foreach ($this->warehouses as $warehouse) {
            $name = $warehouse->name;
            $headings[] = "{$name} - Lokasi";
            $headings[] = "{$name} - Safety Stock";
            $headings[] = "{$name} - Stok (Satuan Dasar)";
            if ($warehouse->type === Warehouse::TYPE_BULK) {
                $headings[] = "{$name} - Stok (Koli)";
                $headings[] = "{$name} - Sisa (Satuan Dasar)";
            }
            $headings[] = "{$name} - Status";
        }

        $headings[] = 'Total Stok (Satuan Dasar)';

        return $headings;
    }

    public function map($row): array
    {
        $values = [
            $row['id'],
            $row['sku'],
            $row['name'],
            $row['is_active'] ? 'Aktif' : 'Nonaktif',
            $row['is_bundle'] ? 'Bundle' : 'Reguler',
            $row['base_unit'],
            $row['package_unit'],
            $row['package_unit'] ? $row['package_conversion'] : null,
        ];

        foreach ($this->warehouses as $warehouse) {
            $stock = $row['warehouses'][$warehouse->id];
            $values[] = $stock['location'];
            $values[] = $stock['safety_stock'];
            $values[] = $stock['stock'];
            if ($warehouse->type === Warehouse::TYPE_BULK) {
                $values[] = $stock['package_qty'];
                $values[] = $stock['package_remainder'];
            }
            $values[] = ItemStockOverview::statusLabel($stock['stock_status']);
        }

        $values[] = $row['total_stock'];

        return $values;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('D2');
                $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
            },
        ];
    }
}
