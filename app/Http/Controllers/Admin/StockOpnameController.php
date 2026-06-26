<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\StockOpnameDetailExport;
use App\Exports\StockOpnamesTemplateExport;
use App\Imports\StockOpnamesImport;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\ItemStock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class StockOpnameController extends Controller
{
    public function items(Request $request)
    {
        $warehouseId = $request->integer('warehouse_id') ?: Warehouse::defaultId();
        $items = Item::query()
            ->with(['units' => fn ($query) => $query->orderByDesc('is_base')->orderBy('conversion_qty')])
            ->leftJoin('item_stocks', function ($join) use ($warehouseId) {
                $join->on('item_stocks.item_id', '=', 'items.id')
                    ->where('item_stocks.warehouse_id', '=', $warehouseId);
            })
            ->orderBy('items.name')
            ->get([
                'items.id',
                'items.sku',
                'items.name',
                DB::raw('COALESCE(item_stocks.stock, 0) as stock'),
            ]);

        return response()->json(['data' => $items]);
    }

    public function index()
    {
        $defaultWarehouseId = Warehouse::defaultId();
        $items = Item::with(['units' => fn ($query) => $query->orderByDesc('is_base')->orderBy('conversion_qty')])
            ->leftJoin('item_stocks', function ($join) use ($defaultWarehouseId) {
                $join->on('item_stocks.item_id', '=', 'items.id')
                    ->where('item_stocks.warehouse_id', '=', $defaultWarehouseId);
            })
            ->orderBy('items.name')
            ->get([
                'items.id',
                'items.sku',
                'items.name',
                DB::raw('COALESCE(item_stocks.stock, 0) as stock'),
            ]);

        return view('admin.inventory.stock-opname.index', [
            'items' => $items,
            'dataUrl' => route('admin.inventory.stock-opname.data'),
            'storeUrl' => route('admin.inventory.stock-opname.store'),
            'importUrl' => route('admin.inventory.stock-opname.import'),
            'templateUrl' => route('admin.inventory.stock-opname.template'),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type', 'is_default']),
        ]);
    }

    public function template()
    {
        return Excel::download(
            new StockOpnamesTemplateExport(),
            'template-import-stock-opname.xlsx'
        );
    }

    public function data(Request $request)
    {
        $baseQuery = StockOpname::query()
            ->leftJoin('stock_opname_items', 'stock_opname_items.stock_opname_id', '=', 'stock_opnames.id')
            ->leftJoin('items', 'items.id', '=', 'stock_opname_items.item_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'stock_opnames.created_by');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('stock_opnames.code', 'like', "%{$search}%")
                    ->orWhere('stock_opnames.note', 'like', "%{$search}%")
                    ->orWhere('items.sku', 'like', "%{$search}%")
                    ->orWhere('items.name', 'like', "%{$search}%");
            });
        }

        $this->applyDateFilter($baseQuery, $request);

        $recordsTotal = StockOpname::count();
        $recordsFiltered = (clone $baseQuery)->distinct('stock_opnames.id')->count('stock_opnames.id');

        $query = (clone $baseQuery)
            ->select([
                'stock_opnames.id',
                'stock_opnames.code',
                'stock_opnames.transacted_at',
                'stock_opnames.note',
                'stock_opnames.status',
                DB::raw('creators.name as submit_by'),
                DB::raw('COUNT(stock_opname_items.id) as items_count'),
                DB::raw('COALESCE(SUM(stock_opname_items.adjustment), 0) as total_adjustment'),
            ])
            ->groupBy('stock_opnames.id', 'stock_opnames.code', 'stock_opnames.transacted_at', 'stock_opnames.note', 'stock_opnames.status', 'creators.name')
            ->orderBy('stock_opnames.transacted_at', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $ts = $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '';
            return [
                'id' => $row->id,
                'code' => $row->code,
                'transacted_at' => $ts,
                'submit_by' => $row->submit_by ?? '-',
                'items_count' => (int) $row->items_count,
                'total_adjustment' => (int) $row->total_adjustment,
                'note' => $row->note ?? '',
                'status' => $row->status ?? 'open',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function show(int $id)
    {
        $opname = StockOpname::with([
            'creator:id,name',
            'completer:id,name',
            'items.item:id,sku,name',
            'items.creator:id,name',
        ])->find($id);

        if (!$opname) {
            abort(404, 'Stock opname tidak ditemukan');
        }

        $items = $opname->items->sortBy('id')->values();
        $diffItems = $items->filter(fn ($row) => (int) $row->adjustment !== 0)->values();
        $plusItems = $diffItems->filter(fn ($row) => (int) $row->adjustment > 0);
        $minusItems = $diffItems->filter(fn ($row) => (int) $row->adjustment < 0);

        return view('admin.inventory.stock-opname.show', [
            'opname' => $opname,
            'items' => $items,
            'diffItems' => $diffItems,
            'totalSku' => $items->count(),
            'diffSkuCount' => $diffItems->count(),
            'plusSkuCount' => $plusItems->count(),
            'minusSkuCount' => $minusItems->count(),
            'plusQty' => (int) $plusItems->sum('adjustment'),
            'minusQty' => (int) $minusItems->sum('adjustment'),
            'totalAdjustment' => (int) $items->sum('adjustment'),
        ]);
    }

    public function approve(int $id)
    {
        DB::beginTransaction();
        try {
            $opname = StockOpname::where('id', $id)->lockForUpdate()->firstOrFail();
            if (($opname->status ?? 'open') === 'completed') {
                DB::commit();
                return response()->json(['message' => 'Stock opname sudah disetujui']);
            }

            $this->postStockMovements($opname);

            $opname->status = 'completed';
            $opname->completed_at = now();
            $opname->completed_by = auth()->id();
            $opname->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyetujui stock opname',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Stock opname berhasil disetujui']);
    }

    public function export(int $id)
    {
        $opname = StockOpname::findOrFail($id);
        $code = $opname->code ?: 'stock-opname';
        $filename = sprintf('stock-opname-%s-%s.xlsx', $code, now()->format('YmdHis'));

        return Excel::download(new StockOpnameDetailExport($opname), $filename);
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $opname = StockOpname::findOrFail($id);
            if (($opname->status ?? 'open') === 'completed') {
                DB::rollBack();
                return response()->json(['message' => 'Stock opname sudah diselesaikan dan tidak bisa dihapus'], 422);
            }

            StockService::rollbackBySource('opname', $opname->id);
            StockMutation::where('source_type', 'opname')
                ->where('source_id', $opname->id)
                ->delete();
            StockOpnameItem::where('stock_opname_id', $opname->id)->delete();
            $opname->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus stock opname',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Stock opname berhasil dihapus',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $code = $this->generateCode('OPN');
        $transactedAt = $validated['transacted_at'] ?? now();

        DB::beginTransaction();
        try {
            $opname = StockOpname::create([
                'warehouse_id' => $validated['warehouse_id'],
                'code' => $code,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => 'open',
            ]);

            foreach ($validated['items'] as $row) {
                $stock = ItemStock::where('warehouse_id', $validated['warehouse_id'])
                    ->where('item_id', $row['item_id'])->lockForUpdate()->first();
                if (!$stock) {
                    ItemStock::create(['warehouse_id' => $validated['warehouse_id'], 'item_id' => $row['item_id'], 'stock' => 0]);
                    $stock = ItemStock::where('warehouse_id', $validated['warehouse_id'])
                        ->where('item_id', $row['item_id'])->lockForUpdate()->first();
                }
                $systemQty = (int) ($stock?->stock ?? 0);
                $countedQty = (int) $row['counted_qty'];
                $adjustment = $countedQty - $systemQty;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'item_id' => $row['item_id'],
                    'unit_id' => $row['unit_id'],
                    'counted_qty_input' => $row['counted_qty_input'],
                    'conversion_qty' => $row['conversion_qty'],
                    'system_qty' => $systemQty,
                    'counted_qty' => $countedQty,
                    'adjustment' => $adjustment,
                    'note' => $row['note'] ?? null,
                    'created_by' => auth()->id(),
                ]);

            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan stock opname',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Stock opname berhasil disimpan',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $warehouseId = $request->integer('warehouse_id');
        $import = new StockOpnamesImport();

        try {
            Excel::import($import, $request->file('file'));
            $items = $import->items ?? [];
            if (empty($items)) {
                throw ValidationException::withMessages([
                    'file' => 'Tidak ada data valid untuk diimport',
                ]);
            }

            $itemIds = collect($items)->pluck('item_id')->all();
            $itemMap = Item::with(['units' => fn ($query) => $query->orderByDesc('is_base')->orderBy('conversion_qty')])
                ->whereIn('id', $itemIds)
                ->get(['id', 'sku', 'name'])
                ->keyBy('id');

            $payloadItems = [];
            foreach ($items as $row) {
                $itemId = (int) $row['item_id'];
                $unitId = $this->resolveImportedUnitId($itemId, $row['unit'] ?? null);
                if (!empty($row['unit']) && $unitId <= 0) {
                    throw ValidationException::withMessages([
                        'file' => "Satuan {$row['unit']} tidak ditemukan untuk SKU {$row['sku']}",
                    ]);
                }
                $unit = $this->resolveInputUnit($warehouseId, $itemId, $unitId);
                $qtyInput = (int) $row['counted_qty_input'];
                $conversionQty = (int) ($unit?->conversion_qty ?? 1);
                $countedQty = $qtyInput * $conversionQty;

                if ($countedQty > 0) {
                    StockService::assertWarehouseQuantity(
                        $warehouseId,
                        $itemId,
                        $countedQty,
                        $unit?->id ? (int) $unit->id : null
                    );
                }

                $item = $itemMap->get($itemId);
                $payloadItems[] = [
                    'item_id' => $itemId,
                    'sku' => $item?->sku ?? $row['sku'],
                    'name' => $item?->name ?? '',
                    'unit_id' => $unit?->id,
                    'unit_name' => $unit?->name,
                    'counted_qty_input' => $qtyInput,
                    'counted_qty' => $countedQty,
                    'note' => $row['note'] ?? null,
                ];
            }

            $transactedAt = null;
            if (!empty($import->transacted_at)) {
                try {
                    $transactedAt = Carbon::parse($import->transacted_at)->format('Y-m-d H:i');
                } catch (\Throwable) {
                    throw ValidationException::withMessages([
                        'file' => 'Format transacted_at tidak valid: '.$import->transacted_at,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Data import berhasil dimuat ke form',
                'note' => $import->note,
                'transacted_at' => $transactedAt,
                'items' => $payloadItems,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal membaca file import stock opname',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function validatePayload(Request $request): array
    {
        $rawItems = $request->input('items', []);
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:item_units,id'],
            'items.*.counted_qty_input' => ['nullable', 'integer', 'min:0'],
            'items.*.counted_qty' => ['nullable', 'integer', 'min:0'],
            'items.*.note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);
        $validated['warehouse_id'] = (int) ($validated['warehouse_id'] ?? Warehouse::defaultId());

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row, $index) use ($validated, $rawItems) {
                $itemId = (int) $row['item_id'];
                $unit = $this->resolveInputUnit(
                    $validated['warehouse_id'],
                    $itemId,
                    (int) ($row['unit_id'] ?? 0),
                    !isset($rawItems[$index]['counted_qty_input'])
                );
                $qtyInput = (int) ($row['counted_qty_input'] ?? $row['counted_qty'] ?? 0);
                $conversionQty = (int) ($unit?->conversion_qty ?? 1);
                return [
                    'item_id' => $itemId,
                    'unit_id' => $unit?->id,
                    'counted_qty_input' => $qtyInput,
                    'conversion_qty' => $conversionQty,
                    'counted_qty' => $qtyInput * $conversionQty,
                    'note' => $row['note'] ?? null,
                ];
            })->values();

        foreach ($items as $row) {
            if ((int) $row['counted_qty'] > 0) {
                StockService::assertWarehouseQuantity(
                    $validated['warehouse_id'],
                    (int) $row['item_id'],
                    (int) $row['counted_qty'],
                    $row['unit_id'] ? (int) $row['unit_id'] : null
                );
            }
        }

        $duplicates = $items->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item tidak boleh duplikat pada stock opname',
            ]);
        }

        $validated['items'] = $items->all();
        if (!empty($validated['transacted_at'])) {
            $validated['transacted_at'] = Carbon::parse($validated['transacted_at']);
        } else {
            $validated['transacted_at'] = null;
        }

        return $validated;
    }

    private function resolveInputUnit(int $warehouseId, int $itemId, int $unitId, bool $legacyBaseInput = false): ?ItemUnit
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $query = ItemUnit::where('item_id', $itemId);
        if ($unitId > 0) {
            $query->whereKey($unitId);
        } elseif ($legacyBaseInput) {
            $query->where('is_base', true);
        } elseif ($warehouse->type === Warehouse::TYPE_BULK) {
            $query->where('is_base', false)->orderBy('conversion_qty');
        } else {
            $query->where('is_base', true);
        }
        $unit = $query->first();
        if (!$unit && $legacyBaseInput) {
            return null;
        }

        if (!$unit || ($warehouse->type === Warehouse::TYPE_BULK && $unit->is_base)) {
            throw ValidationException::withMessages(['items' => 'Gudang Besar wajib dihitung dalam satuan koli/kemasan.']);
        }
        if ($warehouse->type !== Warehouse::TYPE_BULK && !$unit->is_base) {
            throw ValidationException::withMessages(['items' => 'Gudang Kecil wajib dihitung dalam satuan dasar PCS/SET.']);
        }

        return $unit;
    }

    private function resolveImportedUnitId(int $itemId, ?string $unitName): int
    {
        $unitName = trim((string) $unitName);
        if ($unitName === '') {
            return 0;
        }

        return (int) ItemUnit::where('item_id', $itemId)
            ->whereRaw('LOWER(name) = ?', [strtolower($unitName)])
            ->value('id');
    }

    private function postStockMovements(StockOpname $opname): void
    {
        $opname->loadMissing('items');
        foreach ($opname->items as $row) {
            $adjustment = (int) $row->adjustment;
            if ($adjustment === 0) {
                continue;
            }

            StockService::mutate([
                'warehouse_id' => $opname->warehouse_id ?: Warehouse::defaultId(),
                'item_id' => $row->item_id,
                'unit_id' => $row->unit_id,
                'direction' => $adjustment > 0 ? 'in' : 'out',
                'qty' => abs($adjustment),
                'qty_input' => $row->conversion_qty > 0
                    ? (int) (abs($adjustment) / $row->conversion_qty)
                    : abs($adjustment),
                'conversion_qty' => $row->conversion_qty ?: 1,
                'source_type' => 'opname',
                'source_subtype' => null,
                'source_id' => $opname->id,
                'source_code' => $opname->code,
                'note' => $row->note ?? null,
                'occurred_at' => $opname->transacted_at ?? now(),
                'created_by' => auth()->id(),
                'idempotency_key' => StockService::idempotencyKey(['stock', 'opname', $opname->id, $row->item_id]),
            ]);
        }
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('stock_opnames.transacted_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('stock_opnames.transacted_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }
}
