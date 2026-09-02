<?php

namespace App\Support;

use App\Models\StockMutation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class StockMutationReport
{
    public static function query(array $filters = []): Builder
    {
        $query = StockMutation::query()->with([
            'item.baseUnit',
            'creator',
            'warehouse',
            'unit',
        ]);

        $warehouseId = (int) ($filters['warehouse_id'] ?? 0);
        if ($warehouseId > 0) {
            $query->where('warehouse_id', $warehouseId);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->where('source_code', 'like', "%{$search}%")
                    ->orWhere('source_type', 'like', "%{$search}%")
                    ->orWhere('source_subtype', 'like', "%{$search}%")
                    ->orWhereHas('creator', function (Builder $creatorQuery) use ($search) {
                        $creatorQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('item', function (Builder $itemQuery) use ($search) {
                        $itemQuery->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        self::applyDateFilter($query, $filters);

        return $query;
    }

    public static function sourceTypeLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            'inbound' => 'Penerimaan Barang',
            'outbound' => 'Pengeluaran Barang',
            'transfer' => 'Transfer Gudang',
            'adjustment' => 'Penyesuaian Stok',
            'opname' => 'Stock Opname',
            'damaged' => 'Barang Rusak',
            'picker', 'qc' => 'QC Scan',
            'qc_resi' => 'QC Scan Resi',
            default => ucfirst((string) ($sourceType ?: '-')),
        };
    }

    public static function sourceSubtypeLabel(?string $sourceSubtype): string
    {
        return match ($sourceSubtype) {
            'receipt' => 'Penerimaan',
            'return' => 'Retur',
            'manual' => 'Manual',
            'picker' => 'Picker',
            default => $sourceSubtype ? ucfirst($sourceSubtype) : '-',
        };
    }

    public static function sourceLabel(?string $sourceType, ?string $sourceSubtype): string
    {
        $type = self::sourceTypeLabel($sourceType);
        $subtype = self::sourceSubtypeLabel($sourceSubtype);

        return $subtype === '-' ? $type : $type.' / '.$subtype;
    }

    private static function applyDateFilter(Builder $query, array $filters): void
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if ($dateFrom) {
            try {
                $query->where('occurred_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            } catch (\Throwable) {
                // Abaikan tanggal mulai yang tidak valid.
            }
        }

        if ($dateTo) {
            try {
                $query->where('occurred_at', '<=', Carbon::parse($dateTo)->endOfDay());
            } catch (\Throwable) {
                // Abaikan tanggal akhir yang tidak valid.
            }
        }
    }
}
