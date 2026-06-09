<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedGood;
use App\Models\InboundTransaction;
use App\Models\OutboundTransaction;
use App\Models\PickerSession;
use App\Models\QcScanResi;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StockMutationController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('admin.inventory.stock-mutations.index', compact('warehouses'));
    }

    public function data(Request $request)
    {
        $query = StockMutation::query()
            ->with(['item', 'creator', 'warehouse', 'unit'])
            ->orderBy('occurred_at', 'desc');

        if ($warehouseId = $request->integer('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('source_code', 'like', "%{$search}%")
                    ->orWhere('source_type', 'like', "%{$search}%")
                    ->orWhere('source_subtype', 'like', "%{$search}%")
                    ->orWhereHas('creator', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($query, $request);

        $recordsTotal = StockMutation::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($m) {
            $itemLabel = trim(($m->item?->sku ?? '').' - '.($m->item?->name ?? ''));
            $ts = $m->occurred_at ? Carbon::parse($m->occurred_at)->format('Y-m-d H:i') : '';
            $direction = $m->direction === 'in' ? 'IN' : 'OUT';
            $source = strtoupper($m->source_type ?? '').($m->source_subtype ? ' / '.$m->source_subtype : '');
            return [
                'id' => $m->id,
                'occurred_at' => $ts,
                'item' => $itemLabel,
                'warehouse' => $m->warehouse?->name ?? '-',
                'user' => $m->creator?->name ?? '-',
                'direction' => $direction,
                'qty' => (int) $m->qty,
                'qty_input' => (int) ($m->qty_input ?? $m->qty),
                'unit' => $m->unit?->name ?? '',
                'stock_before' => $m->stock_before,
                'stock_after' => $m->stock_after,
                'source' => trim($source),
                'source_code' => $m->source_code ?? '',
                'note' => $m->note ?? '',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('occurred_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('occurred_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    public function show(int $id)
    {
        $mutation = StockMutation::with(['item', 'creator', 'warehouse', 'unit'])->findOrFail($id);
        [$sourceSummary, $sourceItems] = $this->resolveSource($mutation);

        $itemLabel = trim(($mutation->item?->sku ?? '').' - '.($mutation->item?->name ?? ''));
        $direction = $mutation->direction === 'in' ? 'IN' : 'OUT';
        $source = strtoupper($mutation->source_type ?? '').($mutation->source_subtype ? ' / '.$mutation->source_subtype : '');

        return response()->json([
            'mutation' => [
                'id' => $mutation->id,
                'occurred_at' => $mutation->occurred_at?->format('Y-m-d H:i'),
                'item' => $itemLabel,
                'warehouse' => $mutation->warehouse?->name ?? '-',
                'direction' => $direction,
                'qty' => (int) $mutation->qty,
                'qty_input' => (int) ($mutation->qty_input ?? $mutation->qty),
                'unit' => $mutation->unit?->name ?? '',
                'stock_before' => $mutation->stock_before,
                'stock_after' => $mutation->stock_after,
                'source' => trim($source),
                'source_code' => $mutation->source_code ?? '',
                'note' => $mutation->note ?? '',
                'user' => $mutation->creator?->name ?? '-',
            ],
            'source' => $sourceSummary ? array_merge($sourceSummary, ['items' => $sourceItems]) : null,
        ]);
    }

    private function resolveSource(StockMutation $mutation): array
    {
        $sourceSummary = null;
        $sourceItems = [];

        switch ($mutation->source_type) {
            case 'inbound':
                $tx = InboundTransaction::with('items.item')->find($mutation->source_id);
                if ($tx) {
                    $sourceSummary = [
                        'label' => 'Inbound / '.$tx->type,
                        'code' => $tx->code,
                        'ref' => $tx->ref_no ?? '-',
                        'date' => $tx->transacted_at?->format('Y-m-d H:i'),
                        'note' => $tx->note ?? '-',
                    ];
                    $sourceItems = $tx->items->map(function ($row) {
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                            'qty' => (int) $row->qty,
                            'note' => $row->note ?? '-',
                        ];
                    })->values()->all();
                }
                break;
            case 'outbound':
                $tx = OutboundTransaction::with('items.item')->find($mutation->source_id);
                if ($tx) {
                    $sourceSummary = [
                        'label' => 'Outbound / '.$tx->type,
                        'code' => $tx->code,
                        'ref' => $tx->ref_no ?? '-',
                        'date' => $tx->transacted_at?->format('Y-m-d H:i'),
                        'note' => $tx->note ?? '-',
                    ];
                    $sourceItems = $tx->items->map(function ($row) {
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                            'qty' => (int) $row->qty,
                            'note' => $row->note ?? '-',
                        ];
                    })->values()->all();
                }
                break;
            case 'opname':
                $opname = StockOpname::with('items.item')->find($mutation->source_id);
                if ($opname) {
                    $sourceSummary = [
                        'label' => 'Stock Opname',
                        'code' => $opname->code,
                        'ref' => '-',
                        'date' => $opname->transacted_at?->format('Y-m-d H:i'),
                        'note' => $opname->note ?? '-',
                    ];
                    $sourceItems = $opname->items->map(function ($row) {
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                            'qty' => (int) $row->adjustment,
                            'note' => $row->note ?? '-',
                            'meta' => 'System '.$row->system_qty.', Counted '.$row->counted_qty.', Adj '.$row->adjustment,
                        ];
                    })->values()->all();
                }
                break;
            case 'transfer':
                $transfer = StockTransfer::with(['items.item', 'items.unit', 'sourceWarehouse', 'destinationWarehouse'])
                    ->find($mutation->source_id);
                if ($transfer) {
                    $sourceSummary = [
                        'label' => 'Transfer Antar Gudang',
                        'code' => $transfer->code,
                        'ref' => ($transfer->sourceWarehouse?->name ?? '-').' -> '.($transfer->destinationWarehouse?->name ?? '-'),
                        'date' => $transfer->transacted_at?->format('Y-m-d H:i'),
                        'note' => $transfer->note ?? '-',
                    ];
                    $sourceItems = $transfer->items->map(fn ($row) => [
                        'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                        'qty' => (int) $row->qty_base,
                        'note' => $row->note ?? '-',
                        'meta' => $row->qty_input.' '.($row->unit?->name ?? 'unit').' x '.$row->conversion_qty,
                    ])->values()->all();
                }
                break;
            case 'adjustment':
                $adjustment = \App\Models\StockAdjustment::with('items.item')->find($mutation->source_id);
                if ($adjustment) {
                    $sourceSummary = [
                        'label' => 'Penyesuaian Stok',
                        'code' => $adjustment->code,
                        'ref' => '-',
                        'date' => $adjustment->transacted_at?->format('Y-m-d H:i'),
                        'note' => $adjustment->note ?? '-',
                    ];
                    $sourceItems = $adjustment->items->map(function ($row) {
                        $dir = $row->direction === 'in' ? 'IN' : 'OUT';
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')).' ('.$dir.')',
                            'qty' => (int) $row->qty,
                            'note' => $row->note ?? '-',
                        ];
                    })->values()->all();
                }
                break;
            case 'damaged':
                $damage = DamagedGood::with('items.item')->find($mutation->source_id);
                if ($damage) {
                    $sourceSummary = [
                        'label' => 'Barang Rusak / '.$damage->source_type,
                        'code' => $damage->code,
                        'ref' => $damage->source_ref ?? '-',
                        'date' => $damage->transacted_at?->format('Y-m-d H:i'),
                        'note' => $damage->note ?? '-',
                    ];
                    $sourceItems = $damage->items->map(function ($row) {
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                            'qty' => (int) $row->qty,
                            'note' => $row->note ?? '-',
                        ];
                    })->values()->all();
                }
                break;
            case 'picker':
            case 'qc':
                $sessionLabel = 'QC Scan Mobile';
                $session = PickerSession::with('items.item', 'user')->find($mutation->source_id);
                if ($session) {
                    $sourceSummary = [
                        'label' => $sessionLabel,
                        'code' => $session->code,
                        'ref' => $session->user?->name ?? '-',
                        'date' => ($session->submitted_at ?? $session->started_at)?->format('Y-m-d H:i'),
                        'note' => $session->note ?? '-',
                    ];
                    $sourceItems = $session->items->map(function ($row) {
                        return [
                            'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                            'qty' => (int) $row->qty,
                            'note' => $row->note ?? '-',
                        ];
                    })->values()->all();
                }
                break;
            case 'qc_resi':
                $qcResi = QcScanResi::with(['scanner', 'resi', 'items.item'])->find($mutation->source_id);
                if ($qcResi) {
                    $sourceSummary = [
                        'label' => 'QC Scan Resi',
                        'code' => $qcResi->resi?->no_resi ?? $qcResi->resi?->id_pesanan ?? '-',
                        'ref' => $qcResi->resi?->no_resi ?? $qcResi->resi?->id_pesanan ?? '-',
                        'date' => ($qcResi->completed_at ?? $qcResi->scanned_at)?->format('Y-m-d H:i'),
                        'note' => $qcResi->scanner?->name ?? '-',
                    ];
                    $sourceItems = $qcResi->items->map(function ($row) {
                        return [
                            'label' => trim(($row->sku ?? '').' - '.($row->item?->name ?? '')),
                            'qty' => (int) $row->scanned_qty,
                            'note' => ((int) $row->scanned_qty).'/'.((int) $row->required_qty),
                        ];
                    })->values()->all();
                }
                break;
        }

        return [$sourceSummary, $sourceItems];
    }
}
