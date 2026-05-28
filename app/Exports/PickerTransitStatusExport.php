<?php

namespace App\Exports;

use App\Models\QcScanResi;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PickerTransitStatusExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $query = QcScanResi::query()
            ->with(['scanner', 'resi', 'items.item'])
            ->whereHas('resi', function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->orderBy('scanned_at', 'desc')
            ->orderBy('id', 'desc');

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('resi', function ($resiQ) use ($search) {
                    $resiQ->where('no_resi', 'like', "%{$search}%")
                        ->orWhere('id_pesanan', 'like', "%{$search}%");
                })
                    ->orWhereHas('scanner', fn ($scannerQ) => $scannerQ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhereHas('item', fn ($masterQ) => $masterQ->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        $date = $this->filters['date'] ?? null;
        if (empty($date)) {
            $date = now()->toDateString();
        }
        try {
            $target = Carbon::parse($date)->toDateString();
            $query->whereDate('scanned_at', $target);
        } catch (\Throwable) {
            // ignore invalid date
        }

        $status = (string) ($this->filters['status'] ?? '');
        if ($status === 'ongoing') {
            $query->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('packer_scan_outs as pso')
                    ->whereColumn('pso.resi_id', 'qc_scan_resis.resi_id');
            });
        } elseif ($status === 'done') {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('packer_scan_outs as pso')
                    ->whereColumn('pso.resi_id', 'qc_scan_resis.resi_id');
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return ['Tanggal QC', 'ID Pesanan', 'No Resi', 'Petugas QC', 'SKU Dalam Resi', 'Qty Scan', 'Qty Wajib', 'Progress QC', 'Status QC', 'Status Scan Out', 'Scan Out At'];
    }

    public function map($row): array
    {
        $requiredQty = (int) $row->items->sum('required_qty');
        $scannedQty = (int) $row->items->sum('scanned_qty');
        $progress = $requiredQty > 0 ? (int) floor(min(100, ($scannedQty / $requiredQty) * 100)) : 0;
        $scanOut = DB::table('packer_scan_outs')
            ->where('resi_id', $row->resi_id)
            ->orderByDesc('scanned_at')
            ->first(['scanned_at']);
        $skuSummary = $row->items->map(fn ($item) => sprintf(
            '%s (%d/%d)',
            $item->sku,
            (int) $item->scanned_qty,
            (int) $item->required_qty
        ))->implode(', ');

        return [
            $row->scanned_at?->format('Y-m-d H:i') ?? '-',
            $row->resi?->id_pesanan ?? '-',
            $row->resi?->no_resi ?? '-',
            $row->scanner?->name ?? '-',
            $skuSummary ?: '-',
            $scannedQty,
            $requiredQty,
            $progress.'%',
            $row->status === 'completed' ? 'QC selesai' : 'Belum selesai QC',
            $scanOut ? 'Selesai' : 'Belum Scan Out',
            $scanOut?->scanned_at ? Carbon::parse($scanOut->scanned_at)->format('Y-m-d H:i') : '-',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
