<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InboundReturnsDetailSheet extends InboundReturnsTableSheet
{
    private ?Collection $rows = null;

    public function __construct(private readonly Collection $itemRows) {}

    public function title(): string
    {
        return 'Detail Retur';
    }

    public function headings(): array
    {
        return [
            'No.', 'Kode Retur', 'Tanggal Transaksi', 'Status', 'No. Resi / Ref', 'Gudang',
            'SKU', 'Nama Item', 'Qty Menurut Resi', 'Qty Diterima', 'Qty Bagus', 'Qty Rusak',
            'Qty Hilang', 'Tingkat Penerimaan', 'Tingkat Bagus', 'Tingkat Rusak', 'Tingkat Hilang',
            'Satuan Dasar', 'Submit Oleh', 'Waktu Pencatatan', 'Disetujui Oleh', 'Waktu Disetujui',
            'Finalisasi Oleh', 'Waktu Finalisasi', 'Durasi Proses (Jam)', 'Catatan Transaksi', 'Catatan Item',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows ??= $this->itemRows
            ->sortByDesc(fn (array $row) => ($row['transacted_at']?->format('Y-m-d H:i:s.u') ?? '').'-'.str_pad((string) $row['line_id'], 20, '0', STR_PAD_LEFT))
            ->values()
            ->map(function (array $row, int $index) {
                $expected = $row['qty_expected'];
                $processHours = $row['created_at'] && $row['finalized_at']
                    ? $row['created_at']->diffInMinutes($row['finalized_at']) / 60
                    : null;

                return [
                    $index + 1,
                    $row['code'],
                    $row['transacted_at'] ? Date::dateTimeToExcel($row['transacted_at']) : null,
                    $this->statusLabel($row['status']),
                    $row['ref_no'] ?: '-',
                    $row['warehouse'],
                    $row['sku'],
                    $row['item_name'],
                    $expected,
                    $row['qty_received'],
                    $row['qty_good'],
                    $row['qty_damaged'],
                    $row['qty_missing'],
                    $expected > 0 ? $row['qty_received'] / $expected : 0,
                    $expected > 0 ? $row['qty_good'] / $expected : 0,
                    $expected > 0 ? $row['qty_damaged'] / $expected : 0,
                    $expected > 0 ? $row['qty_missing'] / $expected : 0,
                    $row['base_unit'],
                    $row['submitted_by'],
                    $row['created_at'] ? Date::dateTimeToExcel($row['created_at']) : null,
                    $row['approved_by'],
                    $row['approved_at'] ? Date::dateTimeToExcel($row['approved_at']) : null,
                    $row['finalized_by'],
                    $row['finalized_at'] ? Date::dateTimeToExcel($row['finalized_at']) : null,
                    $processHours,
                    $row['transaction_note'] ?: '-',
                    $row['item_note'] ?: '-',
                ];
            });
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT, 'C' => 'dd/mm/yyyy hh:mm',
            'E' => NumberFormat::FORMAT_TEXT, 'G' => NumberFormat::FORMAT_TEXT,
            'I' => '#,##0', 'J' => '#,##0', 'K' => '#,##0', 'L' => '#,##0', 'M' => '#,##0',
            'N' => '0.00%', 'O' => '0.00%', 'P' => '0.00%', 'Q' => '0.00%',
            'T' => 'dd/mm/yyyy hh:mm', 'V' => 'dd/mm/yyyy hh:mm',
            'X' => 'dd/mm/yyyy hh:mm', 'Y' => '#,##0.00',
        ];
    }

    protected function lastColumn(): string
    {
        return 'AA';
    }

    protected function widths(): array
    {
        return [
            'A' => 8, 'B' => 26, 'C' => 21, 'D' => 22, 'E' => 23, 'F' => 27,
            'G' => 18, 'H' => 38, 'I' => 19, 'J' => 16, 'K' => 15, 'L' => 15,
            'M' => 15, 'N' => 19, 'O' => 17, 'P' => 17, 'Q' => 17, 'R' => 16,
            'S' => 22, 'T' => 21, 'U' => 22, 'V' => 21, 'W' => 22, 'X' => 21,
            'Y' => 21, 'Z' => 42, 'AA' => 42,
        ];
    }

    protected function freezePane(): string
    {
        return 'G2';
    }

    protected function wrapColumns(): array
    {
        return ['H', 'Z', 'AA'];
    }

    protected function highlightRows(Worksheet $sheet, int $lastRow): void
    {
        for ($row = 2; $row <= $lastRow; $row++) {
            $status = (string) $sheet->getCell("D{$row}")->getValue();
            $statusColor = $status === 'Finalisasi' ? 'E2F0D9' : ($status === 'Area Retur / Belum Finalisasi' ? 'FFF2CC' : 'FCE4D6');
            $sheet->getStyle("D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statusColor);

            if ((int) $sheet->getCell("L{$row}")->getValue() + (int) $sheet->getCell("M{$row}")->getValue() > 0) {
                $sheet->getStyle("L{$row}:M{$row}")->getFont()->setBold(true)->getColor()->setRGB('C00000');
            }
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'finalized' => 'Finalisasi',
            'approved' => 'Area Retur / Belum Finalisasi',
            default => 'Menunggu',
        };
    }
}
