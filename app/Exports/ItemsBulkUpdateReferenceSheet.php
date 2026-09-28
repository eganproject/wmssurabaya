<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sumber dropdown kategori (kolom A) dan UOM (kolom C) pada sheet "Update Item".
 * Posisi kolom dipakai langsung oleh formula validasi di ItemsBulkUpdateDataSheet.
 */
class ItemsBulkUpdateReferenceSheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    public function __construct(
        private readonly Collection $categories,
        private readonly Collection $uoms,
    ) {}

    public function title(): string
    {
        return 'Referensi';
    }

    public function array(): array
    {
        $rows = [['Kategori', '', 'Kode UOM', 'Nama UOM']];
        $count = max($this->categories->count(), $this->uoms->count());

        for ($i = 0; $i < $count; $i++) {
            $uom = $this->uoms->get($i);
            $rows[] = [
                (string) ($this->categories->get($i) ?? ''),
                '',
                (string) ($uom?->code ?? ''),
                (string) ($uom?->name ?? ''),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(4);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(32);
        foreach (['A1', 'C1:D1'] as $range) {
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5E6278']],
            ]);
        }
        $sheet->freezePane('A2');

        return [];
    }
}
