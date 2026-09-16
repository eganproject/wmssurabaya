<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QcPerformanceDataSheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    public function __construct(
        private readonly Collection $rows,
        private readonly string $sheetTitle,
        private readonly string $type,
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        return [$this->headings(), ...$this->mappedRows()->all()];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = match ($this->type) {
            'user' => 'O', 'daily' => 'Q', 'hourly' => 'M', default => 'O',
        };
        $lastRow = max(1, $this->rows->count() + 1);
        $sheet->freezePane('C2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(38);
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setWidth(17);
        }
        $sheet->getColumnDimension('B')->setWidth(28);
        if ($this->type === 'detail') {
            $sheet->getColumnDimension('D')->setWidth(27);
            $sheet->getColumnDimension('E')->setWidth(23);
        }
        if ($lastRow >= 2) {
            $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
            foreach ($this->percentageColumns() as $column) {
                $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('0.0%');
            }
        }
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);

        return [];
    }

    private function headings(): array
    {
        return match ($this->type) {
            'user' => ['Petugas QC', 'Divisi', 'Hari Aktif', 'Jam Aktif', 'Total Resi', 'QC Selesai', 'Completion', 'SKU Lines', 'Qty Wajib', 'Qty Scan', 'Kesesuaian Qty', 'Resi/Jam Aktif', 'Qty/Jam Aktif', 'Rata-rata Durasi (Menit)', 'Peringkat Produktivitas'],
            'daily' => ['Tanggal', 'Petugas QC', 'Divisi', 'Jam Aktif', 'Total Resi', 'QC Selesai', 'Belum Selesai', 'Completion', 'SKU Lines', 'Qty Wajib', 'Qty Scan', 'Kesesuaian Qty', 'Resi/Jam Aktif', 'Qty/Jam Aktif', 'Jam Tersibuk', 'Resi di Jam Tersibuk', 'Rata-rata Durasi (Menit)'],
            'hourly' => ['Tanggal', 'Petugas QC', 'Divisi', 'Jam', 'Total Resi', 'QC Selesai', 'Belum Selesai', 'Completion', 'SKU Lines', 'Qty Wajib', 'Qty Scan', 'Kesesuaian Qty', 'Rata-rata Durasi (Menit)'],
            default => ['Waktu Mulai', 'Waktu Selesai', 'Petugas QC', 'Divisi', 'No. Resi', 'ID Pesanan', 'Status', 'SKU Lines', 'Qty Wajib', 'Qty Scan', 'Kesesuaian Qty', 'Durasi QC (Menit)', 'Tanggal', 'Jam', 'ID QC'],
        };
    }

    private function mappedRows(): Collection
    {
        return match ($this->type) {
            'user' => $this->rows->values()->map(fn ($row, int $index) => [
                $row->petugas, $row->divisi, $row->active_days, $row->active_hours, $row->total_resi,
                $row->completed_resi, $row->completion_pct / 100, $row->sku_lines, $row->required_qty,
                $row->scanned_qty, $row->scan_pct / 100, $row->resi_per_hour, $row->qty_per_hour,
                $row->avg_cycle_minutes, $index + 1,
            ]),
            'daily' => $this->rows->map(fn ($row) => [
                $row->report_date, $row->petugas, $row->divisi, $row->active_hours, $row->total_resi,
                $row->completed_resi, $row->pending_resi, $row->completion_pct / 100, $row->sku_lines,
                $row->required_qty, $row->scanned_qty, $row->scan_pct / 100, $row->resi_per_hour,
                $row->qty_per_hour, $row->peak_hour ?: '-', $row->peak_hour_resi, $row->avg_cycle_minutes,
            ]),
            'hourly' => $this->rows->map(function ($row) {
                $total = (int) $row->total_resi;
                $required = (int) $row->required_qty;
                return [
                    $row->report_date, $row->petugas, $row->divisi, $row->hour_label, $total,
                    (int) $row->completed_resi, (int) $row->pending_resi,
                    $total > 0 ? (int) $row->completed_resi / $total : 0,
                    (int) $row->sku_lines, $required, (int) $row->scanned_qty,
                    $required > 0 ? (int) $row->scanned_qty / $required : 0,
                    $row->avg_cycle_minutes !== null ? round((float) $row->avg_cycle_minutes, 1) : null,
                ];
            }),
            default => $this->rows->map(function ($row) {
                $required = (int) $row->required_qty;
                $scannedAt = $row->scanned_at ? new \DateTime($row->scanned_at) : null;
                return [
                    $scannedAt?->format('d/m/Y H:i:s') ?? '-',
                    $row->completed_at ? (new \DateTime($row->completed_at))->format('d/m/Y H:i:s') : '-',
                    $row->petugas, $row->divisi, $row->no_resi, $row->id_pesanan,
                    $row->status === 'completed' ? 'Selesai' : 'Belum lengkap',
                    (int) $row->sku_lines, $required, (int) $row->scanned_qty,
                    $required > 0 ? (int) $row->scanned_qty / $required : 0,
                    $row->cycle_minutes !== null ? round((float) $row->cycle_minutes, 1) : null,
                    $scannedAt?->format('Y-m-d') ?? '-', $scannedAt?->format('H:00') ?? '-', (int) $row->id,
                ];
            }),
        };
    }

    private function percentageColumns(): array
    {
        return match ($this->type) {
            'user' => ['G', 'K'],
            'daily' => ['H', 'L'],
            'hourly' => ['H', 'L'],
            default => ['K'],
        };
    }
}
