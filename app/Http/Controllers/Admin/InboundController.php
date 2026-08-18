<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Resi;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Exports\InboundReceiptsTemplateExport;
use App\Exports\InboundReturnsTemplateExport;
use App\Imports\InboundReceiptsImport;
use App\Imports\InboundReturnsImport;
use App\Support\DamagedStockService;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class InboundController extends Controller
{
    public function receipts()
    {
        return $this->index('receipt', 'Inbound - Penerimaan Barang', 'receipts');
    }

    public function returns()
    {
        return $this->index('return', 'Inbound - Retur', 'returns');
    }

    public function returnsCreate()
    {
        return $this->returnForm('Tambah Retur Inbound');
    }

    public function returnsEdit(int $id)
    {
        $transaction = InboundTransaction::with(['items.item'])
            ->where('type', 'return')
            ->findOrFail($id);

        return $this->returnForm('Edit Retur Inbound', $transaction);
    }

    public function receiptsData(Request $request)
    {
        return $this->data($request, 'receipt');
    }

    public function returnsData(Request $request)
    {
        return $this->data($request, 'return');
    }

    public function receiptsStore(Request $request)
    {
        return $this->store($request, 'receipt');
    }

    public function returnsStore(Request $request)
    {
        return $this->store($request, 'return');
    }

    public function receiptsShow(int $id)
    {
        return $this->show('receipt', $id);
    }

    public function returnsShow(int $id)
    {
        return $this->show('return', $id);
    }

    public function receiptsDetail(int $id)
    {
        return $this->detail('receipt', 'Inbound - Penerimaan Barang', 'receipts', $id);
    }

    public function returnsDetail(int $id)
    {
        return $this->detail('return', 'Inbound - Retur', 'returns', $id);
    }

    public function receiptsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'receipt', $id);
    }

    public function returnsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'return', $id);
    }

    public function receiptsDestroy(int $id)
    {
        return $this->destroy('receipt', $id);
    }

    public function returnsDestroy(int $id)
    {
        return $this->destroy('return', $id);
    }

    public function receiptsApprove(int $id)
    {
        return $this->approve('receipt', $id);
    }

    public function returnsApprove(int $id)
    {
        return $this->approve('return', $id);
    }

    public function returnsFinalize(int $id)
    {
        return $this->finalizeReturn($id);
    }

    public function returnsLookupResi(Request $request)
    {
        $validated = $request->validate([
            'no_resi' => ['required', 'string', 'max:100'],
        ]);

        $noResi = trim((string) $validated['no_resi']);
        $resi = Resi::query()
            ->with('details')
            ->where('no_resi', $noResi)
            ->orWhere('id_pesanan', $noResi)
            ->first();

        if (!$resi) {
            return response()->json([
                'found' => false,
                'message' => 'Resi tidak ditemukan pada database import. Silakan input SKU dan qty secara manual.',
                'items' => [],
            ], 404);
        }

        $details = $resi->details
            ->groupBy(fn ($row) => trim((string) $row->sku))
            ->map(fn ($rows, $sku) => [
                'sku' => $sku,
                'qty' => (int) $rows->sum('qty'),
            ])
            ->values();

        $itemMap = Item::query()
            ->whereIn('sku', $details->pluck('sku')->filter()->all())
            ->get(['id', 'sku', 'name'])
            ->keyBy('sku');

        return response()->json([
            'found' => true,
            'resi' => [
                'id' => $resi->id,
                'id_pesanan' => $resi->id_pesanan,
                'no_resi' => $resi->no_resi,
                'status' => $resi->status ?? 'active',
            ],
            'items' => $details->map(function ($row) use ($itemMap) {
                $item = $itemMap->get($row['sku']);
                return [
                    'sku' => $row['sku'],
                    'qty' => $row['qty'],
                    'item_id' => $item?->id,
                    'item_name' => $item?->name,
                    'found_item' => (bool) $item,
                ];
            })->values(),
        ]);
    }

    public function returnsTemplate()
    {
        return Excel::download(
            new InboundReturnsTemplateExport(),
            'template-import-retur-inbound.xlsx'
        );
    }

    public function receiptsTemplate()
    {
        return Excel::download(
            new InboundReceiptsTemplateExport(),
            'template-import-penerimaan-barang.xlsx'
        );
    }

    public function returnsImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new InboundReturnsImport();
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
                $tx = InboundTransaction::create([
                    'warehouse_id' => Warehouse::smallId(),
                    'code' => $this->generateCode('INB-RET'),
                    'type' => 'return',
                    'ref_no' => $group['ref_no'] ?? null,
                    'note' => $group['note'] ?? null,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ]);
                $createdTx++;

                foreach ($group['items'] as $row) {
                    InboundItem::create([
                        'inbound_transaction_id' => $tx->id,
                        'item_id' => $row['item_id'],
                        'qty_input' => $row['qty_received'] ?? $row['qty'],
                        'conversion_qty' => 1,
                        'qty' => $row['qty_received'] ?? $row['qty'],
                        'qty_received' => $row['qty_received'] ?? $row['qty'],
                        'qty_good' => $row['qty_good'] ?? 0,
                        'qty_damaged' => $row['qty_damaged'] ?? ($row['qty_received'] ?? $row['qty']),
                        'qty_missing' => $row['qty_missing'] ?? 0,
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;

                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import retur inbound berhasil masuk Area Retur Gudang Kecil',
                'transactions' => $createdTx,
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import retur inbound',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function receiptsImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);
        $warehouseId = $request->integer('warehouse_id');

        $import = new InboundReceiptsImport();
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
                $tx = InboundTransaction::create([
                    'warehouse_id' => $warehouseId,
                    'code' => $this->generateCode('INB-RCV'),
                    'type' => 'receipt',
                    'ref_no' => $group['ref_no'] ?? null,
                    'note' => $group['note'] ?? null,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'pending',
                ]);
                $createdTx++;

                foreach ($group['items'] as $row) {
                    $unit = $this->defaultUnitForWarehouse($warehouseId, (int) $row['item_id']);
                    $qtyInput = (int) $row['qty'];
                    $conversionQty = (int) $unit->conversion_qty;
                    $qty = $qtyInput * $conversionQty;
                    InboundItem::create([
                        'inbound_transaction_id' => $tx->id,
                        'item_id' => $row['item_id'],
                        'unit_id' => $unit->id,
                        'qty_input' => $qtyInput,
                        'conversion_qty' => $conversionQty,
                        'qty' => $qty,
                        'qty_received' => $qty,
                        'qty_good' => $qty,
                        'qty_damaged' => 0,
                        'qty_missing' => 0,
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;

                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import penerimaan barang berhasil',
                'transactions' => $createdTx,
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import penerimaan barang',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function index(string $type, string $pageTitle, string $routeBase)
    {
        $items = Item::with(['units' => fn ($q) => $q->orderByDesc('is_base')->orderBy('conversion_qty')])
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type', 'is_default']);
        $baseOptions = $this->typeOptions();
        $typeOptions = ['all' => 'Semua'] + $baseOptions;
        $routeMap = [
            'receipt' => [
                'store' => route('admin.inbound.receipts.store'),
                'show' => route('admin.inbound.receipts.show', ':id'),
                'update' => route('admin.inbound.receipts.update', ':id'),
                'delete' => route('admin.inbound.receipts.destroy', ':id'),
                'detail' => route('admin.inbound.receipts.detail', ':id'),
                'approve' => route('admin.inbound.receipts.approve', ':id'),
            ],
            'return' => [
                'create' => route('admin.inbound.returns.create'),
                'store' => route('admin.inbound.returns.store'),
                'show' => route('admin.inbound.returns.show', ':id'),
                'edit' => route('admin.inbound.returns.edit', ':id'),
                'update' => route('admin.inbound.returns.update', ':id'),
                'delete' => route('admin.inbound.returns.destroy', ':id'),
                'detail' => route('admin.inbound.returns.detail', ':id'),
                'approve' => route('admin.inbound.returns.approve', ':id'),
                'finalize' => route('admin.inbound.returns.finalize', ':id'),
                'lookupResi' => route('admin.inbound.returns.lookup-resi'),
            ],
        ];

        return view('admin.stock-flow.index', [
            'pageTitle' => $pageTitle,
            'dataUrl' => route("admin.inbound.{$routeBase}.data"),
            'storeUrl' => route("admin.inbound.{$routeBase}.store"),
            'showUrlTpl' => route("admin.inbound.{$routeBase}.show", ':id'),
            'updateUrlTpl' => route("admin.inbound.{$routeBase}.update", ':id'),
            'deleteUrlTpl' => route("admin.inbound.{$routeBase}.destroy", ':id'),
            'detailUrlTpl' => route("admin.inbound.{$routeBase}.detail", ':id'),
            'items' => $items,
            'warehouses' => $warehouses,
            'smallWarehouse' => Warehouse::findOrFail(Warehouse::smallId()),
            'locksToSmallWarehouse' => $type === 'return',
            'typeOptions' => $typeOptions,
            'typeDefault' => $type,
            'routeMap' => $routeMap,
            'importUrl' => match ($type) {
                'receipt' => route('admin.inbound.receipts.import'),
                'return' => route('admin.inbound.returns.import'),
                default => null,
            },
            'importTitle' => match ($type) {
                'receipt' => 'Import Penerimaan Barang',
                'return' => 'Import Retur Inbound',
                default => null,
            },
            'templateUrl' => match ($type) {
                'receipt' => route('admin.inbound.receipts.template'),
                'return' => route('admin.inbound.returns.template'),
                default => null,
            },
        ]);
    }

    private function returnForm(string $pageTitle, ?InboundTransaction $transaction = null)
    {
        $items = Item::orderBy('name')->get(['id', 'sku', 'name']);

        return view('admin.stock-flow.inbound-return-form', [
            'pageTitle' => $pageTitle,
            'transaction' => $transaction,
            'items' => $items,
            'smallWarehouse' => Warehouse::findOrFail(Warehouse::smallId()),
            'lookupUrl' => route('admin.inbound.returns.lookup-resi'),
            'storeUrl' => route('admin.inbound.returns.store'),
            'updateUrl' => $transaction ? route('admin.inbound.returns.update', $transaction->id) : null,
            'indexUrl' => route('admin.inbound.returns.index'),
        ]);
    }

    private function data(Request $request, string $type)
    {
        $allowed = array_keys($this->typeOptions());
        $filterType = $request->input('type');
        $baseType = null;
        if ($filterType === 'all') {
            $baseType = null;
        } elseif (in_array($filterType, $allowed, true)) {
            $baseType = $filterType;
        } else {
            $baseType = $type;
        }

        $query = InboundTransaction::query()
            ->with(['items.item', 'items.unit', 'creator', 'warehouse'])
            ->select([
                'inbound_transactions.id',
                'inbound_transactions.code',
                'inbound_transactions.transacted_at',
                'inbound_transactions.type',
                'inbound_transactions.ref_no',
                'inbound_transactions.note',
                'inbound_transactions.status',
                'inbound_transactions.created_by',
                'inbound_transactions.warehouse_id',
            ])
            ->orderBy('inbound_transactions.transacted_at', 'desc');
        if ($baseType) {
            $query->where('inbound_transactions.type', $baseType);
        }

        $status = $request->input('status');
        if (in_array($type, ['receipt', 'return'], true) && in_array($status, ['pending', 'approved', 'finalized'], true)) {
            $query->where('inbound_transactions.status', $status);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('inbound_transactions.code', 'like', "%{$search}%")
                    ->orWhere('inbound_transactions.ref_no', 'like', "%{$search}%")
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($query, $request);

        $recordsTotalQuery = InboundTransaction::query();
        if ($baseType) {
            $recordsTotalQuery->where('type', $baseType);
        }
        $recordsTotal = $recordsTotalQuery->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $ts = $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '';
            $items = $row->items ?? collect();
            $labels = $items->map(function ($it) use ($row) {
                $sku = trim($it->item?->sku ?? '');
                if ($sku === '') {
                    return '';
                }
                if (($row->type ?? '') === 'return') {
                    return sprintf(
                        '%s (resi %d, terima %d, bagus %d, rusak %d, hilang %d)',
                        $sku,
                        (int) ($it->qty ?? 0),
                        (int) ($it->qty_received ?? $it->qty ?? 0),
                        (int) ($it->qty_good ?? 0),
                        (int) ($it->qty_damaged ?? 0),
                        (int) ($it->qty_missing ?? 0)
                    );
                }

                $qtyInput = (int) ($it->qty_input ?: $it->qty);
                $qtyBase = (int) ($it->qty ?? 0);
                $unitName = $it->unit?->name ?: 'PCS/SET';

                return (int) ($it->conversion_qty ?: 1) > 1
                    ? sprintf('%s (%d %s = %d PCS/SET)', $sku, $qtyInput, $unitName, $qtyBase)
                    : sprintf('%s (%d %s)', $sku, $qtyInput, $unitName);
            })->filter()->values();
            $itemLabel = $labels->implode(', ');
            $totalQty = (int) $items->sum(fn ($it) => (int) ($it->qty_received ?? $it->qty ?? 0));
            return [
                'id' => $row->id,
                'code' => $row->code,
                'ref_no' => $row->ref_no ?? '',
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'warehouse' => $row->warehouse?->name ?? '-',
                'item' => $itemLabel ?: '-',
                'item_details' => $items->map(fn ($it) => [
                    'sku' => $it->item?->sku ?? '-',
                    'name' => $it->item?->name ?? '',
                    'qty' => (int) ($it->qty ?? 0),
                    'qty_received' => (int) ($it->qty_received ?? $it->qty ?? 0),
                    'qty_good' => (int) ($it->qty_good ?? 0),
                    'qty_damaged' => (int) ($it->qty_damaged ?? 0),
                    'qty_missing' => (int) ($it->qty_missing ?? 0),
                    'qty_input' => (int) ($it->qty_input ?: $it->qty),
                    'unit' => $it->unit?->name ?: 'PCS/SET',
                    'qty_base' => (int) ($it->qty ?? 0),
                    'conversion_qty' => (int) ($it->conversion_qty ?: 1),
                ])->values(),
                'qty' => $totalQty,
                'qty_details' => $items->map(fn ($it) => [
                    'sku' => $it->item?->sku ?? '-',
                    'qty_input' => (int) ($it->qty_input ?: $it->qty),
                    'unit' => $it->unit?->name ?: 'PCS/SET',
                    'qty_base' => (int) ($it->qty ?? 0),
                    'conversion_qty' => (int) ($it->conversion_qty ?: 1),
                ])->values(),
                'note' => $row->note ?? '',
                'type' => $row->type,
                'status' => $row->status ?? 'pending',
                'return_warehouse_qty' => ($row->type ?? '') === 'return' && ($row->status ?? 'pending') === 'approved'
                    ? (int) $items->sum(fn ($it) => (int) ($it->qty_received ?? $it->qty ?? 0))
                    : 0,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function show(string $type, int $id)
    {
        $tx = InboundTransaction::with('items')
            ->where('type', $type)
            ->findOrFail($id);

        return response()->json([
            'id' => $tx->id,
            'code' => $tx->code,
            'ref_no' => $tx->ref_no,
            'note' => $tx->note,
            'status' => $tx->status ?? 'pending',
            'warehouse_id' => $tx->warehouse_id ?: Warehouse::defaultId(),
            'finalized_at' => $tx->finalized_at?->format('Y-m-d H:i'),
            'transacted_at' => $tx->transacted_at?->format('Y-m-d\TH:i'),
            'items' => $tx->items->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'unit_id' => $item->unit_id,
                    'qty' => $item->qty_input ?: $item->qty,
                    'qty_received' => $item->qty_received ?: $item->qty,
                    'qty_good' => $item->qty_good ?? 0,
                    'qty_damaged' => $item->qty_damaged ?? 0,
                    'qty_missing' => $item->qty_missing ?? 0,
                    'note' => $item->note ?? '',
                ];
            })->values(),
        ]);
    }

    private function detail(string $type, string $pageTitle, string $routeBase, int $id)
    {
        $tx = InboundTransaction::with(['items.item', 'creator', 'approver', 'finalizer'])
            ->where('type', $type)
            ->findOrFail($id);

        $totalQty = $tx->items->sum(fn ($item) => (int) ($item->qty_received ?? $item->qty ?? 0));

        return view('admin.stock-flow.detail', [
            'pageTitle' => $pageTitle,
            'transaction' => $tx,
            'totalQty' => $totalQty,
            'backUrl' => route("admin.inbound.{$routeBase}.index"),
        ]);
    }

    private function store(Request $request, string $type)
    {
        $validated = $this->validatePayload($request, $type);

        $prefix = match ($type) {
            'receipt' => 'INB-RCV',
            'return' => 'INB-RET',
            default => 'INB-RCV',
        };

        $code = $this->generateCode($prefix);
        $transactedAt = $validated['transacted_at'] ?? now();
        $status = $type === 'return' ? 'approved' : 'pending';
        $approvedAt = $type === 'return' ? now() : null;
        $approvedBy = $type === 'return' ? auth()->id() : null;

        DB::beginTransaction();
        try {
            $tx = InboundTransaction::create([
                'warehouse_id' => $validated['warehouse_id'],
                'code' => $code,
                'type' => $type,
                'ref_no' => $validated['ref_no'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => $status,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ]);

            foreach ($validated['items'] as $row) {
                InboundItem::create([
                    'inbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'unit_id' => $row['unit_id'] ?? null,
                    'qty_input' => $row['qty_input'] ?? $row['qty'],
                    'conversion_qty' => $row['conversion_qty'] ?? 1,
                    'qty' => $row['qty'],
                    'qty_received' => $row['qty_received'] ?? $row['qty'],
                    'qty_good' => $row['qty_good'] ?? $row['qty'],
                    'qty_damaged' => $row['qty_damaged'] ?? 0,
                    'qty_missing' => $row['qty_missing'] ?? 0,
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
                'message' => 'Gagal menyimpan inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => $type === 'return'
                ? 'Retur berhasil masuk Area Retur Gudang Kecil. Lakukan finalisasi untuk distribusi stok.'
                : 'Inbound berhasil disimpan',
        ]);
    }

    private function update(Request $request, string $type, int $id)
    {
        $validated = $this->validatePayload($request, $type);

        DB::beginTransaction();
        try {
            $tx = InboundTransaction::where('type', $type)->findOrFail($id);
            $status = $tx->status ?? 'pending';
            $isEditableApprovedReturn = $type === 'return' && $status === 'approved';
            if ($status === 'finalized' || ($status === 'approved' && !$isEditableApprovedReturn)) {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah diproses dan tidak bisa diubah'], 422);
            }

            StockService::rollbackBySource('inbound', $tx->id);
            StockMutation::where('source_type', 'inbound')->where('source_id', $tx->id)->delete();
            InboundItem::where('inbound_transaction_id', $tx->id)->delete();

            $tx->update([
                'warehouse_id' => $validated['warehouse_id'],
                'ref_no' => $validated['ref_no'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? $tx->transacted_at,
            ]);

            foreach ($validated['items'] as $row) {
                InboundItem::create([
                    'inbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'unit_id' => $row['unit_id'] ?? null,
                    'qty_input' => $row['qty_input'] ?? $row['qty'],
                    'conversion_qty' => $row['conversion_qty'] ?? 1,
                    'qty' => $row['qty'],
                    'qty_received' => $row['qty_received'] ?? $row['qty'],
                    'qty_good' => $row['qty_good'] ?? $row['qty'],
                    'qty_damaged' => $row['qty_damaged'] ?? 0,
                    'qty_missing' => $row['qty_missing'] ?? 0,
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
                'message' => 'Gagal memperbarui inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Inbound berhasil diperbarui',
        ]);
    }

    private function destroy(string $type, int $id)
    {
        DB::beginTransaction();
        try {
            $tx = InboundTransaction::where('type', $type)->findOrFail($id);
            $status = $tx->status ?? 'pending';
            $isDeletableApprovedReturn = $type === 'return' && $status === 'approved';
            if ($status === 'finalized' || ($status === 'approved' && ! $isDeletableApprovedReturn)) {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah diproses dan tidak bisa dihapus'], 422);
            }

            StockService::rollbackBySource('inbound', $tx->id);
            StockMutation::where('source_type', 'inbound')->where('source_id', $tx->id)->delete();
            $tx->delete();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return response()->json([
                'message' => $msg,
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Inbound berhasil dihapus',
        ]);
    }

    private function approve(string $type, int $id)
    {
        DB::beginTransaction();
        try {
            $tx = InboundTransaction::where('type', $type)
                ->lockForUpdate()
                ->findOrFail($id);

            if (($tx->status ?? 'pending') === 'approved') {
                DB::commit();
                return response()->json(['message' => 'Data sudah disetujui']);
            }
            if (($tx->status ?? 'pending') === 'finalized') {
                DB::commit();
                return response()->json(['message' => 'Data sudah finalisasi']);
            }

            if ($type !== 'return') {
                $this->postStockMovements($tx, $type);
            } else {
                $this->assertInboundReturnBalanced($tx);
            }

            $tx->status = 'approved';
            $tx->approved_at = now();
            $tx->approved_by = auth()->id();
            $tx->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyetujui inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => $type === 'return'
                ? 'Retur berhasil masuk Gudang Retur. Lakukan finalisasi untuk distribusi stok.'
                : 'Inbound berhasil disetujui',
        ]);
    }

    private function finalizeReturn(int $id)
    {
        DB::beginTransaction();
        try {
            $tx = InboundTransaction::where('type', 'return')
                ->lockForUpdate()
                ->findOrFail($id);

            if (($tx->status ?? 'pending') === 'finalized') {
                DB::commit();
                return response()->json(['message' => 'Retur sudah finalisasi']);
            }

            if (($tx->status ?? 'pending') !== 'approved') {
                DB::rollBack();
                return response()->json([
                    'message' => 'Retur harus disetujui dan masuk Gudang Retur sebelum finalisasi.',
                ], 422);
            }

            $this->assertInboundReturnBalanced($tx);
            $this->postInboundReturnToDamagedStock($tx);

            $tx->status = 'finalized';
            $tx->finalized_at = now();
            $tx->finalized_by = auth()->id();
            $tx->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal finalisasi retur',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Retur berhasil difinalisasi']);
    }

    private function postStockMovements(InboundTransaction $tx, string $type): void
    {
        if ($type === 'return') {
            $this->postInboundReturnToDamagedStock($tx);
            return;
        }

        $tx->loadMissing('items');
        foreach ($tx->items as $row) {
            StockService::mutate([
                'warehouse_id' => $tx->warehouse_id ?: Warehouse::defaultId(),
                'item_id' => $row->item_id,
                'unit_id' => $row->unit_id,
                'direction' => 'in',
                'qty' => (int) ($row->qty_good ?: $row->qty),
                'qty_input' => (int) ($row->qty_input ?: ($row->qty_good ?: $row->qty)),
                'conversion_qty' => (int) ($row->conversion_qty ?: 1),
                'source_type' => 'inbound',
                'source_subtype' => $type,
                'source_id' => $tx->id,
                'source_code' => $tx->code,
                'note' => $row->note ?? null,
                'occurred_at' => $tx->transacted_at ?? now(),
                'created_by' => auth()->id(),
                'idempotency_key' => StockService::idempotencyKey(['stock', 'inbound', $type, $tx->id, $row->item_id]),
            ]);
        }
    }

    private function assertInboundReturnBalanced(InboundTransaction $tx): void
    {
        $tx->loadMissing('items.item');
        foreach ($tx->items as $row) {
            $received = (int) ($row->qty_received ?? $row->qty ?? 0);
            $resiQty = (int) ($row->qty_input ?: $row->qty ?: 0);
            $good = (int) ($row->qty_good ?? 0);
            $damaged = (int) ($row->qty_damaged ?? 0);
            $missing = (int) ($row->qty_missing ?? 0);
            $expectedMissing = max(0, $resiQty - $received);
            if ($received <= 0 || $received > $resiQty || $good + $damaged !== $received || $missing !== $expectedMissing) {
                $sku = $row->item?->sku ?? 'item '.$row->item_id;
                throw ValidationException::withMessages([
                    'items' => "Qty bagus + qty rusak harus sama dengan qty diterima, dan qty hilang harus selisih qty resi untuk {$sku}.",
                ]);
            }
        }
    }

    private function postInboundReturnToDamagedStock(InboundTransaction $tx): void
    {
        $tx->loadMissing('items');

        $hasDamagedItems = $tx->items->contains(fn ($row) => (int) ($row->qty_damaged ?? 0) > 0);
        $damage = null;

        if ($hasDamagedItems) {
            $damage = DamagedGood::where('source_type', 'inbound_return')
                ->where('source_ref', $tx->code)
                ->lockForUpdate()
                ->first();

            if (!$damage) {
                $damage = DamagedGood::create([
                    'warehouse_id' => $tx->warehouse_id ?: Warehouse::defaultId(),
                    'code' => $this->generateCode('DMG-RET'),
                    'source_type' => 'inbound_return',
                    'source_ref' => $tx->code,
                    'transacted_at' => $tx->transacted_at ?? now(),
                    'note' => 'Otomatis dari inbound retur '.$tx->code,
                    'created_by' => auth()->id(),
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ]);

                foreach ($tx->items as $row) {
                    $damagedQty = (int) ($row->qty_damaged ?? 0);
                    if ($damagedQty <= 0) {
                        continue;
                    }

                    DamagedGoodItem::create([
                        'damaged_good_id' => $damage->id,
                        'item_id' => $row->item_id,
                        'qty' => $damagedQty,
                        'note' => $row->note ?? null,
                    ]);
                }
            } elseif (($damage->status ?? 'pending') !== 'approved') {
                $damage->status = 'approved';
                $damage->approved_at = now();
                $damage->approved_by = auth()->id();
                $damage->save();
            }
        }

        foreach ($tx->items as $row) {
            $goodQty = (int) ($row->qty_good ?? 0);
            if ($goodQty > 0) {
                StockService::mutate([
                    'warehouse_id' => $tx->warehouse_id ?: Warehouse::defaultId(),
                    'item_id' => $row->item_id,
                    'direction' => 'in',
                    'qty' => $goodQty,
                    'source_type' => 'inbound',
                    'source_subtype' => 'return_good',
                    'source_id' => $tx->id,
                    'source_code' => $tx->code,
                    'note' => $row->note ?? 'Inbound retur barang bagus',
                    'occurred_at' => $tx->transacted_at ?? now(),
                    'created_by' => auth()->id(),
                    'idempotency_key' => StockService::idempotencyKey(['stock', 'inbound-return-good', $tx->id, $row->item_id]),
                ]);
            }

            $damagedQty = (int) ($row->qty_damaged ?? 0);
            if ($damagedQty > 0) {
                DamagedStockService::mutate([
                    'warehouse_id' => $tx->warehouse_id ?: Warehouse::defaultId(),
                    'item_id' => $row->item_id,
                    'direction' => 'in',
                    'qty' => $damagedQty,
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
        }
    }

    private function validatePayload(Request $request, ?string $type = null): array
    {
        $isReturn = $type === 'return';
        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:item_units,id'],
            'items.*.note' => ['nullable', 'string'],
            'ref_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ];
        if ($isReturn) {
            $rules['items.*.qty'] = ['required', 'integer', 'min:1'];
            $rules['items.*.qty_received'] = ['required', 'integer', 'min:1'];
            $rules['items.*.qty_good'] = ['required', 'integer', 'min:0'];
            $rules['items.*.qty_damaged'] = ['required', 'integer', 'min:0'];
            $rules['items.*.qty_missing'] = ['required', 'integer', 'min:0'];
        } else {
            $rules['items.*.qty'] = ['required', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules);
        $validated['warehouse_id'] = $isReturn
            ? Warehouse::smallId()
            : (int) ($validated['warehouse_id'] ?? Warehouse::defaultId());

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row) use ($isReturn) {
                if ($isReturn) {
                    $received = (int) ($row['qty_received'] ?? 0);
                    $resiQty = (int) ($row['qty'] ?? $received);
                    $good = (int) ($row['qty_good'] ?? 0);
                    $damaged = (int) ($row['qty_damaged'] ?? 0);
                    $missing = max(0, $resiQty - $received);
                    return [
                        'item_id' => (int) $row['item_id'],
                        'unit_id' => null,
                        'qty_input' => $resiQty,
                        'conversion_qty' => 1,
                        'qty' => $resiQty,
                        'qty_received' => $received,
                        'qty_good' => $good,
                        'qty_damaged' => $damaged,
                        'qty_missing' => $missing,
                        'note' => $row['note'] ?? null,
                    ];
                }

                $qtyInput = (int) ($row['qty'] ?? 0);
                $unitId = (int) ($row['unit_id'] ?? 0);
                $conversionQty = 1;
                if ($unitId > 0) {
                    $unit = ItemUnit::whereKey($unitId)
                        ->where('item_id', (int) $row['item_id'])
                        ->first();
                    if (!$unit) {
                        throw ValidationException::withMessages([
                            'items' => 'Satuan item inbound tidak valid.',
                        ]);
                    }
                    $conversionQty = (int) $unit->conversion_qty;
                }
                $qty = $qtyInput * $conversionQty;
                return [
                    'item_id' => (int) $row['item_id'],
                    'unit_id' => $unitId ?: null,
                    'qty_input' => $qtyInput,
                    'conversion_qty' => $conversionQty,
                    'qty' => $qty,
                    'qty_received' => $qty,
                    'qty_good' => $qty,
                    'qty_damaged' => 0,
                    'qty_missing' => 0,
                    'note' => $row['note'] ?? null,
                ];
            })->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal 1 item diperlukan',
            ]);
        }

        if ($isReturn) {
            foreach ($items as $idx => $row) {
                if ((int) $row['qty_received'] <= 0) {
                    throw ValidationException::withMessages(["items.{$idx}.qty_received" => 'Qty diterima wajib lebih dari 0']);
                }
                if ((int) $row['qty_received'] > (int) $row['qty']) {
                    throw ValidationException::withMessages(["items.{$idx}.qty_received" => 'Qty diterima tidak boleh lebih besar dari qty resi']);
                }
                if ((int) $row['qty_good'] + (int) $row['qty_damaged'] !== (int) $row['qty_received']) {
                    throw ValidationException::withMessages(["items.{$idx}.qty_received" => 'Qty bagus + qty rusak harus sama dengan qty diterima']);
                }
                StockService::assertWarehouseQuantity(
                    $validated['warehouse_id'],
                    (int) $row['item_id'],
                    (int) $row['qty_received']
                );
                if ((int) $row['qty_good'] > 0) {
                    StockService::assertWarehouseQuantity(
                        $validated['warehouse_id'],
                        (int) $row['item_id'],
                        (int) $row['qty_good']
                    );
                }
                if ((int) $row['qty_damaged'] > 0) {
                    StockService::assertWarehouseQuantity(
                        $validated['warehouse_id'],
                        (int) $row['item_id'],
                        (int) $row['qty_damaged']
                    );
                }
            }
        } else {
            $warehouseType = Warehouse::whereKey($validated['warehouse_id'])->value('type');
            foreach ($items as $idx => $row) {
                // Jangan biarkan qty koli tanpa satuan kemasan diproses sebagai PCS.
                // Tanpa unit_id, qty input tidak dapat dikonversi ke satuan dasar.
                if ($warehouseType === Warehouse::TYPE_BULK && !$row['unit_id']) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.unit_id" => 'Satuan koli belum dikonfigurasi untuk item ini. Atur satuan kemasan di Master Item terlebih dahulu.',
                    ]);
                }

                if (
                    $warehouseType === Warehouse::TYPE_FULFILLMENT
                    && $row['unit_id']
                    && !ItemUnit::whereKey($row['unit_id'])->where('is_base', true)->exists()
                ) {
                    throw ValidationException::withMessages([
                        'items' => 'Penerimaan Gudang Kecil wajib menggunakan satuan PCS/SET.',
                    ]);
                }
                StockService::assertWarehouseQuantity(
                    $validated['warehouse_id'],
                    (int) $row['item_id'],
                    (int) $row['qty'],
                    $row['unit_id']
                );
            }
        }

        $duplicates = $items->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item tidak boleh duplikat pada inbound',
            ]);
        }

        $normalized = $items->groupBy('item_id')->map(function ($rows, $itemId) {
            $first = $rows->first();
            $qty = $rows->sum('qty');
            $qtyReceived = $rows->sum('qty_received');
            $qtyGood = $rows->sum('qty_good');
            $qtyDamaged = $rows->sum('qty_damaged');
            $qtyMissing = $rows->sum('qty_missing');
            $note = $rows->pluck('note')->first(fn ($n) => $n !== null && $n !== '') ?? null;
            return [
                'item_id' => (int) $itemId,
                'unit_id' => $first['unit_id'] ?? null,
                'qty_input' => $rows->sum('qty_input'),
                'conversion_qty' => $first['conversion_qty'] ?? 1,
                'qty' => $qty,
                'qty_received' => $qtyReceived,
                'qty_good' => $qtyGood,
                'qty_damaged' => $qtyDamaged,
                'qty_missing' => $qtyMissing,
                'note' => $note,
            ];
        })->values()->all();

        $validated['items'] = $normalized;
        if (!empty($validated['transacted_at'])) {
            $validated['transacted_at'] = Carbon::parse($validated['transacted_at']);
        } else {
            $validated['transacted_at'] = null;
        }

        return $validated;
    }

    private function typeOptions(): array
    {
        return [
            'receipt' => 'Penerimaan Barang',
            'return' => 'Retur',
            'opening' => 'Saldo Awal',
        ];
    }

    private function defaultUnitForWarehouse(int $warehouseId, int $itemId): ItemUnit
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $unit = ItemUnit::where('item_id', $itemId)
            ->where('is_base', $warehouse->type !== Warehouse::TYPE_BULK)
            ->orderBy('conversion_qty')
            ->first();

        if (!$unit) {
            throw ValidationException::withMessages([
                'file' => $warehouse->type === Warehouse::TYPE_BULK
                    ? 'Item import belum memiliki satuan koli.'
                    : 'Item import belum memiliki satuan dasar PCS/SET.',
            ]);
        }

        return $unit;
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('inbound_transactions.transacted_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('inbound_transactions.transacted_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
