<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockApiSyncRecord;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$filters, $error] = $this->filters($request);
        if ($error) {
            return $error;
        }

        $serverTime = now('Asia/Jakarta');
        if ($filters['as_of']) {
            $query = $this->historicalQuery($filters['warehouse']->id, $filters['as_of']);
        } else {
            $query = StockApiSyncRecord::query()->where('warehouse_id', $filters['warehouse']->id);
            if ($filters['updated_since']) {
                $query->where('source_updated_at', '>=', $filters['updated_since']);
            }
            $query->where('source_updated_at', '<=', $filters['updated_until'] ?? $serverTime);
        }

        $total = (clone $query)->count();
        $records = $filters['as_of']
            ? $query->orderBy('sr.sku')->forPage($filters['page'], $filters['per_page'])->get()
            : $query->orderBy('source_updated_at')->orderBy('sku')->forPage($filters['page'], $filters['per_page'])->get();

        return response()->json([
            'success' => true,
            'meta' => [
                'warehouse_code' => $filters['warehouse']->code,
                'server_time' => $serverTime->toIso8601String(),
                'page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total' => $total,
                'total_pages' => (int) ceil($total / $filters['per_page']),
            ],
            'data' => $records->map(fn (object $record) => [
                'sku' => $record->sku,
                'name' => $record->name,
                'category' => $record->category,
                'uom' => $record->uom,
                'qty' => (float) ($record->historical_qty ?? $record->qty),
                'min_qty' => $record->min_qty === null ? null : (float) $record->min_qty,
                'status' => $record->status,
                'updated_at' => $this->isoTimestamp($record->historical_updated_at ?? $record->source_updated_at),
            ])->values(),
        ]);
    }

    private function historicalQuery(int $warehouseId, CarbonImmutable $asOf): Builder
    {
        $movementsAfter = DB::table('stock_mutations')
            ->select('warehouse_id', 'item_id')
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END) as net_movement")
            ->where('occurred_at', '>', $asOf)
            ->groupBy('warehouse_id', 'item_id');
        $lastMutation = DB::table('stock_mutations')
            ->select('warehouse_id', 'item_id')
            ->selectRaw('MAX(occurred_at) as last_mutation_at')
            ->where('occurred_at', '<=', $asOf)
            ->groupBy('warehouse_id', 'item_id');
        $physicalAsOf = DB::table('item_stocks as s')
            ->leftJoinSub($movementsAfter, 'after_date', function ($join) {
                $join->on('after_date.warehouse_id', '=', 's.warehouse_id')
                    ->on('after_date.item_id', '=', 's.item_id');
            })
            ->leftJoinSub($lastMutation, 'last_mutation', function ($join) {
                $join->on('last_mutation.warehouse_id', '=', 's.warehouse_id')
                    ->on('last_mutation.item_id', '=', 's.item_id');
            })
            ->select('s.warehouse_id', 's.item_id')
            ->selectRaw('COALESCE(s.stock, 0) - COALESCE(after_date.net_movement, 0) as stock_as_of')
            ->selectRaw('last_mutation.last_mutation_at');
        $bundleAsOf = DB::table('item_bundles as ib')
            ->joinSub($physicalAsOf, 'component_stock', 'component_stock.item_id', '=', 'ib.component_item_id')
            ->select('component_stock.warehouse_id', 'ib.bundle_item_id')
            ->selectRaw('COALESCE(MIN(FLOOR(COALESCE(component_stock.stock_as_of, 0) / NULLIF(ib.qty, 0))), 0) as stock_as_of')
            ->selectRaw('MAX(component_stock.last_mutation_at) as last_mutation_at')
            ->groupBy('component_stock.warehouse_id', 'ib.bundle_item_id');

        return DB::table('stock_api_sync_records as sr')
            ->leftJoin('items as i', 'i.id', '=', 'sr.item_id')
            ->leftJoinSub($physicalAsOf, 'physical_stock', function ($join) {
                $join->on('physical_stock.warehouse_id', '=', 'sr.warehouse_id')
                    ->on('physical_stock.item_id', '=', 'sr.item_id');
            })
            ->leftJoinSub($bundleAsOf, 'bundle_stock', function ($join) {
                $join->on('bundle_stock.warehouse_id', '=', 'sr.warehouse_id')
                    ->on('bundle_stock.bundle_item_id', '=', 'sr.item_id');
            })
            ->where('sr.warehouse_id', $warehouseId)
            ->select([
                'sr.sku', 'sr.name', 'sr.category', 'sr.uom', 'sr.min_qty', 'sr.status', 'sr.source_updated_at',
                DB::raw('CASE WHEN COALESCE(i.is_bundle, 0) = 1 THEN COALESCE(bundle_stock.stock_as_of, 0) ELSE COALESCE(physical_stock.stock_as_of, 0) END as historical_qty'),
                DB::raw('CASE WHEN COALESCE(i.is_bundle, 0) = 1 THEN COALESCE(bundle_stock.last_mutation_at, sr.source_updated_at) ELSE COALESCE(physical_stock.last_mutation_at, sr.source_updated_at) END as historical_updated_at'),
            ]);
    }

    private function filters(Request $request): array
    {
        $page = filter_var($request->input('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $perPage = filter_var($request->input('per_page', 100), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
        if ($page === false || $perPage === false) {
            return [[], $this->error('page dan per_page harus berupa integer positif; per_page maksimum 500.')];
        }
        $warehouseCode = trim((string) $request->input('warehouse_code', config('stock_api.default_warehouse_code')));
        $warehouse = Warehouse::where('code', $warehouseCode)->where('is_active', true)->first();
        if (! $warehouse) {
            return [[], $this->error('warehouse_code tidak ditemukan atau tidak aktif.')];
        }
        $since = $this->parseIsoTimestamp($request->input('updated_since'));
        $until = $this->parseIsoTimestamp($request->input('updated_until'));
        if ($since === false || $until === false) {
            return [[], $this->error('updated_since dan updated_until harus ISO-8601 dengan offset zona waktu.')];
        }
        if ($since && $until && $since->gt($until)) {
            return [[], $this->error('updated_since tidak boleh melebihi updated_until.')];
        }
        $asOf = $this->parseAsOfDate($request->input('as_of'));
        if ($asOf === false) {
            return [[], $this->error('as_of harus berupa tanggal YYYY-MM-DD yang valid (WIB).')];
        }
        if ($asOf && ($since || $until)) {
            return [[], $this->error('as_of tidak dapat dipakai bersama updated_since atau updated_until.')];
        }

        return [[
            'page' => $page, 'per_page' => $perPage, 'warehouse' => $warehouse,
            'updated_since' => $since, 'updated_until' => $until, 'as_of' => $asOf,
        ], null];
    }

    private function parseIsoTimestamp(mixed $value): CarbonImmutable|false|null
    {
        if ($value === null || $value === '') return null;
        if (! is_string($value) || ! preg_match('/T.*(?:Z|[+-]\\d{2}:\\d{2})$/', $value)) return false;
        try { return CarbonImmutable::parse($value); } catch (\Throwable) { return false; }
    }

    private function parseAsOfDate(mixed $value): CarbonImmutable|false|null
    {
        if ($value === null || $value === '') return null;
        if (! is_string($value) || ! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) return false;
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta');
            return $date->format('Y-m-d') === $value ? $date->endOfDay() : false;
        } catch (\Throwable) { return false; }
    }

    private function isoTimestamp(mixed $timestamp): string
    {
        return ($timestamp instanceof \DateTimeInterface ? CarbonImmutable::instance($timestamp) : CarbonImmutable::parse($timestamp))
            ->setTimezone('Asia/Jakarta')->toIso8601String();
    }

    private function error(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'error' => ['code' => 'INVALID_PARAMETER', 'message' => $message]], 400);
    }
}
