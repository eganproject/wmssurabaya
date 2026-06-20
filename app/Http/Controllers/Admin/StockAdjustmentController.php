<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Exports\StockAdjustmentsTemplateExport;
use App\Imports\StockAdjustmentsImport;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        $items = Item::with(['units' => fn ($query) => $query->orderByDesc('is_base')->orderBy('conversion_qty')])
            ->orderBy('name')->get(['id', 'sku', 'name']);
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type', 'is_default']);

        return view('admin.inventory.stock-adjustments.index', [
            'items' => $items,
            'dataUrl' => route('admin.inventory.stock-adjustments.data'),
            'storeUrl' => route('admin.inventory.stock-adjustments.store'),
            'importUrl' => route('admin.inventory.stock-adjustments.import'),
            'templateUrl' => route('admin.inventory.stock-adjustments.template'),
            'warehouses' => $warehouses,
        ]);
    }

    public function template()
    {
        return Excel::download(
            new StockAdjustmentsTemplateExport(),
            'template-import-penyesuaian-stok.xlsx'
        );
    }

    public function data(Request $request)
    {
        $query = StockAdjustment::query()
            ->with(['items.item', 'items.unit', 'creator', 'warehouse'])
            ->orderBy('transacted_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('stock_adjustments.code', 'like', "%{$search}%")
                    ->orWhere('stock_adjustments.note', 'like', "%{$search}%")
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($query, $request);

        $recordsTotal = StockAdjustment::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $items = $row->items ?? collect();
            $labels = $items->map(function ($it) {
                $sku = trim($it->item?->sku ?? '');
                if ($sku === '') {
                    return '';
                }
                $qty = (int) ($it->qty_input ?: $it->qty);
                $unit = $it->unit?->name ?: 'satuan dasar';
                $sign = $it->direction === 'in' ? '+' : '-';
                return sprintf('%s (%s%d %s)', $sku, $sign, $qty, $unit);
            })->filter()->values();
            $itemLabel = $labels->implode(', ');
            $ts = $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '';
            $totalIn = (int) $items->where('direction', 'in')->sum('qty');
            $totalOut = (int) $items->where('direction', 'out')->sum('qty');
            return [
                'id' => $row->id,
                'code' => $row->code,
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'warehouse' => $row->warehouse?->name ?? '-',
                'item' => $itemLabel ?: '-',
                'qty_in' => $totalIn,
                'qty_out' => $totalOut,
                'note' => $row->note ?? '',
                'status' => $row->status ?? 'pending',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $code = $this->generateCode('ADJ');
        $transactedAt = $validated['transacted_at'] ?? now();

        DB::beginTransaction();
        try {
            $adjustment = StockAdjustment::create([
                'warehouse_id' => $validated['warehouse_id'],
                'code' => $code,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $row) {
                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'item_id' => $row['item_id'],
                    'unit_id' => $row['unit_id'],
                    'qty_input' => $row['qty_input'],
                    'conversion_qty' => $row['conversion_qty'],
                    'direction' => $row['direction'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);

            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan penyesuaian stok',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Penyesuaian stok berhasil disimpan',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);
        $warehouseId = $request->integer('warehouse_id');

        $import = new StockAdjustmentsImport();
        DB::beginTransaction();
        try {
            Excel::import($import, $request->file('file'));
            $items = $import->items ?? [];
            if (empty($items)) {
                throw ValidationException::withMessages([
                    'file' => 'Tidak ada data valid untuk diimport',
                ]);
            }

            $transactedAt = now();
            if (!empty($import->transacted_at)) {
                try {
                    $transactedAt = Carbon::parse($import->transacted_at);
                } catch (\Throwable $e) {
                    throw ValidationException::withMessages([
                        'file' => 'Format transacted_at tidak valid: '.$import->transacted_at,
                    ]);
                }
            }

            $adjustment = StockAdjustment::create([
                'warehouse_id' => $warehouseId,
                'code' => $this->generateCode('ADJ'),
                'note' => $import->note,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            $createdItems = 0;
            foreach ($items as $row) {
                $unit = $this->resolveInputUnit($warehouseId, (int) $row['item_id'], 0);
                $conversionQty = (int) $unit->conversion_qty;
                $qtyInput = (int) $row['qty'];
                $qty = $qtyInput * $conversionQty;
                StockService::assertWarehouseQuantity($warehouseId, (int) $row['item_id'], $qty, $unit->id);

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'item_id' => $row['item_id'],
                    'unit_id' => $unit->id,
                    'qty_input' => $qtyInput,
                    'conversion_qty' => $conversionQty,
                    'direction' => $row['direction'],
                    'qty' => $qty,
                    'note' => $row['note'] ?? null,
                ]);
                $createdItems++;

            }

            DB::commit();

            return response()->json([
                'message' => 'Import penyesuaian stok berhasil',
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import penyesuaian stok',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id)
    {
        $adjustment = StockAdjustment::with('items')
            ->findOrFail($id);

        return response()->json([
            'id' => $adjustment->id,
            'code' => $adjustment->code,
            'note' => $adjustment->note,
            'status' => $adjustment->status ?? 'pending',
            'warehouse_id' => $adjustment->warehouse_id ?: Warehouse::defaultId(),
            'transacted_at' => $adjustment->transacted_at?->format('Y-m-d H:i'),
            'items' => $adjustment->items->map(function ($row) {
                return [
                    'item_id' => $row->item_id,
                    'unit_id' => $row->unit_id,
                    'qty_input' => (int) ($row->qty_input ?: $row->qty),
                    'conversion_qty' => (int) ($row->conversion_qty ?: 1),
                    'direction' => $row->direction,
                    'qty' => (int) $row->qty,
                    'note' => $row->note ?? '',
                ];
            })->values(),
        ]);
    }

    public function detail(int $id)
    {
        $adjustment = StockAdjustment::with(['items.item', 'items.unit', 'creator', 'approver', 'warehouse'])
            ->findOrFail($id);

        $totalIn = (int) $adjustment->items->where('direction', 'in')->sum('qty');
        $totalOut = (int) $adjustment->items->where('direction', 'out')->sum('qty');

        return view('admin.inventory.stock-adjustments.detail', [
            'adjustment' => $adjustment,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'netQty' => $totalIn - $totalOut,
            'backUrl' => route('admin.inventory.stock-adjustments.index'),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $adjustment = StockAdjustment::findOrFail($id);
            if (($adjustment->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa diubah'], 422);
            }

            StockService::rollbackBySource('adjustment', $adjustment->id);
            StockMutation::where('source_type', 'adjustment')
                ->where('source_id', $adjustment->id)
                ->delete();
            StockAdjustmentItem::where('stock_adjustment_id', $adjustment->id)->delete();

            $adjustment->update([
                'warehouse_id' => $validated['warehouse_id'],
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? now(),
            ]);

            foreach ($validated['items'] as $row) {
                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'item_id' => $row['item_id'],
                    'unit_id' => $row['unit_id'],
                    'qty_input' => $row['qty_input'],
                    'conversion_qty' => $row['conversion_qty'],
                    'direction' => $row['direction'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);

            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui penyesuaian stok',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Penyesuaian stok berhasil diperbarui',
        ]);
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $adjustment = StockAdjustment::findOrFail($id);
            if (($adjustment->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa dihapus'], 422);
            }
            StockService::rollbackBySource('adjustment', $adjustment->id);
            StockMutation::where('source_type', 'adjustment')
                ->where('source_id', $adjustment->id)
                ->delete();
            StockAdjustmentItem::where('stock_adjustment_id', $adjustment->id)->delete();
            $adjustment->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus penyesuaian stok',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Penyesuaian stok berhasil dihapus',
        ]);
    }

    public function approve(int $id)
    {
        DB::beginTransaction();
        try {
            $adjustment = StockAdjustment::where('id', $id)->lockForUpdate()->firstOrFail();
            if (($adjustment->status ?? 'pending') === 'approved') {
                DB::commit();
                return response()->json(['message' => 'Data sudah disetujui']);
            }

            $this->postStockMovements($adjustment);

            $adjustment->status = 'approved';
            $adjustment->approved_at = now();
            $adjustment->approved_by = auth()->id();
            $adjustment->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyetujui penyesuaian stok',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Penyesuaian stok berhasil disetujui']);
    }

    private function postStockMovements(StockAdjustment $adjustment): void
    {
        $adjustment->loadMissing('items');
        foreach ($adjustment->items as $row) {
            StockService::mutate([
                'warehouse_id' => $adjustment->warehouse_id ?: Warehouse::defaultId(),
                'item_id' => $row->item_id,
                'unit_id' => $row->unit_id,
                'direction' => $row->direction,
                'qty' => $row->qty,
                'qty_input' => $row->qty_input ?: $row->qty,
                'conversion_qty' => $row->conversion_qty ?: 1,
                'source_type' => 'adjustment',
                'source_subtype' => 'manual',
                'source_id' => $adjustment->id,
                'source_code' => $adjustment->code,
                'note' => $row->note ?? null,
                'occurred_at' => $adjustment->transacted_at ?? now(),
                'created_by' => auth()->id(),
                'idempotency_key' => StockService::idempotencyKey(['stock', 'adjustment', $adjustment->id, $row->item_id, $row->direction]),
            ]);
        }
    }

    private function validatePayload(Request $request): array
    {
        $rawItems = $request->input('items', []);
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.direction' => ['required', 'string', Rule::in(['in', 'out'])],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:item_units,id'],
            'items.*.qty_input' => ['nullable', 'integer', 'min:1'],
            'items.*.qty' => ['nullable', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);
        $validated['warehouse_id'] = (int) ($validated['warehouse_id'] ?? Warehouse::defaultId());

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['qty_input'] ?? $row['qty'] ?? 0) > 0 && (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row, $index) use ($validated, $rawItems) {
                $itemId = (int) $row['item_id'];
                $unit = $this->resolveInputUnit(
                    $validated['warehouse_id'],
                    $itemId,
                    (int) ($row['unit_id'] ?? 0),
                    !isset($rawItems[$index]['qty_input'])
                );
                $qtyInput = (int) ($row['qty_input'] ?? $row['qty'] ?? 0);
                $conversionQty = (int) ($unit?->conversion_qty ?? 1);
                return [
                    'item_id' => $itemId,
                    'unit_id' => $unit?->id,
                    'qty_input' => $qtyInput,
                    'conversion_qty' => $conversionQty,
                    'direction' => $row['direction'] === 'out' ? 'out' : 'in',
                    'qty' => $qtyInput * $conversionQty,
                    'note' => $row['note'] ?? null,
                ];
            })->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal 1 item diperlukan',
            ]);
        }

        foreach ($items as $row) {
            StockService::assertWarehouseQuantity(
                $validated['warehouse_id'],
                (int) $row['item_id'],
                (int) $row['qty'],
                $row['unit_id'] ? (int) $row['unit_id'] : null
            );
        }

        $duplicates = $items->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item tidak boleh duplikat pada penyesuaian stok',
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
            throw ValidationException::withMessages(['items' => 'Gudang Besar wajib menggunakan satuan koli/kemasan.']);
        }

        return $unit;
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
                $query->where('stock_adjustments.transacted_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('stock_adjustments.transacted_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }
}
