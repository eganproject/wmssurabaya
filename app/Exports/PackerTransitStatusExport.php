<?php

namespace App\Exports;

use App\Models\PackerTransitHistory;
use App\Support\ResiStoreFilter;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PackerTransitStatusExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $query = PackerTransitHistory::query()
            ->with('resi.store')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id_pesanan', 'like', "%{$search}%")
                    ->orWhere('no_resi', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('resi.store', fn ($storeQ) => $storeQ->where('name', 'like', "%{$search}%"));
            });
        }

        $date = $this->filters['date'] ?? null;
        if (empty($date)) {
            $date = now()->toDateString();
        }
        try {
            $target = Carbon::parse($date)->toDateString();
            $query->whereDate('created_at', $target);
        } catch (\Throwable) {
            // ignore invalid date
        }

        ResiStoreFilter::applyViaResi($query, ResiStoreFilter::normalize($this->filters['store_id'] ?? null));

        $status = (string) ($this->filters['status'] ?? '');
        if ($status === 'pending') {
            $query->where('status', 'menunggu scan out');
        } elseif ($status === 'done') {
            $query->where('status', 'selesai');
        }

        return $query->get();
    }

    public function headings(): array
    {
        return ['Waktu Input', 'ID Pesanan', 'No Resi', 'Toko', 'Status'];
    }

    public function map($row): array
    {
        return [
            $row->created_at?->format('Y-m-d H:i') ?? '-',
            (string) ($row->id_pesanan ?? '-'),
            (string) ($row->no_resi ?? '-'),
            ResiStoreFilter::label($row->resi?->store),
            $row->status ?? '-',
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
