<?php

namespace App\Exports;

use App\Support\ItemBulkUpdateFields;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemsBulkUpdateGuideSheet implements FromArray, WithStyles, WithTitle
{
    private const TABLE_START_ROW = 10;

    public function __construct(private readonly array $fields) {}

    public function title(): string
    {
        return 'Panduan';
    }

    public function array(): array
    {
        $definitions = ItemBulkUpdateFields::all();

        $rows = [
            ['Panduan Update Massal Master Item'],
            ['Isi sheet "Update Item", lalu upload kembali file ini melalui menu Master Item → Update Massal.'],
            [''],
            ['1. SKU adalah kunci pencarian item dan TIDAK dapat diubah. Baris dengan SKU yang tidak terdaftar akan ditolak.'],
            ['2. Hanya kolom yang tercantum di bawah yang akan diperbarui. Field lain (termasuk satuan kemasan/koli dan isi per koli) tidak berubah.'],
            ['3. Jangan mengubah atau menghapus baris header. Kolom tambahan di luar daftar akan diabaikan.'],
            ['4. Jika ada satu baris yang tidak valid, seluruh import dibatalkan sehingga data tetap konsisten.'],
            ['5. Hapus baris item yang tidak ingin diubah agar proses lebih cepat (opsional).'],
            [''],
            ['Kolom', 'Field', 'Ketentuan'],
            [ItemBulkUpdateFields::KEY_COLUMN, 'SKU (kunci)', 'Wajib diisi dan harus sudah terdaftar. Tidak ikut diperbarui.'],
        ];

        if (!in_array('name', $this->fields, true)) {
            $rows[] = [ItemBulkUpdateFields::REFERENCE_COLUMN, 'Nama Item (referensi)', 'Hanya informasi, diabaikan saat import.'];
        }

        foreach ($this->fields as $field) {
            $rows[] = [$field, $definitions[$field]['label'], $definitions[$field]['hint']];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = self::TABLE_START_ROW + 1 + count($this->fields)
            + (in_array('name', $this->fields, true) ? 0 : 1);

        $sheet->getColumnDimension('A')->setWidth(32);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(80);

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('5E6278');
        $sheet->getStyle('A4:A8')->getFont()->getColor()->setRGB('252F4A');

        $headerRange = 'A'.self::TABLE_START_ROW.':C'.self::TABLE_START_ROW;
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B84FF']],
        ]);
        $tableRange = 'A'.self::TABLE_START_ROW.":C{$lastRow}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DBDFE9');
        $sheet->getStyle($tableRange)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A'.(self::TABLE_START_ROW + 1).":A{$lastRow}")->getFont()->setName('Consolas');

        return [];
    }
}
