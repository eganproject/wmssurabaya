<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $items = Item::where('is_bundle', false)
            ->with(['units' => fn ($q) => $q->orderBy('id')])
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);

        return view('admin.inventory.stock-transfers.index', compact('warehouses', 'items'));
    }

    public function data(Request $request)
    {
        $query = StockTransfer::with([
            'sourceWarehouse',
            'destinationWarehouse',
            'items.unit',
            'items.receivedUnit',
            'creator',
        ])
            ->orderByDesc('transacted_at');
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhereHas('sourceWarehouse', fn ($w) => $w->where('name', 'like', "%{$search}%"))
                ->orWhereHas('destinationWarehouse', fn ($w) => $w->where('name', 'like', "%{$search}%"))
                ->orWhereHas('items.item', fn ($item) => $item
                    ->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")));
        }
        if ($status = trim((string) $request->input('status'))) {
            $query->where('status', $status);
        }
        if ($warehouseId = $request->integer('warehouse_id')) {
            $query->where(fn ($q) => $q
                ->where('source_warehouse_id', $warehouseId)
                ->orWhere('destination_warehouse_id', $warehouseId));
        }

        $rows = $query->get()->map(function ($transfer) {
            $sentSummary = $transfer->items
                ->groupBy(fn ($item) => $item->unit?->name ?? 'UNIT')
                ->map(fn ($items, $unit) => $items->sum('qty_input').' '.$unit)
                ->implode(', ');
            $sentBase = (int) $transfer->items->sum('qty_base');
            $receivedBase = (int) $transfer->items->sum('qty_received_base');
            $discrepancyBase = (int) $transfer->items->sum('qty_discrepancy_base');

            return [
                'id' => $transfer->id,
                'code' => $transfer->code,
                'source' => $transfer->sourceWarehouse?->name ?? '-',
                'destination' => $transfer->destinationWarehouse?->name ?? '-',
                'destination_type' => $transfer->destinationWarehouse?->type,
                'status' => $transfer->status,
                'transacted_at' => $transfer->transacted_at?->format('Y-m-d H:i'),
                'items_count' => $transfer->items->count(),
                'sent_summary' => $sentSummary ?: '-',
                'qty_base' => $sentBase,
                'qty_received_base' => $receivedBase,
                'qty_discrepancy_base' => $discrepancyBase,
                'creator' => $transfer->creator?->name ?? '-',
                'note' => $transfer->note ?? '',
            ];
        });

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $transfer = DB::transaction(function () use ($validated) {
            $transfer = StockTransfer::create([
                'code' => 'TRF-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'source_warehouse_id' => $validated['source_warehouse_id'],
                'destination_warehouse_id' => $validated['destination_warehouse_id'],
                'status' => 'draft',
                'transacted_at' => Carbon::parse($validated['transacted_at']),
                'note' => $validated['note'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $this->replaceItems($transfer, $validated['items']);

            return $transfer;
        });

        return response()->json(['message' => 'Transfer berhasil dibuat', 'id' => $transfer->id]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated, $id) {
            $transfer = StockTransfer::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya transfer draft yang dapat diubah']);
            }
            $transfer->update([
                'source_warehouse_id' => $validated['source_warehouse_id'],
                'destination_warehouse_id' => $validated['destination_warehouse_id'],
                'transacted_at' => Carbon::parse($validated['transacted_at']),
                'note' => $validated['note'] ?? null,
            ]);
            $transfer->items()->delete();
            $this->replaceItems($transfer, $validated['items']);
        });

        return response()->json(['message' => 'Draft transfer berhasil diperbarui']);
    }

    public function cancel(int $id)
    {
        DB::transaction(function () use ($id) {
            $transfer = StockTransfer::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($transfer->status === 'cancelled') {
                return;
            }
            if ($transfer->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya transfer draft yang dapat dibatalkan']);
            }
            $transfer->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
            ]);
        });

        return response()->json(['message' => 'Draft transfer berhasil dibatalkan']);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:destination_warehouse_id'],
            'destination_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'transacted_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.qty_input' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
        ]);

        $activeWarehouseIds = Warehouse::whereIn('id', [
            $validated['source_warehouse_id'],
            $validated['destination_warehouse_id'],
        ])->where('is_active', true)->pluck('id');
        if ($activeWarehouseIds->count() !== 2) {
            throw ValidationException::withMessages(['warehouse_id' => 'Gudang asal dan tujuan harus aktif']);
        }

        // Satuan kirim ditentukan oleh master item, bukan nilai yang dikirim browser.
        // Transfer yang menyentuh Gudang Besar selalu memakai satuan koli; antar
        // Gudang Kecil memakai satuan dasar.
        $requiresPackage = Warehouse::whereIn('id', [
            $validated['source_warehouse_id'],
            $validated['destination_warehouse_id'],
        ])->where('type', Warehouse::TYPE_BULK)->exists();
        foreach ($validated['items'] as $index => $row) {
            $unit = ItemUnit::where('item_id', $row['item_id'])
                ->where('is_base', !$requiresPackage)
                ->orderBy('id')
                ->first();
            if (!$unit) {
                throw ValidationException::withMessages([
                    "items.{$index}.item_id" => $requiresPackage
                        ? 'Satuan koli belum dikonfigurasi pada master item.'
                        : 'Satuan dasar belum dikonfigurasi pada master item.',
                ]);
            }
            $validated['items'][$index]['unit_id'] = $unit->id;
        }

        $rows = collect($validated['items'])->groupBy('item_id');
        if ($rows->contains(fn ($group) => $group->count() > 1)) {
            throw ValidationException::withMessages(['items' => 'Item transfer tidak boleh duplikat']);
        }

        return $validated;
    }

    private function replaceItems(StockTransfer $transfer, array $items): void
    {
        foreach ($items as $row) {
            $unit = ItemUnit::where('id', $row['unit_id'])
                ->where('item_id', $row['item_id'])
                ->firstOrFail();
            $qtyInput = (int) $row['qty_input'];
            $conversion = (int) $unit->conversion_qty;
            $qtyBase = $qtyInput * $conversion;
            StockService::assertWarehouseQuantity(
                (int) $transfer->source_warehouse_id,
                (int) $row['item_id'],
                $qtyBase,
                (int) $unit->id
            );
            StockService::assertWarehouseQuantity(
                (int) $transfer->destination_warehouse_id,
                (int) $row['item_id'],
                $qtyBase,
                (int) $unit->id
            );
            StockTransferItem::create([
                'stock_transfer_id' => $transfer->id,
                'item_id' => $row['item_id'],
                'unit_id' => $unit->id,
                'qty_input' => $qtyInput,
                'conversion_qty' => $conversion,
                'qty_base' => $qtyBase,
                'note' => $row['note'] ?? null,
            ]);
        }
    }

    public function show(int $id)
    {
        $transfer = StockTransfer::with([
            'sourceWarehouse',
            'destinationWarehouse',
            'creator',
            'shipper',
            'receiver',
            'canceller',
            'items.item.baseUnit',
            'items.item.packageUnit',
            'items.unit',
            'items.receivedUnit',
        ])
            ->findOrFail($id);

        return response()->json([
            'id' => $transfer->id,
            'code' => $transfer->code,
            'source_warehouse_id' => $transfer->source_warehouse_id,
            'destination_warehouse_id' => $transfer->destination_warehouse_id,
            'source_warehouse' => $transfer->sourceWarehouse,
            'destination_warehouse' => $transfer->destinationWarehouse,
            'status' => $transfer->status,
            'transacted_at' => $transfer->transacted_at?->toIso8601String(),
            'shipped_at' => $transfer->shipped_at?->toIso8601String(),
            'received_at' => $transfer->received_at?->toIso8601String(),
            'cancelled_at' => $transfer->cancelled_at?->toIso8601String(),
            'note' => $transfer->note,
            'discrepancy_note' => $transfer->discrepancy_note,
            'creator' => $transfer->creator?->name,
            'shipper' => $transfer->shipper?->name,
            'receiver' => $transfer->receiver?->name,
            'canceller' => $transfer->canceller?->name,
            'items' => $transfer->items->map(fn ($row) => [
                'id' => $row->id,
                'item_id' => $row->item_id,
                'item' => $row->item,
                'unit_id' => $row->unit_id,
                'unit' => $row->unit,
                'qty_input' => (int) $row->qty_input,
                'conversion_qty' => (int) $row->conversion_qty,
                'qty_base' => (int) $row->qty_base,
                'received_unit_id' => $row->received_unit_id,
                'received_unit' => $row->receivedUnit,
                'qty_received_unit' => (int) $row->qty_received_unit,
                'qty_received_base' => (int) $row->qty_received_base,
                'qty_discrepancy_base' => (int) $row->qty_discrepancy_base,
                'note' => $row->note,
                'discrepancy_note' => $row->discrepancy_note,
            ])->values(),
        ]);
    }

    public function ship(int $id)
    {
        DB::transaction(function () use ($id) {
            $transfer = StockTransfer::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($transfer->status === 'shipped' || $transfer->status === 'received') {
                return;
            }
            if ($transfer->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Status transfer tidak dapat dikirim']);
            }

            $transfer->load('items');
            foreach ($transfer->items as $row) {
                StockService::mutate([
                    'warehouse_id' => $transfer->source_warehouse_id,
                    'item_id' => $row->item_id,
                    'unit_id' => $row->unit_id,
                    'direction' => 'out',
                    'qty' => $row->qty_base,
                    'qty_input' => $row->qty_input,
                    'conversion_qty' => $row->conversion_qty,
                    'source_type' => 'transfer',
                    'source_subtype' => 'ship',
                    'source_id' => $transfer->id,
                    'source_code' => $transfer->code,
                    'note' => $row->note,
                    'occurred_at' => now(),
                    'created_by' => auth()->id(),
                    'idempotency_key' => StockService::idempotencyKey(['transfer', 'ship', $transfer->id, $row->item_id]),
                ]);
            }
            $transfer->update(['status' => 'shipped', 'shipped_at' => now(), 'shipped_by' => auth()->id()]);
        });

        return response()->json(['message' => 'Transfer sudah dikirim']);
    }

    public function receive(Request $request, int $id)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty_received' => ['required', 'integer', 'min:0'],
            'items.*.discrepancy_note' => ['nullable', 'string'],
            'discrepancy_note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($id, $validated) {
            $transfer = StockTransfer::whereKey($id)->lockForUpdate()->firstOrFail();
            if (in_array($transfer->status, ['received', 'received_with_discrepancy'], true)) {
                return;
            }
            if ($transfer->status !== 'shipped') {
                throw ValidationException::withMessages(['status' => 'Transfer harus dikirim sebelum diterima']);
            }

            $transfer->load(['items.item.baseUnit', 'items.item.packageUnit', 'destinationWarehouse']);
            $receivedRows = collect($validated['items'])->keyBy(fn ($row) => (int) $row['item_id']);
            if ($receivedRows->count() !== $transfer->items->count()) {
                throw ValidationException::withMessages(['items' => 'Seluruh item transfer wajib dikonfirmasi']);
            }

            $hasDiscrepancy = false;
            foreach ($transfer->items as $row) {
                $received = $receivedRows->get($row->item_id);
                if (!$received) {
                    throw ValidationException::withMessages(['items' => 'Item penerimaan transfer tidak valid']);
                }
                $qtyReceivedUnit = (int) $received['qty_received'];
                $isDestinationBulk = $transfer->destinationWarehouse?->type === Warehouse::TYPE_BULK;
                $receivedUnit = $isDestinationBulk
                    ? $row->item?->packageUnit
                    : $row->item?->baseUnit;
                if (!$receivedUnit) {
                    throw ValidationException::withMessages([
                        'items' => $isDestinationBulk
                            ? 'Satuan koli item belum dikonfigurasi.'
                            : 'Satuan dasar item belum dikonfigurasi.',
                    ]);
                }

                $qtyReceivedBase = $qtyReceivedUnit * (int) $receivedUnit->conversion_qty;
                if ($qtyReceivedBase > (int) $row->qty_base) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty diterima tidak boleh melebihi qty yang dikirim.',
                    ]);
                }
                $qtyDiscrepancyBase = (int) $row->qty_base - $qtyReceivedBase;
                $isDiscrepancy = $qtyDiscrepancyBase !== 0;
                $hasDiscrepancy = $hasDiscrepancy || $isDiscrepancy;
                if ($isDiscrepancy && trim((string) ($received['discrepancy_note'] ?? '')) === '') {
                    throw ValidationException::withMessages(['items' => 'Alasan selisih wajib diisi untuk qty yang tidak lengkap']);
                }

                if ($qtyReceivedBase > 0) {
                    StockService::mutate([
                        'warehouse_id' => $transfer->destination_warehouse_id,
                        'item_id' => $row->item_id,
                        'unit_id' => $receivedUnit->id,
                        'direction' => 'in',
                        'qty' => $qtyReceivedBase,
                        'qty_input' => $qtyReceivedUnit,
                        'conversion_qty' => $receivedUnit->conversion_qty,
                        'source_type' => 'transfer',
                        'source_subtype' => 'receive',
                        'source_id' => $transfer->id,
                        'source_code' => $transfer->code,
                        'note' => $row->note,
                        'occurred_at' => now(),
                        'created_by' => auth()->id(),
                        'idempotency_key' => StockService::idempotencyKey(['transfer', 'receive', $transfer->id, $row->item_id]),
                    ]);
                }
                $row->update([
                    'qty_received_base' => $qtyReceivedBase,
                    'received_unit_id' => $receivedUnit->id,
                    'qty_received_unit' => $qtyReceivedUnit,
                    'qty_discrepancy_base' => $qtyDiscrepancyBase,
                    'discrepancy_note' => $received['discrepancy_note'] ?? null,
                ]);
            }
            $transfer->update([
                'status' => $hasDiscrepancy ? 'received_with_discrepancy' : 'received',
                'received_at' => now(),
                'received_by' => auth()->id(),
                'discrepancy_note' => $validated['discrepancy_note'] ?? null,
            ]);
        });

        return response()->json(['message' => 'Transfer sudah diterima']);
    }
}
