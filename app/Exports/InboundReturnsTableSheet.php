<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class InboundReturnsTableSheet extends DefaultValueBinder implements FromCollection, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    abstract protected function lastColumn(): string;

    abstract protected function widths(): array;

    protected function freezePane(): string
    {
        return 'A2';
    }

    protected function wrapColumns(): array
    {
        return [];
    }

    protected function highlightRows(Worksheet $sheet, int $lastRow): void {}

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $this->lastColumn();
        $lastRow = max(1, $this->collection()->count() + 1);

        $sheet->freezePane($this->freezePane());
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");

        foreach ($this->widths() as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(44);

        foreach ($this->wrapColumns() as $column) {
            $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getAlignment()->setWrapText(true);
        }

        $this->highlightRows($sheet, $lastRow);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.3)->setRight(0.2)->setBottom(0.3)->setLeft(0.2);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

        return [];
    }
}
