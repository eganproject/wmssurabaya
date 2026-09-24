<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockForecastTableSheet extends DefaultValueBinder implements FromArray, WithColumnFormatting, WithCustomValueBinder, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $headings,
        private readonly Collection $rows,
        private readonly array $formats = [],
        private readonly array $widths = [],
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        return [$this->headings, ...$this->rows->values()->all()];
    }

    public function columnFormats(): array
    {
        return $this->formats;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->rows->count() + 1);
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings));

        $sheet->freezePane('B2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        $sheet->setShowGridlines(false);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
            ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(38);

        foreach ($this->headings as $index => $heading) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $width = $this->widths[$column] ?? match (true) {
                str_contains($heading, 'Nama') => 36,
                str_contains($heading, 'Kategori') => 28,
                str_contains($heading, 'Catatan') => 48,
                str_contains($heading, 'Tanggal') => 18,
                str_contains($heading, 'Sumber') => 22,
                default => 16,
            };
            $sheet->getColumnDimension($column)->setWidth($width);

            if (in_array($heading, ['Nama Item', 'Catatan Analisis', 'Interpretasi'], true) && $lastRow >= 2) {
                $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getAlignment()->setWrapText(true);
            }

            if (in_array($heading, ['Tindakan', 'Kualitas Data'], true)) {
                $this->styleStatusColumn($sheet, $column, $lastRow);
            }
        }

        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.4)->setLeft(0.3);

        return [];
    }

    private function styleStatusColumn(Worksheet $sheet, string $column, int $lastRow): void
    {
        for ($row = 2; $row <= $lastRow; $row++) {
            $value = (string) $sheet->getCell("{$column}{$row}")->getValue();
            $color = match ($value) {
                'Order Sekarang', 'Rendah' => 'F4CCCC',
                'Jadwalkan', 'Cukup' => 'FFF2CC',
                'Tercukupi', 'Baik' => 'D9EAD3',
                default => 'E7E6E6',
            };
            $sheet->getStyle("{$column}{$row}")
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        }
    }
}
