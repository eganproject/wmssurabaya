<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMutationDailySummarySheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    private ?Collection $rows = null;

    public function __construct(private readonly Collection $mutations) {}

    public function title(): string
    {
        return 'Rekap Harian';
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Gudang',
            'Jumlah Mutasi',
            'Jumlah SKU',
            'Dokumen Referensi',
            'Total Masuk',
            'Total Keluar',
            'Mutasi Bersih',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows ??= $this->mutations
            ->groupBy(function ($mutation) {
                $date = $mutation->occurred_at?->format('Y-m-d') ?? '-';

                return $date.'|'.($mutation->warehouse_id ?: 0);
            })
            ->map(function (Collection $group) {
                $incoming = (int) $group->where('direction', 'in')->sum('qty');
                $outgoing = (int) $group->where('direction', 'out')->sum('qty');
                $first = $group->first();

                return [
                    $first?->occurred_at?->format('d/m/Y') ?? '-',
                    $first?->warehouse?->name ?? '-',
                    $group->count(),
                    $group->pluck('item_id')->filter()->unique()->count(),
                    $group->map(fn ($mutation) => ($mutation->source_type ?: '-').'|'.($mutation->source_id ?: 0))->unique()->count(),
                    $incoming,
                    $outgoing,
                    $incoming - $outgoing,
                ];
            })
            ->values();
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->collection()->count() + 1);
        $sheet->freezePane('C2');
        $sheet->setAutoFilter("A1:H{$lastRow}");
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(26);
        foreach (range('C', 'H') as $column) {
            $sheet->getColumnDimension($column)->setWidth(18);
        }
        $sheet->getStyle("C2:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
        $sheet->getStyle("A1:H{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle('A1:H1')->applyFromArray($this->headerStyle());
        $sheet->getRowDimension(1)->setRowHeight(30);

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
