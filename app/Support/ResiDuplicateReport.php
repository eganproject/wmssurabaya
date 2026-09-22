<?php

namespace App\Support;

use App\Models\Resi;
use App\Models\ResiImportBatchItem;

class ResiDuplicateReport
{
    public function forDate(string $date): array
    {
        $groups = collect();
        foreach (['no_resi' => 'Nomor resi', 'id_pesanan' => 'ID Pesanan'] as $column => $label) {
            // Compare against all dates so an older import remains visible as a conflict.
            $keys = Resi::query()->whereDate('tanggal_upload', $date)
                ->selectRaw("UPPER(TRIM({$column}))")
                ->whereNotNull($column)->whereRaw("TRIM({$column}) <> ''");
            $duplicates = Resi::query()
                ->whereIn(\DB::raw("UPPER(TRIM({$column}))"), $keys)
                ->selectRaw("UPPER(TRIM({$column})) as duplicate_key, COUNT(*) as total")
                ->groupByRaw("UPPER(TRIM({$column}))")
                ->havingRaw('COUNT(*) > 1')->get();

            if ($duplicates->isEmpty()) {
                continue;
            }

            $rows = Resi::query()->with('kurir')
                ->whereIn(\DB::raw("UPPER(TRIM({$column}))"), $duplicates->pluck('duplicate_key'))
                ->select('resis.*')->selectRaw("UPPER(TRIM({$column})) as duplicate_key")
                ->orderBy('tanggal_upload')->orderBy('id')->get()->groupBy('duplicate_key');

            foreach ($duplicates as $duplicate) {
                $matches = $rows->get($duplicate->duplicate_key, collect());
                $groups->push([
                    'type' => $label,
                    'value' => $duplicate->duplicate_key,
                    'rows' => $matches,
                    'active_count' => $matches->where('status', '!=', 'canceled')->count(),
                ]);
            }
        }

        return [
            'groups' => $groups,
            'updated_count' => ResiImportBatchItem::query()
                ->where('action', 'updated')
                ->whereHas('batch', fn ($query) => $query->whereDate('uploaded_at', $date)->where('status', 'active'))
                ->count(),
        ];
    }
}
