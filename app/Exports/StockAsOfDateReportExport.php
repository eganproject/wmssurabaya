<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockAsOfDateReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private Collection $rows, private string $asOfDate)
    {
    }

    public function title(): string
    {
        return 'Stok Akhir Harian';
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Per Tanggal', 'Gudang', 'SKU', 'Nama Item', 'Kategori', 'Lokasi',
            'Stok Akhir', 'Satuan', 'Qty Kemasan', 'Sisa Kemasan', 'Satuan Kemasan',
            'Safety Stock', 'Gap Safety', 'Status',
        ];
    }

    public function map($row): array
    {
        return [
            $this->asOfDate,
            $row['warehouse'],
            $row['sku'],
            $row['name'],
            $row['category'],
            $row['location'],
            (int) $row['stock'],
            $row['base_unit'],
            $row['package_qty'],
            $row['package_remainder'],
            $row['package_unit'],
            (int) $row['safety_stock'],
            (int) $row['gap_to_safety'],
            $row['status_label'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:N1');
        $sheet->getStyle('A1:N1')->getAlignment()->setHorizontal('center');
        $sheet->getColumnDimension('D')->setWidth(34);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(16);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E78']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ],
        ];
    }
}
