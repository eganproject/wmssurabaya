<?php

namespace App\Exports;

use App\Support\StockMutationReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMutationDetailSheet extends DefaultValueBinder implements FromCollection, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    private ?Collection $rows = null;

    public function __construct(private readonly Collection $mutations) {}

    public function title(): string
    {
        return 'Detail Mutasi';
    }

    public function headings(): array
    {
        return [
            'No.',
            'No. Bukti Mutasi',
            'Waktu Mutasi',
            'Gudang',
            'SKU',
            'Nama Item',
            'Arah Mutasi',
            'Qty Input',
            'Satuan Input',
            'Konversi ke Dasar',
            'Qty Masuk (Dasar)',
            'Qty Keluar (Dasar)',
            'Mutasi Bersih',
            'Stok Sebelum',
            'Stok Sesudah',
            'Jenis Sumber',
            'Subjenis Sumber',
            'ID Sumber',
            'Kode Referensi',
            'Catatan',
            'Petugas',
            'Waktu Pencatatan',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows ??= $this->mutations
            ->values()
            ->map(function ($mutation, int $index) {
                $qty = (int) $mutation->qty;
                $incoming = $mutation->direction === 'in';

                return [
                    $index + 1,
                    'MUT-'.str_pad((string) $mutation->id, 7, '0', STR_PAD_LEFT),
                    $mutation->occurred_at ? Date::dateTimeToExcel($mutation->occurred_at) : null,
                    $mutation->warehouse?->name ?? '-',
                    $mutation->item?->sku ?? '-',
                    $mutation->item?->name ?? 'Item tidak ditemukan',
                    $incoming ? 'MASUK' : 'KELUAR',
                    (int) ($mutation->qty_input ?? $qty),
                    $mutation->unit?->name ?? $mutation->item?->baseUnit?->name ?? 'Satuan dasar',
                    (int) ($mutation->conversion_qty ?: 1),
                    $incoming ? $qty : 0,
                    $incoming ? 0 : $qty,
                    $incoming ? $qty : -$qty,
                    is_null($mutation->stock_before) ? null : (int) $mutation->stock_before,
                    is_null($mutation->stock_after) ? null : (int) $mutation->stock_after,
                    StockMutationReport::sourceTypeLabel($mutation->source_type),
                    StockMutationReport::sourceSubtypeLabel($mutation->source_subtype),
                    $mutation->source_id,
                    $mutation->source_code ?: '-',
                    $mutation->note ?: '-',
                    $mutation->creator?->name ?? '-',
                    $mutation->created_at ? Date::dateTimeToExcel($mutation->created_at) : null,
                ];
            });
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => 'dd/mm/yyyy hh:mm',
            'E' => NumberFormat::FORMAT_TEXT,
            'H' => '#,##0',
            'J' => '#,##0',
            'K' => '#,##0',
            'L' => '#,##0',
            'M' => '#,##0;[Red]-#,##0',
            'N' => '#,##0;[Red]-#,##0',
            'O' => '#,##0;[Red]-#,##0',
            'R' => NumberFormat::FORMAT_TEXT,
            'S' => NumberFormat::FORMAT_TEXT,
            'V' => 'dd/mm/yyyy hh:mm',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->collection()->count() + 1);
        $sheet->freezePane('F2');
        $sheet->setAutoFilter("A1:V{$lastRow}");

        $widths = [
            'A' => 8, 'B' => 19, 'C' => 20, 'D' => 25, 'E' => 18, 'F' => 38,
            'G' => 15, 'H' => 14, 'I' => 17, 'J' => 19, 'K' => 19, 'L' => 19,
            'M' => 16, 'N' => 16, 'O' => 16, 'P' => 24, 'Q' => 20, 'R' => 14,
            'S' => 23, 'T' => 42, 'U' => 24, 'V' => 20,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle("A1:V{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A1:V{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("F2:F{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("T2:T{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1:V1')->applyFromArray($this->headerStyle());
        $sheet->getRowDimension(1)->setRowHeight(42);

        for ($row = 2; $row <= $lastRow; $row++) {
            $incoming = $sheet->getCell("G{$row}")->getValue() === 'MASUK';
            $color = $incoming ? 'E2F0D9' : 'FCE4D6';
            $sheet->getStyle("G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            $sheet->getStyle(($incoming ? 'K' : 'L').$row)->getFont()->setBold(true)->getColor()->setRGB($incoming ? '008000' : 'C00000');
        }

        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.3)->setRight(0.2)->setBottom(0.3)->setLeft(0.2);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

        return [];
    }

    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value)) {
            $cell->setValueExplicit(StringHelper::sanitizeUTF8($value), DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    private function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
    }
}
