<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedAllocation;
use App\Models\DamagedAllocationItem;
use App\Models\DamagedItemStock;
use App\Models\Item;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use App\Models\Warehouse;
use App\Support\DamagedStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DamagedAllocationController extends Controller
{
    public function index()
    {
        $items = Item::orderBy('name')->get(['id', 'sku', 'name']);
        $damagedStocks = DamagedItemStock::query()
            ->get(['warehouse_id', 'item_id', 'stock', 'reserved_stock'])
            ->groupBy('warehouse_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [
                (string) $row->item_id => max(0, (int) $row->stock - (int) $row->reserved_stock),
            ]));

        return view('admin.inventory.damaged-allocations.index', [
            'items' => $items,
            'damagedStocks' => $damagedStocks,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'is_default']),
            'dataUrl' => route('admin.inventory.damaged-allocations.data'),
            'storeUrl' => route('admin.inventory.damaged-allocations.store'),
        ]);
    }

    public function data(Request $request)
    {
        $query = DamagedAllocation::query()
            ->with(['items.item', 'creator', 'warehouse'])
            ->orderByDesc('transacted_at');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('ref_no', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = DamagedAllocation::count();
        $recordsFiltered = (clone $query)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $typeLabels = $this->allocationTypeLabels();
        $data = $query->get()->map(function ($row) use ($typeLabels) {
            $items = $row->items ?? collect();
            $labels = $items->map(function ($it) {
                $sku = trim((string) ($it->item?->sku ?? ''));
                return $sku === '' ? '' : sprintf('%s (%d)', $sku, (int) $it->qty);
            })->filter()->values();

            return [
                'id' => $row->id,
                'code' => $row->code,
                'warehouse' => $row->warehouse?->name ?? '-',
                'allocation_type' => $typeLabels[$row->allocation_type] ?? $row->allocation_type,
                'ref_no' => $row->ref_no ?? '',
                'transacted_at' => $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '',
                'submit_by' => $row->creator?->name ?? '-',
                'item' => $labels->implode(', ') ?: '-',
                'qty' => (int) $items->sum('qty'),
                'note' => $row->note ?? '',
                'status' => $row->status ?? 'pending',
                'outbound_transaction_id' => $row->outbound_transaction_id,
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

        DB::beginTransaction();
        try {
            $allocation = DamagedAllocation::create([
                'warehouse_id' => $validated['warehouse_id'],
                'code' => $this->generateCode('DMG-ALC'),
                'allocation_type' => $validated['allocation_type'],
                'ref_no' => $validated['ref_no'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? now(),
                'note' => $validated['note'] ?? null,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $row) {
                DamagedAllocationItem::create([
                    'damaged_allocation_id' => $allocation->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);
            }

            // Reservasi stok secara FIFO — siapa duluan simpan, dia yang dapat jatah
            foreach ($validated['items'] as $row) {
                DamagedStockService::reserve($row['item_id'], $row['qty'], $validated['warehouse_id']);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan alokasi barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Alokasi barang rusak berhasil disimpan']);
    }

    public function show(int $id)
    {
        $allocation = DamagedAllocation::with('items')->findOrFail($id);

        return response()->json([
            'id' => $allocation->id,
            'code' => $allocation->code,
            'warehouse_id' => $allocation->warehouse_id ?: Warehouse::defaultId(),
            'allocation_type' => $allocation->allocation_type,
            'ref_no' => $allocation->ref_no,
            'note' => $allocation->note,
            'status' => $allocation->status ?? 'pending',
            'transacted_at' => $allocation->transacted_at?->format('Y-m-d H:i'),
            'items' => $allocation->items->map(fn ($row) => [
                'item_id' => $row->item_id,
                'qty' => (int) $row->qty,
                'note' => $row->note ?? '',
            ])->values(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $allocation = DamagedAllocation::with('items')->findOrFail($id);
            if (($allocation->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa diubah'], 422);
            }

            // Lepas reservasi lama sebelum item diganti
            foreach ($allocation->items as $row) {
                DamagedStockService::releaseReservation($row->item_id, $row->qty, $allocation->warehouse_id);
            }

            DamagedAllocationItem::where('damaged_allocation_id', $allocation->id)->delete();
            $allocation->update([
                'warehouse_id' => $validated['warehouse_id'],
                'allocation_type' => $validated['allocation_type'],
                'ref_no' => $validated['ref_no'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? now(),
                'note' => $validated['note'] ?? null,
            ]);

            foreach ($validated['items'] as $row) {
                DamagedAllocationItem::create([
                    'damaged_allocation_id' => $allocation->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);
            }

            // Reservasi ulang dengan item baru
            foreach ($validated['items'] as $row) {
                DamagedStockService::reserve($row['item_id'], $row['qty'], $validated['warehouse_id']);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui alokasi barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Alokasi barang rusak berhasil diperbarui']);
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $allocation = DamagedAllocation::with('items')->findOrFail($id);
            if (($allocation->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa dihapus'], 422);
            }

            // Lepas reservasi stok untuk alokasi pending yang dihapus
            foreach ($allocation->items as $row) {
                DamagedStockService::releaseReservation($row->item_id, $row->qty, $allocation->warehouse_id);
            }

            $allocation->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus alokasi barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Alokasi barang rusak berhasil dihapus']);
    }

    public function approve(int $id)
    {
        DB::beginTransaction();
        try {
            $allocation = DamagedAllocation::where('id', $id)->lockForUpdate()->firstOrFail();
            if (($allocation->status ?? 'pending') === 'approved') {
                DB::commit();
                return response()->json(['message' => 'Data sudah disetujui']);
            }

            $allocation->loadMissing('items');
            if (($allocation->allocation_type ?? '') === 'return_vendor') {
                $this->createOutboundReturnForAllocation($allocation);
            } else {
                foreach ($allocation->items as $row) {
                    DamagedStockService::mutate([
                        'warehouse_id' => $allocation->warehouse_id ?: Warehouse::defaultId(),
                        'item_id' => $row->item_id,
                        'direction' => 'out',
                        'qty' => $row->qty,
                        'source_type' => 'damaged_allocation',
                        'source_subtype' => $allocation->allocation_type,
                        'source_id' => $allocation->id,
                        'source_code' => $allocation->code,
                        'note' => $row->note ?? null,
                        'occurred_at' => $allocation->transacted_at ?? now(),
                        'created_by' => auth()->id(),
                        'idempotency_key' => DamagedStockService::idempotencyKey(['damaged-stock', 'allocation', $allocation->id, $row->item_id]),
                    ]);
                }
            }

            $allocation->status = 'approved';
            $allocation->approved_at = now();
            $allocation->approved_by = auth()->id();
            $allocation->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyetujui alokasi barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Alokasi barang rusak berhasil disetujui'
                .($allocation->allocation_type === 'return_vendor' ? ' dan retur outbound berhasil dibuat serta disetujui' : ''),
        ]);
    }

    private function createOutboundReturnForAllocation(DamagedAllocation $allocation): OutboundTransaction
    {
        if ($allocation->outbound_transaction_id) {
            return OutboundTransaction::lockForUpdate()->findOrFail($allocation->outbound_transaction_id);
        }

        $existing = OutboundTransaction::where('type', 'return')
            ->where('ref_no', $allocation->code)
            ->lockForUpdate()
            ->first();
        if ($existing) {
            $allocation->outbound_transaction_id = $existing->id;
            $allocation->save();
            $this->approveOutboundReturnForAllocation($existing);
            return $existing;
        }

        $tx = OutboundTransaction::create([
            'warehouse_id' => $allocation->warehouse_id ?: Warehouse::defaultId(),
            'code' => $this->generateCode('OUT-RET'),
            'type' => 'return',
            'ref_no' => $allocation->code,
            'note' => 'Otomatis dari alokasi barang rusak '.$allocation->code,
            'transacted_at' => $allocation->transacted_at ?? now(),
            'created_by' => auth()->id(),
            'status' => 'pending',
        ]);

        foreach ($allocation->items as $row) {
            OutboundItem::create([
                'outbound_transaction_id' => $tx->id,
                'item_id' => $row->item_id,
                'stock_source' => 'damaged',
                'qty' => $row->qty,
                'note' => $row->note ?? 'Dari alokasi barang rusak '.$allocation->code,
            ]);
        }

        $allocation->outbound_transaction_id = $tx->id;
        $allocation->save();

        $this->approveOutboundReturnForAllocation($tx);

        return $tx;
    }

    private function approveOutboundReturnForAllocation(OutboundTransaction $tx): void
    {
        $tx->loadMissing('items');

        foreach ($tx->items as $row) {
            DamagedStockService::mutate([
                'warehouse_id' => $tx->warehouse_id ?: Warehouse::defaultId(),
                'item_id' => $row->item_id,
                'direction' => 'out',
                'qty' => $row->qty,
                'source_type' => 'outbound',
                'source_subtype' => 'return',
                'source_id' => $tx->id,
                'source_code' => $tx->code,
                'note' => $row->note ?? null,
                'occurred_at' => $tx->transacted_at ?? now(),
                'created_by' => auth()->id(),
                'idempotency_key' => DamagedStockService::idempotencyKey(['stock', 'outbound', 'return', 'damaged', $tx->id, $row->item_id]),
            ]);
        }

        if (($tx->status ?? 'pending') !== 'approved') {
            $tx->status = 'approved';
            $tx->approved_at = now();
            $tx->approved_by = auth()->id();
            $tx->save();
        }
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'allocation_type' => ['required', 'string', Rule::in(array_keys($this->allocationTypeLabels()))],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'ref_no' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
        ]);

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['qty'] ?? 0) > 0 && (int) ($row['item_id'] ?? 0) > 0)
            ->map(fn ($row) => [
                'item_id' => (int) $row['item_id'],
                'qty' => (int) $row['qty'],
                'note' => $row['note'] ?? null,
            ])->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Minimal 1 item diperlukan']);
        }

        if ($items->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1)->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Item tidak boleh duplikat pada alokasi barang rusak']);
        }

        $validated['items'] = $items->all();
        $validated['warehouse_id'] = (int) ($validated['warehouse_id'] ?? Warehouse::defaultId());
        $validated['transacted_at'] = Carbon::parse($validated['transacted_at']);

        return $validated;
    }

    private function allocationTypeLabels(): array
    {
        return [
            'dispose' => 'Dimusnahkan',
            'repair' => 'Diperbaiki',
            'return_vendor' => 'Dikembalikan ke Gudang Besar',
            'other' => 'Lainnya',
        ];
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
