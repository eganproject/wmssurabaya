<?php

namespace App\Support;

use App\Models\ItemStock;
use App\Models\ItemUnit;
use App\Models\StockMutation;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public static function mutate(array $payload): ?StockMutation
    {
        $idempotencyKey = self::normalizeIdempotencyKey($payload['idempotency_key'] ?? null);

        try {
            return DB::transaction(function () use ($payload, $idempotencyKey) {
                $itemId = (int) ($payload['item_id'] ?? 0);
                $warehouseId = (int) ($payload['warehouse_id'] ?? Warehouse::defaultId());
                $direction = $payload['direction'] ?? 'in';
                $qty = (int) ($payload['qty'] ?? 0);
                $qtyInput = (int) ($payload['qty_input'] ?? $qty);
                $unitId = isset($payload['unit_id']) ? (int) $payload['unit_id'] : null;
                $conversionQty = (int) ($payload['conversion_qty'] ?? 1);

                if ($unitId) {
                    $unit = ItemUnit::where('id', $unitId)
                        ->where('item_id', $itemId)
                        ->first();
                    if (!$unit) {
                        throw ValidationException::withMessages([
                            'unit_id' => 'Satuan item tidak valid',
                        ]);
                    }
                    $conversionQty = (int) $unit->conversion_qty;
                    $qty = $qtyInput * $conversionQty;
                }

                if ($idempotencyKey) {
                    $existing = StockMutation::where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        return $existing;
                    }
                }

                if (!in_array($direction, ['in', 'out'], true)) {
                    throw ValidationException::withMessages([
                        'direction' => 'Arah mutasi stok tidak valid',
                    ]);
                }

                if ($itemId <= 0 || $warehouseId <= 0 || $qty <= 0 || $qtyInput <= 0 || $conversionQty <= 0) {
                    throw ValidationException::withMessages([
                        'qty' => 'Qty tidak valid',
                    ]);
                }

                self::assertWarehouseQuantity($warehouseId, $itemId, $qty, $unitId);

                $stock = self::lockStock($warehouseId, $itemId);

                if ($idempotencyKey) {
                    $existing = StockMutation::where('idempotency_key', $idempotencyKey)->first();
                    if ($existing) {
                        return $existing;
                    }
                }

                if ($direction === 'out' && $stock->stock < $qty) {
                    throw ValidationException::withMessages([
                        'qty' => 'Stok tidak mencukupi',
                    ]);
                }

                $stockBefore = (int) $stock->stock;
                $stockAfter = $direction === 'out'
                    ? ($stockBefore - $qty)
                    : ($stockBefore + $qty);
                $stock->stock = $stockAfter;
                $stock->save();

                $sourceType = $payload['source_type'] ?? null;
                $sourceId = $payload['source_id'] ?? null;
                if (!$sourceType || !$sourceId) {
                    throw ValidationException::withMessages([
                        'source' => 'Sumber mutasi tidak valid',
                    ]);
                }

                return StockMutation::create([
                    'warehouse_id' => $warehouseId,
                    'item_id' => $itemId,
                    'unit_id' => $unitId,
                    'direction' => $direction,
                    'qty' => $qty,
                    'qty_input' => $qtyInput,
                    'conversion_qty' => $conversionQty,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
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
                return StockMutation::where('idempotency_key', $idempotencyKey)->first();
            }

            throw $e;
        }
    }

    public static function rollbackBySource(string $sourceType, int $sourceId): void
    {
        $mutations = StockMutation::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderByDesc('id')
            ->get();

        foreach ($mutations as $mutation) {
            $warehouseId = (int) ($mutation->warehouse_id ?: Warehouse::defaultId());
            $stock = self::lockStock($warehouseId, (int) $mutation->item_id);

            if ($mutation->direction === 'in') {
                $newStock = $stock->stock - $mutation->qty;
            } else {
                $newStock = $stock->stock + $mutation->qty;
            }

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'qty' => 'Stok tidak mencukupi untuk rollback',
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

    public static function assertWarehouseQuantity(
        int $warehouseId,
        int $itemId,
        int $qtyBase,
        ?int $unitId = null
    ): void {
        $warehouseType = Warehouse::whereKey($warehouseId)->value('type');
        if ($warehouseType !== Warehouse::TYPE_BULK) {
            return;
        }

        if ($unitId !== null) {
            $unit = ItemUnit::where('id', $unitId)
                ->where('item_id', $itemId)
                ->first();
            if (!$unit || $unit->is_base || (int) $unit->conversion_qty < 2) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Gudang Besar wajib menggunakan satuan koli, bukan satuan dasar.',
                ]);
            }

            if ($qtyBase <= 0 || $qtyBase % (int) $unit->conversion_qty !== 0) {
                throw ValidationException::withMessages([
                    'qty' => "Qty Gudang Besar wajib kelipatan 1 {$unit->name} ({$unit->conversion_qty} satuan dasar).",
                ]);
            }

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

        if ($qtyBase <= 0 || $qtyBase % (int) $packageUnit->conversion_qty !== 0) {
            throw ValidationException::withMessages([
                'qty' => "Qty Gudang Besar wajib kelipatan 1 {$packageUnit->name} ({$packageUnit->conversion_qty} satuan dasar).",
            ]);
        }
    }

    private static function normalizeIdempotencyKey(mixed $key): ?string
    {
        $key = trim((string) ($key ?? ''));
        if ($key === '') {
            return null;
        }

        return strlen($key) > 120 ? hash('sha256', $key) : $key;
    }

    private static function lockStock(int $warehouseId, int $itemId): ItemStock
    {
        $now = now();
        DB::table('item_stocks')->insertOrIgnore([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'stock' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ItemStock::where('warehouse_id', $warehouseId)
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
