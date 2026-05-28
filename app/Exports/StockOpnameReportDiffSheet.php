<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockOpnameReportDiffSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $filters = [], private string $type = 'plus')
    {
    }

    public function title(): string
    {
        return $this->type === 'minus' ? 'SKU Selisih Kurang' : 'SKU Selisih Lebih';
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Nama',
            'Jumlah Selisih',
        ];
    }

    public function collection(): Collection
    {
        $isMinus = $this->type === 'minus';

        $query = DB::table('stock_opname_items as soi')
            ->join('stock_opnames as so', 'so.id', '=', 'soi.stock_opname_id')
            ->join('items as i', 'i.id', '=', 'soi.item_id')
            ->where('so.status', 'completed');

        if ($isMinus) {
            $query->where('soi.adjustment', '<', 0);
        } else {
            $query->where('soi.adjustment', '>', 0);
        }

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%");
            });
        }

        $this->applyDateFilter($query);

        $rows = $query
            ->select('i.sku', 'i.name')
            ->selectRaw('SUM(soi.adjustment) as total_adjustment')
            ->groupBy('i.id', 'i.sku', 'i.name')
            ->orderByRaw('ABS(SUM(soi.adjustment)) DESC')
            ->get();

        return $rows->map(function ($row) use ($isMinus) {
            $qty = (int) $row->total_adjustment;
            return [
                $row->sku ?? '-',
                $row->name ?? '-',
                $isMinus ? abs($qty) : $qty,
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
