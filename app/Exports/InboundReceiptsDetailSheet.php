<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InboundReceiptsDetailSheet extends DefaultValueBinder implements FromCollection, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    private ?Collection $rows = null;

    public function __construct(private readonly Collection $itemRows) {}

    public function title(): string
    {
        return 'Detail Penerimaan';
    }

    public function headings(): array
    {
        return [
            'No.',
            'Kode Penerimaan',
            'Tanggal Transaksi',
            'Status',
            'Gudang',
            'Tipe Gudang',
            'Ref No',
            'SKU',
            'Nama Item',
            'Qty Input',
            'Satuan Input',
            'Konversi ke Dasar',
            'Qty Dasar',
            'Qty Diterima',
            'Satuan Dasar',
            'Submit Oleh',
            'Waktu Pencatatan',
            'Disetujui Oleh',
            'Waktu Persetujuan',
            'Durasi Persetujuan (Jam)',
            'Catatan Transaksi',
            'Catatan Item',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows ??= $this->itemRows
            ->sortByDesc(fn (array $row) => ($row['transacted_at']?->format('Y-m-d H:i:s.u') ?? '').'-'.str_pad((string) $row['line_id'], 20, '0', STR_PAD_LEFT))
            ->values()
            ->map(function (array $row, int $index) {
                $approvalHours = $row['created_at'] && $row['approved_at']
                    ? $row['created_at']->diffInMinutes($row['approved_at']) / 60
                    : null;

                return [
                    $index + 1,
                    $row['code'],
                    $row['transacted_at'] ? Date::dateTimeToExcel($row['transacted_at']) : null,
                    $this->statusLabel($row['status']),
                    $row['warehouse'],
                    $this->warehouseTypeLabel($row['warehouse_type']),
                    $row['ref_no'] ?: '-',
                    $row['sku'],
                    $row['item_name'],
                    $row['qty_input'],
                    $row['input_unit'],
                    $row['conversion_qty'],
                    $row['qty_base'],
                    $row['qty_received'],
                    $row['base_unit'],
                    $row['submitted_by'],
                    $row['created_at'] ? Date::dateTimeToExcel($row['created_at']) : null,
                    $row['approved_by'],
                    $row['approved_at'] ? Date::dateTimeToExcel($row['approved_at']) : null,
                    $approvalHours,
                    $row['transaction_note'] ?: '-',
                    $row['item_note'] ?: '-',
                ];
            });
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => 'dd/mm/yyyy hh:mm',
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'J' => '#,##0',
            'L' => '#,##0',
            'M' => '#,##0',
            'N' => '#,##0',
            'Q' => 'dd/mm/yyyy hh:mm',
            'S' => 'dd/mm/yyyy hh:mm',
            'T' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->collection()->count() + 1);
        $sheet->freezePane('H2');
        $sheet->setAutoFilter("A1:V{$lastRow}");

        $widths = [
            'A' => 8, 'B' => 27, 'C' => 21, 'D' => 21, 'E' => 28, 'F' => 17, 'G' => 22,
            'H' => 18, 'I' => 38, 'J' => 14, 'K' => 17, 'L' => 19, 'M' => 15, 'N' => 16,
            'O' => 17, 'P' => 23, 'Q' => 21, 'R' => 23, 'S' => 21, 'T' => 23, 'U' => 42, 'V' => 42,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle("A1:V{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A1:V{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("I2:I{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("U2:V{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1:V1')->applyFromArray($this->headerStyle());
        $sheet->getRowDimension(1)->setRowHeight(44);

        for ($row = 2; $row <= $lastRow; $row++) {
            $approved = $sheet->getCell("D{$row}")->getValue() === 'Disetujui';
            $sheet->getStyle("D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($approved ? 'E2F0D9' : 'FFF2CC');
            $sheet->getStyle("N{$row}")->getFont()->setBold(true)->getColor()->setRGB($approved ? '008000' : 'C65911');
        }

        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.3)->setRight(0.2)->setBottom(0.3)->setLeft(0.2);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

        return [];
    }

    private function statusLabel(string $status): string
    {
        return $status === 'approved' ? 'Disetujui' : 'Menunggu Persetujuan';
    }

    private function warehouseTypeLabel(string $type): string
    {
        return match ($type) {
            'bulk' => 'Gudang Besar',
            'small' => 'Gudang Kecil',
            default => $type ?: '-',
        };
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
