<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockOpnameReportSummarySheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $filters = [])
    {
    }

    public function title(): string
    {
        return 'Ringkasan Harian';
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Total Batch',
            'Jumlah SKU',
            'SKU Selisih',
            'Akurasi (%)',
        ];
    }

    public function collection(): Collection
    {
        $baseQuery = DB::table('stock_opnames as so')
            ->join('stock_opname_items as soi', 'soi.stock_opname_id', '=', 'so.id')
            ->where('so.status', 'completed');

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $baseQuery->whereRaw('DATE(so.transacted_at) like ?', ["%{$search}%"]);
        }

        $this->applyDateFilter($baseQuery);

        $rows = $baseQuery
            ->selectRaw('DATE(so.transacted_at) as report_date')
            ->selectRaw('COUNT(DISTINCT so.id) as batch_count')
            ->selectRaw('COUNT(DISTINCT soi.item_id) as sku_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN soi.adjustment <> 0 THEN soi.item_id END) as diff_sku_count')
            ->groupBy('report_date')
            ->orderBy('report_date', 'desc')
            ->get();

        return $rows->map(function ($row) {
            $skuCount = (int) $row->sku_count;
            $diffCount = (int) $row->diff_sku_count;
            $accuracy = $skuCount > 0 ? (($skuCount - $diffCount) / $skuCount) * 100 : 100;
            return [
                $row->report_date,
                (int) $row->batch_count,
                $skuCount,
                $diffCount,
                number_format($accuracy, 2),
            ];
        });
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function applyDateFilter($query): void
    {
        $dateFrom = $this->filters['date_from'] ?? null;
        $dateTo = $this->filters['date_to'] ?? null;

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('so.transacted_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('so.transacted_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }
}
