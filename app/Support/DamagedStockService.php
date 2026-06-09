<?php

namespace App\Support;

use App\Models\DamagedItemStock;
use App\Models\DamagedStockMutation;
use App\Models\ItemUnit;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DamagedStockService
{
    public static function mutate(array $payload): ?DamagedStockMutation
    {
        $idempotencyKey = self::normalizeIdempotencyKey($payload['idempotency_key'] ?? null);

        try {
            return DB::transaction(function () use ($payload, $idempotencyKey) {
                $itemId = (int) ($payload['item_id'] ?? 0);
                $warehouseId = (int) ($payload['warehouse_id'] ?? Warehouse::defaultId());
                $direction = $payload['direction'] ?? 'in';
                $qty = (int) ($payload['qty'] ?? 0);

                if ($idempotencyKey) {
                    $existing = DamagedStockMutation::where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        return $existing;
                    }
                }

                if ($itemId <= 0 || $warehouseId <= 0 || $qty <= 0) {
                    throw ValidationException::withMessages([
                        'qty' => 'Qty stok rusak tidak valid',
                    ]);
                }

                self::assertBulkMultiple($warehouseId, $itemId, $qty);

                $stock = self::lockStock($warehouseId, $itemId);

                if ($direction === 'out' && $stock->stock < $qty) {
                    throw ValidationException::withMessages([
                        'qty' => 'Stok barang rusak tidak mencukupi',
                    ]);
                }

                $stock->stock = $direction === 'out'
                    ? ($stock->stock - $qty)
                    : ($stock->stock + $qty);

                // Saat stok benar-benar terpotong (approve alokasi), lepas reservasi yang dipegang
                if ($direction === 'out') {
                    $stock->reserved_stock = max(0, ($stock->reserved_stock ?? 0) - $qty);
                }

                $stock->save();

                $sourceType = $payload['source_type'] ?? null;
                $sourceId = $payload['source_id'] ?? null;
                if (!$sourceType || !$sourceId) {
                    throw ValidationException::withMessages([
                        'source' => 'Sumber mutasi stok rusak tidak valid',
                    ]);
                }

                return DamagedStockMutation::create([
                    'warehouse_id' => $warehouseId,
                    'item_id' => $itemId,
                    'direction' => $direction,
                    'qty' => $qty,
                    'source_type' => $sourceType,
                    'source_subtype' => $payload['source_subtype'] ?? null,
                    'source_id' => $sourceId,
                    'source_code' => $payload['source_code'] ?? null,
                    'note' => $payload['note'] ?? null,
                    'occurred_at' => $payload['occurred_at'] ?? now(),
                    'created_by' => $payload['created_by'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                ]);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && self::isUniqueConstraintViolation($e)) {
                return DamagedStockMutation::where('idempotency_key', $idempotencyKey)->first();
            }

            throw $e;
        }
    }

    /**
     * Reservasi stok rusak saat alokasi dibuat (pending).
     * FIFO: siapa duluan buat alokasi, dia yang dapat jatah stok tersedia.
     */
    public static function reserve(int $itemId, int $qty, ?int $warehouseId = null): void
    {
        DB::transaction(function () use ($itemId, $qty, $warehouseId) {
            $warehouseId ??= Warehouse::defaultId();
            if ($itemId <= 0 || $qty <= 0) {
                throw ValidationException::withMessages(['qty' => 'Qty reservasi tidak valid']);
            }

            self::assertBulkMultiple($warehouseId, $itemId, $qty);
            $stock = self::lockStock($warehouseId, $itemId);

            $available = $stock->stock - ($stock->reserved_stock ?? 0);
            if ($available < $qty) {
                throw ValidationException::withMessages([
                    'qty' => 'Stok barang rusak tidak mencukupi untuk alokasi ini',
                ]);
            }

            $stock->reserved_stock = ($stock->reserved_stock ?? 0) + $qty;
            $stock->save();
        });
    }

    /**
     * Lepas reservasi stok rusak saat alokasi pending dihapus atau diperbarui.
     */
    public static function releaseReservation(int $itemId, int $qty, ?int $warehouseId = null): void
    {
        DB::transaction(function () use ($itemId, $qty, $warehouseId) {
            $warehouseId ??= Warehouse::defaultId();
            if ($itemId <= 0 || $qty <= 0) {
                return;
            }

            $stock = DamagedItemStock::where('warehouse_id', $warehouseId)
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->first();
            if (!$stock) {
                return;
            }

            $stock->reserved_stock = max(0, ($stock->reserved_stock ?? 0) - $qty);
            $stock->save();
        });
    }

    public static function rollbackBySource(string $sourceType, int $sourceId): void
    {
        $mutations = DamagedStockMutation::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderByDesc('id')
            ->get();

        foreach ($mutations as $mutation) {
            $warehouseId = (int) ($mutation->warehouse_id ?: Warehouse::defaultId());
            $stock = self::lockStock($warehouseId, (int) $mutation->item_id);

            $newStock = $mutation->direction === 'in'
                ? $stock->stock - $mutation->qty
                : $stock->stock + $mutation->qty;

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'qty' => 'Stok rusak tidak mencukupi untuk rollback',
                ]);
            }

            $stock->stock = $newStock;
            $stock->save();
        }
    }

    public static function idempotencyKey(array $parts): string
    {
        $normalized = collect($parts)
            ->map(fn ($part) => is_scalar($part) || $part === null ? (string) $part : json_encode($part))
            ->implode('|');

        return hash('sha256', $normalized);
    }

    private static function normalizeIdempotencyKey(mixed $key): ?string
    {
        $key = trim((string) ($key ?? ''));
        if ($key === '') {
            return null;
        }

        return strlen($key) > 120 ? hash('sha256', $key) : $key;
    }

    private static function assertBulkMultiple(int $warehouseId, int $itemId, int $qty): void
    {
        if (Warehouse::whereKey($warehouseId)->value('type') !== Warehouse::TYPE_BULK) {
            return;
        }

        $packageUnit = ItemUnit::where('item_id', $itemId)
            ->where('is_base', false)
            ->first();
        if (!$packageUnit || (int) $packageUnit->conversion_qty < 2) {
            throw ValidationException::withMessages([
                'qty' => 'Item belum memiliki satuan koli untuk transaksi Gudang Besar.',
            ]);
        }
        if ($qty % (int) $packageUnit->conversion_qty !== 0) {
            throw ValidationException::withMessages([
                'qty' => "Qty Gudang Besar wajib kelipatan 1 {$packageUnit->name} ({$packageUnit->conversion_qty} satuan dasar).",
            ]);
        }
    }

    private static function lockStock(int $warehouseId, int $itemId): DamagedItemStock
    {
        $now = now();
        DB::table('damaged_item_stocks')->insertOrIgnore([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'stock' => 0,
            'reserved_stock' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DamagedItemStock::where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private static function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (string) ($e->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '19'], true);
    }
}
