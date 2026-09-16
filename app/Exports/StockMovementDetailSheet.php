<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMovementDetailSheet extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    public function __construct(private readonly Collection $rows) {}

    public function title(): string
    {
        return 'Detail SKU';
    }

    public function headings(): array
    {
        return [
            'SKU', 'Nama Item', 'Status Produk', 'Kategori', 'Gudang', 'Tipe Gudang', 'Lokasi', 'Klasifikasi',
            'Stok Saat Ini', 'Safety Stock', 'Gap ke Safety', 'Satuan', 'Qty Keluar', 'Rata-rata / Hari',
            'Kontribusi (%)', 'Transaksi Keluar', 'Hari Aktif', 'Days Cover', 'Terakhir Keluar', 'Rekomendasi Tindakan',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows
            ->sortBy([['movement_order', 'asc'], ['outbound_qty', 'desc'], ['sku', 'asc']])
            ->values()
            ->map(fn (array $row) => [
                $row['sku'],
                $row['name'],
                $row['is_active'] ? 'Aktif' : 'Nonaktif',
                $row['category'],
                $row['warehouse'],
                $row['warehouse_type'],
                $row['location'],
                $row['movement_label'],
                $row['stock'],
                $row['safety_stock'],
                $row['gap_to_safety'],
                $row['base_unit'],
                $row['outbound_qty'],
                $row['average_daily_outbound'],
                $row['contribution_percent'] / 100,
                $row['outbound_transactions'],
                $row['active_days'],
                $row['days_cover'],
                $row['last_outbound_at'] ? date('d/m/Y H:i', strtotime($row['last_outbound_at'])) : '-',
                $row['recommended_action'],
            ]);
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->rows->count() + 1);
        $sheet->freezePane('I2');
        $sheet->setAutoFilter("A1:T{$lastRow}");
        $sheet->getColumnDimension('B')->setWidth(38);
        $sheet->getColumnDimension('T')->setWidth(54);
        foreach (['D', 'E', 'F', 'G', 'H'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(22);
        }
        if ($lastRow >= 2) {
            $sheet->getStyle("I2:M{$lastRow}")->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
            $sheet->getStyle("N2:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("O2:O{$lastRow}")->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyle("P2:R{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
            $sheet->getStyle("A2:T{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("T2:T{$lastRow}")->getAlignment()->setWrapText(true);
        }
        $sheet->getStyle("A1:T{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle('A1:T1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(38);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);

        return [];
    }
}
