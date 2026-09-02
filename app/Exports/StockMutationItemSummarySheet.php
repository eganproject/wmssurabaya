<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMutationItemSummarySheet implements FromCollection, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    private ?Collection $rows = null;

    public function __construct(private readonly Collection $mutations) {}

    public function title(): string
    {
        return 'Rekap per Item';
    }

    public function headings(): array
    {
        return [
            'Gudang',
            'SKU',
            'Nama Item',
            'Satuan Dasar',
            'Jumlah Mutasi',
            'Total Masuk',
            'Total Keluar',
            'Mutasi Bersih',
            'Saldo Awal Periode',
            'Saldo Akhir Periode',
            'Selisih Kontrol',
            'Mutasi Pertama',
            'Mutasi Terakhir',
            'Status Saldo',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows ??= $this->mutations
            ->groupBy(fn ($mutation) => ($mutation->warehouse_id ?: 0).'|'.($mutation->item_id ?: 0))
            ->map(function (Collection $group) {
                $ordered = $group->sortBy(function ($mutation) {
                    return ($mutation->occurred_at?->format('Y-m-d H:i:s.u') ?? '').'-'.str_pad((string) $mutation->id, 20, '0', STR_PAD_LEFT);
                })->values();
                $first = $ordered->first();
                $last = $ordered->last();
                $incoming = (int) $group->where('direction', 'in')->sum('qty');
                $outgoing = (int) $group->where('direction', 'out')->sum('qty');
                $net = $incoming - $outgoing;
                $hasBalances = ! is_null($first?->stock_before) && ! is_null($last?->stock_after);
                $controlDifference = $hasBalances
                    ? (int) $last->stock_after - (int) $first->stock_before - $net
                    : null;

                return [
                    $first?->warehouse?->name ?? '-',
                    $first?->item?->sku ?? '-',
                    $first?->item?->name ?? 'Item tidak ditemukan',
                    $first?->item?->baseUnit?->name ?? 'Satuan dasar',
                    $group->count(),
                    $incoming,
                    $outgoing,
                    $net,
                    $hasBalances ? (int) $first->stock_before : null,
                    $hasBalances ? (int) $last->stock_after : null,
                    $controlDifference,
                    $first?->occurred_at ? Date::dateTimeToExcel($first->occurred_at) : null,
                    $last?->occurred_at ? Date::dateTimeToExcel($last->occurred_at) : null,
                    ! $hasBalances ? 'Data saldo tidak lengkap' : ($controlDifference === 0 ? 'OK' : 'Perlu diperiksa'),
                ];
            })
            ->sortBy(fn (array $row) => mb_strtolower($row[0].'|'.$row[1]))
            ->values();
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'E' => '#,##0',
            'F' => '#,##0',
            'G' => '#,##0',
            'H' => '#,##0;[Red]-#,##0',
            'I' => '#,##0;[Red]-#,##0',
            'J' => '#,##0;[Red]-#,##0',
            'K' => '#,##0;[Red]-#,##0',
            'L' => 'dd/mm/yyyy hh:mm',
            'M' => 'dd/mm/yyyy hh:mm',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->collection()->count() + 1);
        $sheet->freezePane('E2');
        $sheet->setAutoFilter("A1:N{$lastRow}");

        $widths = [
            'A' => 25, 'B' => 18, 'C' => 36, 'D' => 16, 'E' => 15,
            'F' => 16, 'G' => 16, 'H' => 16, 'I' => 19, 'J' => 19,
            'K' => 16, 'L' => 20, 'M' => 20, 'N' => 23,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle("A1:N{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A1:N{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C2:C{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1:N1')->applyFromArray($this->headerStyle());
        $sheet->getRowDimension(1)->setRowHeight(36);

        for ($row = 2; $row <= $lastRow; $row++) {
            $status = (string) $sheet->getCell("N{$row}")->getValue();
            $color = $status === 'OK' ? 'E2F0D9' : ($status === 'Perlu diperiksa' ? 'FCE4D6' : 'FFF2CC');
            $sheet->getStyle("N{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        }

        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.4)->setLeft(0.3);

        return [];
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
