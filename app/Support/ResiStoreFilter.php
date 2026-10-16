<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Shared "Toko" filter for report pages that list resi.
 * Value: '' = semua toko, 'none' = resi tanpa toko, angka = id toko.
 */
class ResiStoreFilter
{
    public const NONE = 'none';

    public static function normalize(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === self::NONE) {
            return self::NONE;
        }

        return ctype_digit($value) && (int) $value > 0 ? $value : null;
    }

    /**
     * Apply the filter to a query on the resis table (Eloquent or query builder).
     */
    public static function apply($query, ?string $filter, string $column = 'store_id')
    {
        if ($filter === self::NONE) {
            $query->whereNull($column);
        } elseif ($filter !== null) {
            $query->where($column, (int) $filter);
        }

        return $query;
    }

    /**
     * Apply the filter through a `resi` relation (e.g. QcScanResi, PackerResiScan).
     */
    public static function applyViaResi($query, ?string $filter, string $relation = 'resi')
    {
        if ($filter === null) {
            return $query;
        }

        return $query->whereHas($relation, fn ($resiQuery) => self::apply($resiQuery, $filter));
    }

    /**
     * Import vs scanned totals per toko for an Eloquent query on resis.
     * $scanTable is the table holding the scans (packer_scan_outs / packer_resi_scans).
     */
    public static function breakdown($resiQuery, string $scanTable): Collection
    {
        return (clone $resiQuery)
            ->leftJoin('stores as resi_store', 'resi_store.id', '=', 'resis.store_id')
            ->selectRaw('resis.store_id, MAX(resi_store.name) as store_name, COUNT(*) as import_total')
            ->selectRaw("SUM(CASE WHEN EXISTS (SELECT 1 FROM {$scanTable} scan WHERE scan.resi_id = resis.id) THEN 1 ELSE 0 END) as scanned_total")
            ->groupBy('resis.store_id')
            ->toBase()
            ->get()
            ->map(function ($row) {
                $import = (int) $row->import_total;
                $scanned = (int) $row->scanned_total;

                return [
                    'store_id' => $row->store_id ? (int) $row->store_id : null,
                    'store_name' => $row->store_id ? ($row->store_name ?? '-') : 'Tanpa Toko',
                    'import_total' => $import,
                    'scanned_total' => $scanned,
                    'missing_total' => max(0, $import - $scanned),
                    'progress' => $import > 0 ? round($scanned / $import * 100, 1) : 0,
                ];
            })
            ->sortBy([['missing_total', 'desc'], ['store_name', 'asc']])
            ->values();
    }

    public static function options(): Collection
    {
        return Store::query()->orderBy('name')->get(['id', 'name']);
    }

    public static function label($store): string
    {
        return $store?->name ?? '-';
    }
}
