<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemBundle;
use App\Models\DamagedItemStock;
use App\Models\OutboundItem;
use App\Models\OutboundManualScanLog;
use App\Models\OutboundTransaction;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\ItemStock;
use App\Models\StockMutation;
use App\Models\SuratJalan;
use App\Models\Warehouse;
use App\Exports\OutboundManualsTemplateExport;
use App\Exports\OutboundReturnsTemplateExport;
use App\Imports\OutboundReturnsImport;
use App\Models\DamagedStockMutation;
use App\Support\BundleService;
use App\Support\DamagedStockService;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class OutboundController extends Controller
{
    public function pickers()
    {
        return $this->index('picker', 'Outbound - QC Scan', 'pickers');
    }

    public function manuals()
    {
        return $this->index('manual', 'Outbound - Manual', 'manuals');
    }

    public function returns()
    {
        return $this->index('return', 'Outbound - Retur', 'returns');
    }

    public function returnsCreate()
    {
        return $this->returnForm('Tambah Retur Customer');
    }

    public function returnsEdit(int $id)
    {
        $transaction = OutboundTransaction::with(['items.item'])
            ->where('type', 'return')
            ->findOrFail($id);

        return $this->returnForm('Edit Retur Customer', $transaction);
    }

    public function pickersData(Request $request)
    {
        return $this->data($request, 'picker');
    }

    public function manualsData(Request $request)
    {
        return $this->data($request, 'manual');
    }

    public function returnsData(Request $request)
    {
        return $this->data($request, 'return');
    }

    public function pickersStore(Request $request)
    {
        return $this->store($request, 'picker');
    }

    public function manualsStore(Request $request)
    {
        return $this->store($request, 'manual');
    }

    public function returnsStore(Request $request)
    {
        return $this->store($request, 'return');
    }

    public function pickersShow(int $id)
    {
        return $this->show('picker', $id);
    }

    public function manualsShow(int $id)
    {
        return $this->show('manual', $id);
    }

    public function returnsShow(int $id)
    {
        return $this->show('return', $id);
    }

    public function pickersDetail(int $id)
    {
        return $this->detail('picker', 'Outbound - QC Scan', 'pickers', $id);
    }

    public function manualsDetail(int $id)
    {
        return $this->detail('manual', 'Outbound - Manual', 'manuals', $id);
    }

    public function returnsDetail(int $id)
    {
        return $this->detail('return', 'Outbound - Retur', 'returns', $id);
    }

    public function pickersUpdate(Request $request, int $id)
    {
        return $this->update($request, 'picker', $id);
    }

    public function manualsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'manual', $id);
    }

    public function returnsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'return', $id);
    }

    public function pickersDestroy(int $id)
    {
        return $this->destroy('picker', $id);
    }

    public function manualsDestroy(int $id)
    {
        return $this->destroy('manual', $id);
    }

    public function returnsDestroy(int $id)
    {
        return $this->destroy('return', $id);
    }

    public function pickersApprove(int $id)
    {
        return $this->approve('picker', $id);
    }

    public function manualsApprove(int $id)
    {
        return $this->approve('manual', $id);
    }

    public function manualsGenerateSuratJalan(int $id)
    {
        $tx = OutboundTransaction::with(['items.item', 'creator', 'suratJalan'])
            ->where('type', 'manual')
            ->findOrFail($id);

        if (($tx->status ?? 'pending') !== 'approved') {
            return response()->json(['message' => 'Outbound harus disetujui terlebih dahulu sebelum membuat surat jalan'], 422);
        }

        if ($tx->suratJalan) {
            return response()->json([
                'message' => 'Surat jalan sudah dibuat',
                'code' => $tx->suratJalan->code,
                'url' => route('admin.outbound.manuals.surat-jalan', $id),
            ]);
        }

        $code = $this->generateSuratJalanCode();
        $sj = SuratJalan::create([
            'code' => $code,
            'outbound_transaction_id' => $tx->id,
            'note' => null,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Surat jalan berhasil dibuat',
            'code' => $sj->code,
            'url' => route('admin.outbound.manuals.surat-jalan', $id),
        ]);
    }

    public function manualsViewSuratJalan(int $id)
    {
        $tx = OutboundTransaction::with(['items.item', 'creator', 'approver', 'suratJalan.creator'])
            ->where('type', 'manual')
            ->findOrFail($id);

        if (!$tx->suratJalan) {
            abort(404, 'Surat jalan belum dibuat');
        }

        $totalQty = $tx->items->sum('qty');

        return view('admin.stock-flow.surat-jalan', [
            'transaction' => $tx,
            'suratJalan' => $tx->suratJalan,
            'totalQty' => $totalQty,
            'backUrl' => route('admin.outbound.manuals.detail', $id),
        ]);
    }

    public function returnsApprove(int $id)
    {
        return $this->approve('return', $id);
    }

    public function manualsImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new OutboundReturnsImport();
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

                $tx = OutboundTransaction::create([
                    'code' => $this->generateCode('OUT-MNL'),
                    'type' => 'manual',
                    'ref_no' => $group['ref_no'] ?? null,
                    'note' => $group['note'] ?? null,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'pending',
                ]);
                $createdTx++;

                foreach ($group['items'] as $row) {
                    OutboundItem::create([
                        'outbound_transaction_id' => $tx->id,
                        'item_id' => $row['item_id'],
                        'qty_input' => $row['qty'],
                        'conversion_qty' => 1,
                        'qty' => $row['qty'],
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;

                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import outbound manual berhasil',
                'transactions' => $createdTx,
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import outbound manual',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function manualsTemplate()
    {
        return Excel::download(
            new OutboundManualsTemplateExport(),
            'template-import-outbound-manual.xlsx'
        );
    }

    public function returnsTemplate()
    {
        return Excel::download(
            new OutboundReturnsTemplateExport(),
            'template-import-retur-outbound.xlsx'
        );
    }

    public function returnsImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new OutboundReturnsImport();
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

                $tx = OutboundTransaction::create([
                    'warehouse_id' => Warehouse::smallId(),
                    'code' => $this->generateCode('OUT-RET'),
                    'type' => 'return',
                    'ref_no' => $group['ref_no'] ?? null,
                    'note' => $group['note'] ?? null,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'pending',
                ]);
                $createdTx++;

                foreach ($group['items'] as $row) {
                    OutboundItem::create([
                        'outbound_transaction_id' => $tx->id,
                        'item_id' => $row['item_id'],
                        'qty_input' => $row['qty'],
                        'conversion_qty' => 1,
                        'qty' => $row['qty'],
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;

                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import retur outbound berhasil',
                'transactions' => $createdTx,
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import retur outbound',
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
            'picker' => [
                'store' => route('admin.outbound.pickers.store'),
                'show' => route('admin.outbound.pickers.show', ':id'),
                'update' => route('admin.outbound.pickers.update', ':id'),
                'delete' => route('admin.outbound.pickers.destroy', ':id'),
                'detail' => route('admin.outbound.pickers.detail', ':id'),
                'approve' => route('admin.outbound.pickers.approve', ':id'),
            ],
            'manual' => [
                'store' => route('admin.outbound.manuals.store'),
                'show' => route('admin.outbound.manuals.show', ':id'),
                'update' => route('admin.outbound.manuals.update', ':id'),
                'delete' => route('admin.outbound.manuals.destroy', ':id'),
                'detail' => route('admin.outbound.manuals.detail', ':id'),
                'approve' => route('admin.outbound.manuals.approve', ':id'),
                'scan' => route('admin.outbound.manuals.scan', ':id'),
            ],
            'return' => [
                'create' => route('admin.outbound.returns.create'),
                'store' => route('admin.outbound.returns.store'),
                'show' => route('admin.outbound.returns.show', ':id'),
                'edit' => route('admin.outbound.returns.edit', ':id'),
                'update' => route('admin.outbound.returns.update', ':id'),
                'delete' => route('admin.outbound.returns.destroy', ':id'),
                'detail' => route('admin.outbound.returns.detail', ':id'),
                'approve' => route('admin.outbound.returns.approve', ':id'),
            ],
        ];

        return view('admin.stock-flow.index', [
            'pageTitle' => $pageTitle,
            'dataUrl' => route("admin.outbound.{$routeBase}.data"),
            'storeUrl' => route("admin.outbound.{$routeBase}.store"),
            'showUrlTpl' => route("admin.outbound.{$routeBase}.show", ':id'),
            'updateUrlTpl' => route("admin.outbound.{$routeBase}.update", ':id'),
            'deleteUrlTpl' => route("admin.outbound.{$routeBase}.destroy", ':id'),
            'detailUrlTpl' => route("admin.outbound.{$routeBase}.detail", ':id'),
            'items' => $items,
            'warehouses' => $warehouses,
            'smallWarehouse' => Warehouse::findOrFail(Warehouse::smallId()),
            'locksToSmallWarehouse' => $type === 'return',
            'typeOptions' => $typeOptions,
            'typeDefault' => $type,
            'routeMap' => $routeMap,
            'importUrl' => match ($type) {
                'return' => route('admin.outbound.returns.import'),
                'manual' => route('admin.outbound.manuals.import'),
                default => null,
            },
            'importTitle' => match ($type) {
                'return' => 'Import Retur Outbound',
                'manual' => 'Import Manual Outbound',
                default => null,
            },
            'templateUrl' => match ($type) {
                'return' => route('admin.outbound.returns.template'),
                'manual' => route('admin.outbound.manuals.template'),
                default => null,
            },
        ]);
    }

    private function returnForm(string $pageTitle, ?OutboundTransaction $transaction = null)
    {
        $items = Item::with(['units' => fn ($q) => $q->orderByDesc('is_base')->orderBy('conversion_qty')])
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);

        $itemUnitsJson = $items->mapWithKeys(function ($item) {
            return [
                $item->id => $item->units->map(fn ($unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'conversion_qty' => (int) $unit->conversion_qty,
                    'is_base' => (bool) $unit->is_base,
                ])->values(),
            ];
        });

        return view('admin.stock-flow.return-form', [
            'pageTitle' => $pageTitle,
            'transaction' => $transaction,
            'items' => $items,
            'itemUnitsJson' => $itemUnitsJson,
            'smallWarehouse' => Warehouse::findOrFail(Warehouse::smallId()),
            'storeUrl' => route('admin.outbound.returns.store'),
            'updateUrl' => $transaction ? route('admin.outbound.returns.update', $transaction->id) : null,
            'indexUrl' => route('admin.outbound.returns.index'),
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

        $query = OutboundTransaction::query()
            ->with(['items.item', 'creator', 'warehouse'])
            ->select([
                'outbound_transactions.id',
                'outbound_transactions.code',
                'outbound_transactions.transacted_at',
                'outbound_transactions.type',
                'outbound_transactions.ref_no',
                'outbound_transactions.note',
                'outbound_transactions.status',
                'outbound_transactions.scan_status',
                'outbound_transactions.created_by',
                'outbound_transactions.warehouse_id',
            ])
            ->orderBy('outbound_transactions.transacted_at', 'desc');
        if ($baseType) {
            $query->where('outbound_transactions.type', $baseType);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('outbound_transactions.code', 'like', "%{$search}%")
                    ->orWhere('outbound_transactions.ref_no', 'like', "%{$search}%")
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($query, $request);

        $recordsTotalQuery = OutboundTransaction::query();
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
            $labels = $items->map(function ($it) {
                $sku = trim($it->item?->sku ?? '');
                if ($sku === '') {
                    return '';
                }
                $qty = (int) ($it->qty ?? 0);
                return sprintf('%s (%d)', $sku, $qty);
            })->filter()->values();
            $itemLabel = $labels->implode(', ');
            $totalQty = (int) $items->sum('qty');
            return [
                'id' => $row->id,
                'code' => $row->code,
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'warehouse' => $row->warehouse?->name ?? '-',
                'item' => $itemLabel ?: '-',
                'qty' => $totalQty,
                'note' => $row->note ?? '',
                'type' => $row->type,
                'status' => $row->status ?? 'pending',
                'scan_status' => $row->scan_status ?? 'not_started',
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
        $tx = OutboundTransaction::with('items')
            ->where('type', $type)
            ->findOrFail($id);

        return response()->json([
            'id' => $tx->id,
            'code' => $tx->code,
            'ref_no' => $tx->ref_no,
            'note' => $tx->note,
            'status' => $tx->status ?? 'pending',
            'warehouse_id' => $tx->warehouse_id ?: Warehouse::defaultId(),
            'transacted_at' => $tx->transacted_at?->format('Y-m-d\TH:i'),
            'items' => $tx->items->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'unit_id' => $item->unit_id,
                    'qty' => $item->qty_input ?: $item->qty,
                    'stock_source' => $item->stock_source ?? 'regular',
                    'note' => $item->note ?? '',
                ];
            })->values(),
        ]);
    }

    private function detail(string $type, string $pageTitle, string $routeBase, int $id)
    {
        $tx = OutboundTransaction::with(['items.item', 'creator', 'approver', 'scanCompleter', 'suratJalan.creator', 'manualScanLogs'])
            ->where('type', $type)
            ->findOrFail($id);

        $totalQty = $tx->items->sum('qty');

        return view('admin.stock-flow.detail', [
            'pageTitle' => $pageTitle,
            'transaction' => $tx,
            'totalQty' => $totalQty,
            'backUrl' => route("admin.outbound.{$routeBase}.index"),
        ]);
    }

    private function store(Request $request, string $type)
    {
        $validated = $this->validatePayload($request, $type);
        if ($type === 'return') {
            $this->assertOutboundReturnStockAvailable($validated['items'], $validated['warehouse_id']);
        }

        $prefix = match ($type) {
            'picker' => 'OUT-PCK',
            'return' => 'OUT-RET',
            default => 'OUT-MNL',
        };

        $code = $this->generateCode($prefix);
        $transactedAt = $validated['transacted_at'] ?? now();

        DB::beginTransaction();
        try {
            $tx = OutboundTransaction::create([
                'warehouse_id' => $validated['warehouse_id'],
                'code' => $code,
                'type' => $type,
                'ref_no' => $validated['ref_no'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $row) {
                OutboundItem::create([
                    'outbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'unit_id' => $row['unit_id'] ?? null,
                    'qty_input' => $row['qty_input'] ?? $row['qty'],
                    'conversion_qty' => $row['conversion_qty'] ?? 1,
                    'stock_source' => $row['stock_source'] ?? 'regular',
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
                'message' => 'Gagal menyimpan outbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Outbound berhasil disimpan',
        ]);
    }

    private function update(Request $request, string $type, int $id)
    {
        $validated = $this->validatePayload($request, $type);
        if ($type === 'return') {
            $this->assertOutboundReturnStockAvailable($validated['items'], $validated['warehouse_id']);
        }

        DB::beginTransaction();
        try {
            $tx = OutboundTransaction::where('type', $type)->findOrFail($id);
            if (($tx->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa diubah'], 422);
            }

            StockService::rollbackBySource('outbound', $tx->id);
            StockMutation::where('source_type', 'outbound')->where('source_id', $tx->id)->delete();
            DamagedStockService::rollbackBySource('outbound', $tx->id);
            DamagedStockMutation::where('source_type', 'outbound')->where('source_id', $tx->id)->delete();
            OutboundManualScanLog::where('outbound_transaction_id', $tx->id)->delete();
            OutboundItem::where('outbound_transaction_id', $tx->id)->delete();

            $tx->update([
                'warehouse_id' => $validated['warehouse_id'],
                'ref_no' => $validated['ref_no'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? $tx->transacted_at,
                'scan_status' => 'not_started',
                'scan_completed_at' => null,
                'scan_completed_by' => null,
            ]);

            foreach ($validated['items'] as $row) {
                OutboundItem::create([
                    'outbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'unit_id' => $row['unit_id'] ?? null,
                    'qty_input' => $row['qty_input'] ?? $row['qty'],
                    'conversion_qty' => $row['conversion_qty'] ?? 1,
                    'stock_source' => $row['stock_source'] ?? 'regular',
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
                'message' => 'Gagal memperbarui outbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Outbound berhasil diperbarui',
        ]);
    }

    private function destroy(string $type, int $id)
    {
        DB::beginTransaction();
        try {
            $tx = OutboundTransaction::where('type', $type)->findOrFail($id);
            if (($tx->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa dihapus'], 422);
            }

            StockService::rollbackBySource('outbound', $tx->id);
            StockMutation::where('source_type', 'outbound')->where('source_id', $tx->id)->delete();
            DamagedStockService::rollbackBySource('outbound', $tx->id);
            DamagedStockMutation::where('source_type', 'outbound')->where('source_id', $tx->id)->delete();
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
                'message' => 'Gagal menghapus outbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Outbound berhasil dihapus',
        ]);
    }

    private function approve(string $type, int $id)
    {
        DB::beginTransaction();
        try {
            $tx = OutboundTransaction::where('type', $type)
                ->lockForUpdate()
                ->findOrFail($id);

            if (($tx->status ?? 'pending') === 'approved') {
                DB::commit();
                return response()->json(['message' => 'Data sudah disetujui']);
            }

            if ($type === 'return') {
                $this->assertOutboundReturnStockAvailable(
                    $tx->items->map(fn ($row) => [
                        'item_id' => (int) $row->item_id,
                        'qty' => (int) $row->qty,
                        'stock_source' => $row->stock_source ?? 'regular',
                    ])->all()
                );
            }

            if ($type === 'manual' && ($tx->scan_status ?? 'not_started') !== 'complete') {
                throw ValidationException::withMessages([
                    'scan' => 'Outbound manual harus scan complete sebelum approve.',
                ]);
            }
            $this->postStockMovements($tx, $type);

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
                'message' => 'Gagal menyetujui outbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Outbound berhasil disetujui']);
    }

    private function assertOutboundReturnStockAvailable(array $items, ?int $warehouseId = null): void
    {
        $warehouseId ??= Warehouse::defaultId();
        $grouped = collect($items)
            ->groupBy(fn ($row) => ((int) ($row['item_id'] ?? 0)).'|'.($row['stock_source'] ?? 'regular'))
            ->map(fn ($rows) => [
                'item_id' => (int) ($rows->first()['item_id'] ?? 0),
                'stock_source' => $rows->first()['stock_source'] ?? 'regular',
                'qty' => (int) $rows->sum('qty'),
            ]);

        $itemIds = $grouped->pluck('item_id')->filter()->unique()->values()->all();
        $itemsById = Item::whereIn('id', $itemIds)->get(['id', 'sku', 'is_bundle'])->keyBy('id');
        $regularStocks = ItemStock::where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $itemIds)
            ->pluck('stock', 'item_id');
        $damagedStocks = DamagedItemStock::where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $itemIds)
            ->pluck('stock', 'item_id');

        foreach ($grouped as $row) {
            $itemId = (int) $row['item_id'];
            $qty = (int) $row['qty'];
            $source = $row['stock_source'] ?? 'regular';
            $item = $itemsById->get($itemId);
            $sku = $item?->sku ?? 'item '.$itemId;

            if ($source === 'damaged') {
                $available = (int) ($damagedStocks[$itemId] ?? 0);
                if ($available < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Stok gudang rusak tidak mencukupi untuk {$sku}. Tersedia {$available}, diminta {$qty}.",
                    ]);
                }
                continue;
            }

            if ($item?->is_bundle) {
                BundleService::assertVirtualStockSufficient($itemId, $qty, $warehouseId);
                continue;
            }

            $available = (int) ($regularStocks[$itemId] ?? 0);
            if ($available < $qty) {
                throw ValidationException::withMessages([
                    'items' => "Stok gudang reguler tidak mencukupi untuk {$sku}. Tersedia {$available}, diminta {$qty}.",
                ]);
            }
        }
    }

    private function postStockMovements(OutboundTransaction $tx, string $type): void
    {
        $tx->loadMissing('items');

        $itemIds = $tx->items->pluck('item_id')->unique()->all();
        $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');

        foreach ($tx->items as $row) {
            $item = $items->get($row->item_id);
            $stockSource = $row->stock_source ?? 'regular';
            $baseKey = StockService::idempotencyKey(['stock', 'outbound', $type, $stockSource, $tx->id, $row->item_id]);

            if ($stockSource === 'damaged') {
                DamagedStockService::mutate([
                    'warehouse_id' => $tx->warehouse_id ?: Warehouse::defaultId(),
                    'item_id' => $row->item_id,
                    'direction' => 'out',
                    'qty' => $row->qty,
                    'source_type' => 'outbound',
                    'source_subtype' => $type,
                    'source_id' => $tx->id,
                    'source_code' => $tx->code,
                    'note' => $row->note ?? null,
                    'occurred_at' => $tx->transacted_at ?? now(),
                    'created_by' => auth()->id(),
                    'idempotency_key' => $baseKey,
                ]);
                continue;
            }

            if ($item && $item->is_bundle) {
                $this->deductBundleComponentsForOutbound($item, $row->qty, $tx, $type, $baseKey);
            } else {
                StockService::mutate([
                    'warehouse_id' => $tx->warehouse_id ?: Warehouse::defaultId(),
                    'item_id' => $row->item_id,
                    'unit_id' => $row->unit_id,
                    'direction' => 'out',
                    'qty' => $row->qty,
                    'qty_input' => (int) ($row->qty_input ?: $row->qty),
                    'conversion_qty' => (int) ($row->conversion_qty ?: 1),
                    'source_type' => 'outbound',
                    'source_subtype' => $type,
                    'source_id' => $tx->id,
                    'source_code' => $tx->code,
                    'note' => $row->note ?? null,
                    'occurred_at' => $tx->transacted_at ?? now(),
                    'created_by' => auth()->id(),
                    'idempotency_key' => $baseKey,
                ]);
            }
        }
    }

    private function deductBundleComponentsForOutbound(Item $item, int $bundleQty, OutboundTransaction $tx, string $type, string $baseKey): void
    {
        $components = ItemBundle::where('bundle_item_id', $item->id)->lockForUpdate()->get();

        $warehouseId = (int) ($tx->warehouse_id ?: Warehouse::defaultId());
        BundleService::assertVirtualStockSufficient($item->id, $bundleQty, $warehouseId);

        foreach ($components as $index => $component) {
            $componentQty = $bundleQty * (int) $component->qty;
            $idempotencyKey = $index === 0
                ? $baseKey
                : StockService::idempotencyKey([$baseKey, 'comp', $component->component_item_id]);

            StockService::mutate([
                'warehouse_id' => $warehouseId,
                'item_id' => $component->component_item_id,
                'direction' => 'out',
                'qty' => $componentQty,
                'source_type' => 'outbound',
                'source_subtype' => $type,
                'source_id' => $tx->id,
                'source_code' => $tx->code,
                'note' => null,
                'occurred_at' => $tx->transacted_at ?? now(),
                'created_by' => auth()->id(),
                'idempotency_key' => $idempotencyKey,
            ]);
        }
    }

    private function validatePayload(Request $request, ?string $type = null): array
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:item_units,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.stock_source' => ['nullable', 'in:regular,damaged'],
            'items.*.note' => ['nullable', 'string'],
            'ref_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);
        $validated['warehouse_id'] = $type === 'return'
            ? Warehouse::smallId()
            : (int) ($validated['warehouse_id'] ?? Warehouse::defaultId());
        $isBulkWarehouse = Warehouse::whereKey($validated['warehouse_id'])->value('type') === Warehouse::TYPE_BULK;

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['qty'] ?? 0) > 0 && (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row, $index) use ($isBulkWarehouse) {
                $qtyInput = (int) ($row['qty'] ?? 0);
                $unitId = (int) ($row['unit_id'] ?? 0);
                $conversionQty = 1;
                if ($isBulkWarehouse && $unitId <= 0) {
                    $item = Item::with('packageUnit')->find((int) $row['item_id']);
                    if ($item?->packageUnit && (int) $item->packageUnit->conversion_qty >= 2) {
                        $unitId = (int) $item->packageUnit->id;
                    } else {
                        throw ValidationException::withMessages([
                            "items.{$index}.unit_id" => 'Item '.($item?->sku ?? '').' belum memiliki isi per kemasan yang valid di master item.',
                        ]);
                    }
                }
                if ($unitId > 0) {
                    $unit = ItemUnit::whereKey($unitId)
                        ->where('item_id', (int) $row['item_id'])
                        ->first();
                    if (!$unit) {
                        throw ValidationException::withMessages([
                            "items.{$index}.unit_id" => 'Satuan item outbound tidak valid.',
                        ]);
                    }
                    if ($isBulkWarehouse && $unit->is_base) {
                        throw ValidationException::withMessages([
                            "items.{$index}.unit_id" => 'Gudang Besar wajib menggunakan satuan koli/kemasan, bukan satuan dasar.',
                        ]);
                    }
                    $conversionQty = (int) $unit->conversion_qty;
                }
                return [
                    'item_id' => (int) $row['item_id'],
                    'unit_id' => $unitId ?: null,
                    'qty_input' => $qtyInput,
                    'conversion_qty' => $conversionQty,
                    'qty' => $qtyInput * $conversionQty,
                    'stock_source' => in_array($row['stock_source'] ?? null, ['regular', 'damaged'], true) ? $row['stock_source'] : 'regular',
                    'note' => $row['note'] ?? null,
                ];
            })->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal 1 item diperlukan',
            ]);
        }

        foreach ($items as $row) {
            if (
                $type === 'return'
                && $row['unit_id']
                && !ItemUnit::whereKey($row['unit_id'])->where('is_base', true)->exists()
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Retur Gudang Kecil wajib menggunakan satuan PCS/SET.',
                ]);
            }
            StockService::assertWarehouseQuantity(
                $validated['warehouse_id'],
                (int) $row['item_id'],
                (int) $row['qty'],
                $row['unit_id']
            );
        }

        $duplicates = $items->groupBy(fn ($row) => $row['item_id'].'|'.$row['stock_source'])
            ->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item dengan sumber stok yang sama tidak boleh duplikat pada outbound',
            ]);
        }

        $normalized = $items->groupBy(fn ($row) => $row['item_id'].'|'.$row['stock_source'])
            ->map(function ($rows) {
                $first = $rows->first();
                $qty = $rows->sum('qty');
                $note = $rows->pluck('note')->first(fn ($n) => $n !== null && $n !== '') ?? null;
                return [
                    'item_id' => (int) $first['item_id'],
                    'unit_id' => $first['unit_id'] ?? null,
                    'qty_input' => $rows->sum('qty_input'),
                    'conversion_qty' => $first['conversion_qty'] ?? 1,
                    'qty' => $qty,
                    'stock_source' => $first['stock_source'],
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
            'picker' => 'QC Scan',
            'manual' => 'Manual',
            'return' => 'Retur',
        ];
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('outbound_transactions.transacted_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('outbound_transactions.transacted_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function generateSuratJalanCode(): string
    {
        $prefix = 'SJ-'.now()->format('Ymd');
        $last = SuratJalan::where('code', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->code);
            $seq = ((int) end($parts)) + 1;
        }

        return $prefix.'-'.str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
