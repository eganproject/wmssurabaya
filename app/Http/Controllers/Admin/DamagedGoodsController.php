<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DamagedGoodsTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\DamagedGoodsImport;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\DamagedStockMutation;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\DamagedStockService;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class DamagedGoodsController extends Controller
{
    public function index()
    {
        $items = Item::with(['units' => fn ($query) => $query->orderByDesc('is_base')->orderBy('conversion_qty')])
            ->orderBy('name')->get(['id', 'sku', 'name']);
        $smallWarehouse = Warehouse::findOrFail(Warehouse::smallId());

        return view('admin.inventory.damaged-goods.index', [
            'items' => $items,
            'smallWarehouse' => $smallWarehouse,
            'dataUrl' => route('admin.inventory.damaged-goods.data'),
            'storeUrl' => route('admin.inventory.damaged-goods.store'),
            'stockSummaryUrl' => route('admin.inventory.damaged-goods.stock-summary'),
            'importUrl' => route('admin.inventory.damaged-goods.import'),
            'templateUrl' => route('admin.inventory.damaged-goods.template'),
        ]);
    }

    public function template()
    {
        return Excel::download(
            new DamagedGoodsTemplateExport(),
            'template-import-barang-rusak.xlsx'
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);
        $warehouseId = Warehouse::smallId();

        $import = new DamagedGoodsImport();
        DB::beginTransaction();
        try {
            Excel::import($import, $request->file('file'));
            $groups = $import->groups ?? [];
            if (empty($groups)) {
                throw ValidationException::withMessages([
                    'file' => 'Tidak ada data valid untuk diimport',
                ]);
            }

            $createdTx = 0;
            $createdItems = 0;
            foreach ($groups as $group) {
                $transactedAt = now();
                if (!empty($group['transacted_at'])) {
                    try {
                        $transactedAt = Carbon::parse($group['transacted_at']);
                    } catch (\Throwable $e) {
                        throw ValidationException::withMessages([
                            'file' => 'Format transacted_at tidak valid: '.$group['transacted_at'],
                        ]);
                    }
                }

                $damage = DamagedGood::create([
                    'warehouse_id' => $warehouseId,
                    'code' => $this->generateCode('DMG'),
                    'source_type' => $group['source_type'],
                    'source_ref' => $group['source_ref'] ?? null,
                    'note' => $group['note'] ?? null,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'pending',
                ]);
                $createdTx++;

                foreach ($group['items'] as $row) {
                    $unit = $this->resolveInputUnit($warehouseId, (int) $row['item_id'], 0);
                    $conversionQty = (int) $unit->conversion_qty;
                    $qtyInput = (int) $row['qty'];
                    DamagedGoodItem::create([
                        'damaged_good_id' => $damage->id,
                        'item_id' => $row['item_id'],
                        'unit_id' => $unit->id,
                        'qty_input' => $qtyInput,
                        'conversion_qty' => $conversionQty,
                        'qty' => $qtyInput * $conversionQty,
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import barang rusak berhasil. Silakan tinjau lalu setujui datanya.',
                'transactions' => $createdTx,
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function stockSummary(Request $request)
    {
        $warehouseId = Warehouse::smallId();
        $query = Item::query()
            ->join('damaged_item_stocks', 'damaged_item_stocks.item_id', '=', 'items.id')
            ->where('damaged_item_stocks.warehouse_id', $warehouseId)
            ->where('damaged_item_stocks.stock', '>', 0)
            ->orderBy('items.name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('items.sku', 'like', "%{$search}%")
                    ->orWhere('items.name', 'like', "%{$search}%");
            });
        }

        $recordsTotal = Item::join('damaged_item_stocks', 'damaged_item_stocks.item_id', '=', 'items.id')
            ->where('damaged_item_stocks.warehouse_id', $warehouseId)
            ->where('damaged_item_stocks.stock', '>', 0)->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get([
            'items.id',
            'items.sku',
            'items.name',
            DB::raw('damaged_item_stocks.stock as stock'),
            DB::raw('COALESCE(damaged_item_stocks.reserved_stock, 0) as reserved_stock'),
            DB::raw('GREATEST(0, damaged_item_stocks.stock - COALESCE(damaged_item_stocks.reserved_stock, 0)) as available_stock'),
        ])->map(fn ($row) => [
            'sku'             => $row->sku,
            'name'            => $row->name,
            'stock'           => (int) $row->stock,
            'reserved_stock'  => (int) $row->reserved_stock,
            'available_stock' => (int) $row->available_stock,
        ]);

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function data(Request $request)
    {
        $query = DamagedGood::query()
            ->with(['items.item', 'items.unit', 'creator', 'warehouse'])
            ->orderBy('transacted_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('damaged_goods.code', 'like', "%{$search}%")
                    ->orWhere('damaged_goods.source_ref', 'like', "%{$search}%")
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = DamagedGood::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $sourceLabels = $this->sourceLabels();
        $data = $query->get()->map(function ($row) use ($sourceLabels) {
            $items = $row->items ?? collect();
            $labels = $items->map(function ($it) {
                $sku = trim($it->item?->sku ?? '');
                if ($sku === '') {
                    return '';
                }
                $qty = (int) ($it->qty_input ?: $it->qty);
                $unit = $it->unit?->name ?: 'satuan dasar';
                return sprintf('%s (%d %s)', $sku, $qty, $unit);
            })->filter()->values();
            $itemLabel = $labels->implode(', ');
            $ts = $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '';
            $note = $row->note ?? '';
            $totalQty = (int) $items->sum('qty');
            return [
                'id' => $row->id,
                'code' => $row->code,
                'warehouse' => $row->warehouse?->name ?? '-',
                'source' => $sourceLabels[$row->source_type] ?? $row->source_type,
                'source_ref' => $row->source_ref ?? '',
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'item' => $itemLabel ?: '-',
                'qty' => $totalQty,
                'note' => $note,
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

        $code = $this->generateCode('DMG');
        $transactedAt = $validated['transacted_at'] ?? now();

        DB::beginTransaction();
        try {
            $damage = DamagedGood::create([
                'warehouse_id' => $validated['warehouse_id'],
                'code' => $code,
                'source_type' => $validated['source_type'],
                'source_ref' => $validated['source_ref'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $row) {
                DamagedGoodItem::create([
                    'damaged_good_id' => $damage->id,
                    'item_id' => $row['item_id'],
                    'unit_id' => $row['unit_id'],
                    'qty_input' => $row['qty_input'],
                    'conversion_qty' => $row['conversion_qty'],
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
                'message' => 'Gagal menyimpan barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Barang rusak berhasil disimpan',
        ]);
    }

    public function show(int $id)
    {
        $damage = DamagedGood::with('items')
            ->findOrFail($id);

        return response()->json([
            'id' => $damage->id,
            'code' => $damage->code,
            'warehouse_id' => $damage->warehouse_id ?: Warehouse::defaultId(),
            'source_type' => $damage->source_type,
            'source_ref' => $damage->source_ref,
            'note' => $damage->note,
            'status' => $damage->status ?? 'pending',
            'transacted_at' => $damage->transacted_at?->format('Y-m-d H:i'),
            'items' => $damage->items->map(function ($row) {
                return [
                    'item_id' => $row->item_id,
                    'unit_id' => $row->unit_id,
                    'qty_input' => (int) ($row->qty_input ?: $row->qty),
                    'conversion_qty' => (int) ($row->conversion_qty ?: 1),
                    'qty' => (int) $row->qty,
                    'note' => $row->note,
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $damage = DamagedGood::findOrFail($id);
            if (($damage->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa diubah'], 422);
            }

            StockService::rollbackBySource('damaged', $damage->id);
            StockMutation::where('source_type', 'damaged')
                ->where('source_id', $damage->id)
                ->delete();
            DamagedStockService::rollbackBySource('damaged', $damage->id);
            DamagedStockMutation::where('source_type', 'damaged')
                ->where('source_id', $damage->id)
                ->delete();
            DamagedGoodItem::where('damaged_good_id', $damage->id)->delete();

            $damage->update([
                'warehouse_id' => $validated['warehouse_id'],
                'source_type' => $validated['source_type'],
                'source_ref' => $validated['source_ref'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? now(),
            ]);

            foreach ($validated['items'] as $row) {
                DamagedGoodItem::create([
                    'damaged_good_id' => $damage->id,
                    'item_id' => $row['item_id'],
                    'unit_id' => $row['unit_id'],
                    'qty_input' => $row['qty_input'],
                    'conversion_qty' => $row['conversion_qty'],
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
                'message' => 'Gagal memperbarui barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Barang rusak berhasil diperbarui',
        ]);
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $damage = DamagedGood::findOrFail($id);
            if (($damage->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa dihapus'], 422);
            }
            StockService::rollbackBySource('damaged', $damage->id);
            StockMutation::where('source_type', 'damaged')
                ->where('source_id', $damage->id)
                ->delete();
            DamagedStockService::rollbackBySource('damaged', $damage->id);
            DamagedStockMutation::where('source_type', 'damaged')
                ->where('source_id', $damage->id)
                ->delete();
            DamagedGoodItem::where('damaged_good_id', $damage->id)->delete();
            $damage->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Barang rusak berhasil dihapus',
        ]);
    }

    public function approve(int $id)
    {
        DB::beginTransaction();
        try {
            $damage = DamagedGood::where('id', $id)->lockForUpdate()->firstOrFail();
            if (($damage->status ?? 'pending') === 'approved') {
                DB::commit();
                return response()->json(['message' => 'Data sudah disetujui']);
            }

            if (($damage->source_type ?? '') === 'inbound_return') {
                $this->createFinalizedInboundReturnForDamage($damage);
            } else {
                $this->postStockMovements($damage);
            }

            $damage->status = 'approved';
            $damage->approved_at = now();
            $damage->approved_by = auth()->id();
            $damage->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyetujui barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Barang rusak berhasil disetujui'
                .($damage->source_type === 'inbound_return' ? ' dan retur inbound berhasil dibuat' : ''),
        ]);
    }

    private function createFinalizedInboundReturnForDamage(DamagedGood $damage): InboundTransaction
    {
        $damage->loadMissing('items');

        if ($damage->inbound_transaction_id) {
            return InboundTransaction::lockForUpdate()->findOrFail($damage->inbound_transaction_id);
        }

        $existing = null;
        if (!empty($damage->source_ref)) {
            $existing = InboundTransaction::where('type', 'return')
                ->where('code', $damage->source_ref)
                ->lockForUpdate()
                ->first();
        }

        if (!$existing) {
            $existing = InboundTransaction::where('type', 'return')
                ->where('ref_no', $damage->code)
                ->lockForUpdate()
                ->first();
        }

        if ($existing) {
            if (!$existing->items()->exists()) {
                foreach ($damage->items as $row) {
                    InboundItem::create([
                        'inbound_transaction_id' => $existing->id,
                        'item_id' => $row->item_id,
                        'qty_input' => $row->qty,
                        'conversion_qty' => 1,
                        'qty' => $row->qty,
                        'qty_received' => $row->qty,
                        'qty_good' => 0,
                        'qty_damaged' => $row->qty,
                        'qty_missing' => 0,
                        'note' => $row->note ?? null,
                    ]);
                }
            }

            $damage->inbound_transaction_id = $existing->id;
            $damage->source_ref = $existing->code;
            $damage->save();
            $this->finalizeInboundReturnFromDamage($existing, $damage);
            return $existing;
        }

        $tx = InboundTransaction::create([
            'warehouse_id' => $damage->warehouse_id ?: Warehouse::defaultId(),
            'code' => $this->generateCode('INB-RET'),
            'type' => 'return',
            'ref_no' => $damage->code,
            'note' => 'Otomatis dari barang rusak '.$damage->code,
            'transacted_at' => $damage->transacted_at ?? now(),
            'created_by' => auth()->id(),
            'status' => 'finalized',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),
        ]);

        foreach ($damage->items as $row) {
            InboundItem::create([
                'inbound_transaction_id' => $tx->id,
                'item_id' => $row->item_id,
                'qty_input' => $row->qty,
                'conversion_qty' => 1,
                'qty' => $row->qty,
                'qty_received' => $row->qty,
                'qty_good' => 0,
                'qty_damaged' => $row->qty,
                'qty_missing' => 0,
                'note' => $row->note ?? null,
            ]);

        }

        $damage->inbound_transaction_id = $tx->id;
        $damage->source_ref = $tx->code;
        $damage->save();
        $this->finalizeInboundReturnFromDamage($tx, $damage);

        return $tx;
    }

    private function finalizeInboundReturnFromDamage(InboundTransaction $tx, DamagedGood $damage): void
    {
        $damage->loadMissing('items');

        foreach ($damage->items as $row) {
            DamagedStockService::mutate([
                'warehouse_id' => $damage->warehouse_id ?: Warehouse::defaultId(),
                'item_id' => $row->item_id,
                'direction' => 'in',
                'qty' => $row->qty,
                'source_type' => 'inbound_return',
                'source_subtype' => 'approval',
                'source_id' => $tx->id,
                'source_code' => $tx->code,
                'note' => $row->note ?? 'Inbound retur masuk stok barang rusak',
                'occurred_at' => $tx->transacted_at ?? now(),
                'created_by' => auth()->id(),
                'idempotency_key' => DamagedStockService::idempotencyKey(['damaged-stock', 'inbound-return', $tx->id, $row->item_id]),
            ]);
        }

        if (($tx->status ?? 'pending') !== 'finalized') {
            $tx->status = 'finalized';
            $tx->approved_at = $tx->approved_at ?? now();
            $tx->approved_by = $tx->approved_by ?? auth()->id();
            $tx->finalized_at = now();
            $tx->finalized_by = auth()->id();
            $tx->save();
        }
    }

    private function postStockMovements(DamagedGood $damage): void
    {
        $damage->loadMissing('items');
        foreach ($damage->items as $row) {
            if ($damage->source_type === 'display') {
                StockService::mutate([
                    'warehouse_id' => $damage->warehouse_id ?: Warehouse::defaultId(),
                    'item_id' => $row->item_id,
                    'unit_id' => $row->unit_id,
                    'direction' => 'out',
                    'qty' => $row->qty,
                    'qty_input' => $row->qty_input ?: $row->qty,
                    'conversion_qty' => $row->conversion_qty ?: 1,
                    'source_type' => 'damaged',
                    'source_subtype' => $damage->source_type,
                    'source_id' => $damage->id,
                    'source_code' => $damage->code,
                    'note' => $row->note ?? null,
                    'occurred_at' => $damage->transacted_at ?? now(),
                    'created_by' => auth()->id(),
                    'idempotency_key' => StockService::idempotencyKey(['stock', 'damaged', $damage->source_type, $damage->id, $row->item_id]),
                ]);
            }

            DamagedStockService::mutate([
                'warehouse_id' => $damage->warehouse_id ?: Warehouse::defaultId(),
                'item_id' => $row->item_id,
                'direction' => 'in',
                'qty' => $row->qty,
                'source_type' => 'damaged',
                'source_subtype' => $damage->source_type,
                'source_id' => $damage->id,
                'source_code' => $damage->code,
                'note' => $row->note ?? null,
                'occurred_at' => $damage->transacted_at ?? now(),
                'created_by' => auth()->id(),
                'idempotency_key' => DamagedStockService::idempotencyKey(['damaged-stock', 'damaged', $damage->source_type, $damage->id, $row->item_id]),
            ]);
        }
    }

    private function validatePayload(Request $request): array
    {
        $rawItems = $request->input('items', []);
        $validated = $request->validate([
            'source_type' => ['required', 'string', Rule::in(['display', 'inbound_return'])],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'source_ref' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:item_units,id'],
            'items.*.qty_input' => ['nullable', 'integer', 'min:1'],
            'items.*.qty' => ['nullable', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
        ]);

        $validated['warehouse_id'] = Warehouse::smallId();
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
                    'qty' => $qtyInput * $conversionQty,
                    'note' => $row['note'] ?? null,
                ];
            })->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal 1 item diperlukan',
            ]);
        }

        $duplicates = $items->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item tidak boleh duplikat pada barang rusak',
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
        if ($warehouse->type === Warehouse::TYPE_FULFILLMENT && !$unit->is_base) {
            throw ValidationException::withMessages(['items' => 'Barang rusak Gudang Kecil wajib menggunakan satuan PCS/SET.']);
        }

        return $unit;
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function sourceLabels(): array
    {
        return [
            'display' => 'Display',
            'inbound_return' => 'Retur Inbound',
        ];
    }
}
