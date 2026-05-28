<?php

namespace App\Support;

use App\Models\ItemStock;
use App\Models\StockMutation;
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
                $direction = $payload['direction'] ?? 'in';
                $qty = (int) ($payload['qty'] ?? 0);

                if ($idempotencyKey) {
                    $existing = StockMutation::where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        return $existing;
                    }
                }

                if ($itemId <= 0 || $qty <= 0) {
                    throw ValidationException::withMessages([
                        'qty' => 'Qty tidak valid',
                    ]);
                }

                $stock = ItemStock::where('item_id', $itemId)->lockForUpdate()->first();
                if (!$stock) {
                    $stock = ItemStock::create(['item_id' => $itemId, 'stock' => 0]);
                }

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

                $stock->stock = $direction === 'out'
                    ? ($stock->stock - $qty)
                    : ($stock->stock + $qty);
                $stock->save();

                $sourceType = $payload['source_type'] ?? null;
                $sourceId = $payload['source_id'] ?? null;
                if (!$sourceType || !$sourceId) {
                    throw ValidationException::withMessages([
                        'source' => 'Sumber mutasi tidak valid',
                    ]);
                }

                return StockMutation::create([
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
            $stock = ItemStock::where('item_id', $mutation->item_id)->lockForUpdate()->first();
            if (!$stock) {
                $stock = ItemStock::create(['item_id' => $mutation->item_id, 'stock' => 0]);
            }

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

    private static function normalizeIdempotencyKey(mixed $key): ?string
    {
        $key = trim((string) ($key ?? ''));
        if ($key === '') {
            return null;
        }

        return strlen($key) > 120 ? hash('sha256', $key) : $key;
    }

    private static function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (string) ($e->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '19'], true);
    }
}
