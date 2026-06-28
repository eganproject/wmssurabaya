<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurir;
use App\Models\PackerScanOut;
use App\Models\QcScanResi;
use App\Models\Resi;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();
        $selectedDate = $today;
        $dateInput = $request->query('date');
        if ($dateInput) {
            try {
                $selectedDate = Carbon::parse($dateInput)->toDateString();
            } catch (\Throwable) {
                $selectedDate = $today;
            }
        }

        $resiBase = Resi::query()->whereDate('tanggal_upload', $selectedDate);
        $activeResiBase = (clone $resiBase)->where(function ($q) {
            $q->whereNull('status')
                ->orWhere('status', '!=', 'canceled');
        });

        $totalResiActive = (clone $activeResiBase)->count();
        $totalResiCanceled = (clone $resiBase)->where('status', 'canceled')->count();
        $totalResiUpdatedAt = (clone $activeResiBase)->max('updated_at');
        $totalQcScan = QcScanResi::query()
            ->whereIn('resi_id', (clone $resiBase)->select('id'))
            ->count();
        $totalQcScanUpdatedAt = QcScanResi::query()
            ->whereIn('resi_id', (clone $resiBase)->select('id'))
            ->max('scanned_at');
        $totalScanOut = PackerScanOut::query()
            ->whereIn('resi_id', (clone $resiBase)->select('id'))
            ->count();
        $totalScanUpdatedAt = PackerScanOut::query()
            ->whereIn('resi_id', (clone $resiBase)->select('id'))
            ->max('scanned_at');
        $totalResiUpdated = $totalResiUpdatedAt ? Carbon::parse($totalResiUpdatedAt)->format('H:i') : '-';
        $totalQcScanUpdated = $totalQcScanUpdatedAt ? Carbon::parse($totalQcScanUpdatedAt)->format('H:i') : '-';
        $totalScanUpdated = $totalScanUpdatedAt ? Carbon::parse($totalScanUpdatedAt)->format('H:i') : '-';

        $resiCounts = Resi::select('kurir_id', DB::raw('count(*) as total'))
            ->whereDate('tanggal_upload', $selectedDate)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->groupBy('kurir_id')
            ->pluck('total', 'kurir_id')
            ->toArray();

        $canceledCounts = Resi::select('kurir_id', DB::raw('count(*) as total'))
            ->whereDate('tanggal_upload', $selectedDate)
            ->where('status', 'canceled')
            ->groupBy('kurir_id')
            ->pluck('total', 'kurir_id')
            ->toArray();

        $scanCounts = PackerScanOut::query()
            ->join('resis', 'resis.id', '=', 'packer_scan_outs.resi_id')
            ->select('resis.kurir_id', DB::raw('count(*) as total'))
            ->whereDate('resis.tanggal_upload', $selectedDate)
            ->groupBy('resis.kurir_id')
            ->pluck('total', 'resis.kurir_id')
            ->toArray();

        $resiLatest = Resi::select('kurir_id', DB::raw('max(updated_at) as latest'))
            ->whereDate('tanggal_upload', $selectedDate)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->groupBy('kurir_id')
            ->pluck('latest', 'kurir_id')
            ->toArray();

        $scanLatest = PackerScanOut::query()
            ->join('resis', 'resis.id', '=', 'packer_scan_outs.resi_id')
            ->select('resis.kurir_id', DB::raw('max(packer_scan_outs.scanned_at) as latest'))
            ->whereDate('resis.tanggal_upload', $selectedDate)
            ->groupBy('resis.kurir_id')
            ->pluck('latest', 'resis.kurir_id')
            ->toArray();

        $kurirs = Kurir::orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($kurir) use ($resiCounts, $canceledCounts, $scanCounts, $resiLatest, $scanLatest) {
                $resiTotal = (int) ($resiCounts[$kurir->id] ?? 0);
                $scanTotal = (int) ($scanCounts[$kurir->id] ?? 0);
                $canceledTotal = (int) ($canceledCounts[$kurir->id] ?? 0);
                $latestResi = $resiLatest[$kurir->id] ?? null;
                $latestScan = $scanLatest[$kurir->id] ?? null;
                $latestRaw = $latestResi && $latestScan
                    ? (Carbon::parse($latestResi)->greaterThan(Carbon::parse($latestScan)) ? $latestResi : $latestScan)
                    : ($latestResi ?: $latestScan);
                $latestTime = $latestRaw ? Carbon::parse($latestRaw)->format('H:i') : '-';
                return [
                    'id' => $kurir->id,
                    'name' => $kurir->name,
                    'resi_total' => $resiTotal,
                    'scan_total' => $scanTotal,
                    'remaining' => max(0, $resiTotal - $scanTotal),
                    'canceled_total' => $canceledTotal,
                    'last_update' => $latestTime,
                ];
            });

        $lowStockLimit = 5;
        $stockWindowStart = Carbon::parse($selectedDate)->subDays(29)->startOfDay();
        $stockWindowEnd = Carbon::parse($selectedDate)->endOfDay();
        $inventoryWarehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN type = 'bulk' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'code']);
        $selectedInventoryWarehouseId = $request->integer('inventory_warehouse_id') ?: null;
        if ($selectedInventoryWarehouseId && !$inventoryWarehouses->contains('id', $selectedInventoryWarehouseId)) {
            $selectedInventoryWarehouseId = null;
        }
        $selectedInventoryWarehouse = $selectedInventoryWarehouseId
            ? $inventoryWarehouses->firstWhere('id', $selectedInventoryWarehouseId)
            : null;

        $stockRowBase = DB::table('items')
            ->leftJoin('item_stocks', function ($join) use ($selectedInventoryWarehouseId) {
                $join->on('items.id', '=', 'item_stocks.item_id');
                if ($selectedInventoryWarehouseId) {
                    $join->where('item_stocks.warehouse_id', '=', $selectedInventoryWarehouseId);
                }
            })
            ->leftJoin('warehouses', 'warehouses.id', '=', 'item_stocks.warehouse_id');

        $stockTotalQuery = DB::table('item_stocks');
        if ($selectedInventoryWarehouseId) {
            $stockTotalQuery->where('warehouse_id', $selectedInventoryWarehouseId);
        }

        $inventorySummary = [
            'total_sku' => (int) DB::table('items')->count(),
            'total_stock' => (int) $stockTotalQuery->sum('stock'),
            'out_of_stock' => (clone $stockRowBase)
                ->whereRaw('COALESCE(item_stocks.stock, 0) <= 0')
                ->count(),
            'low_stock' => (clone $stockRowBase)
                ->whereRaw('COALESCE(item_stocks.stock, 0) > 0')
                ->whereRaw('COALESCE(item_stocks.stock, 0) <= ?', [$lowStockLimit])
                ->count(),
        ];

        $stockSelect = [
            'items.sku',
            'items.name',
            DB::raw("COALESCE(warehouses.name, '-') as warehouse_name"),
            DB::raw('COALESCE(item_stocks.stock, 0) as stock'),
        ];

        $outOfStockItems = (clone $stockRowBase)
            ->select($stockSelect)
            ->whereRaw('COALESCE(item_stocks.stock, 0) <= 0')
            ->orderBy('items.sku')
            ->limit(10)
            ->get();

        $lowStockItems = (clone $stockRowBase)
            ->select($stockSelect)
            ->whereRaw('COALESCE(item_stocks.stock, 0) > 0')
            ->whereRaw('COALESCE(item_stocks.stock, 0) <= ?', [$lowStockLimit])
            ->orderBy('item_stocks.stock')
            ->orderBy('items.sku')
            ->limit(10)
            ->get();

        $stockMutationToday = [
            'in_qty' => (int) DB::table('stock_mutations')
                ->where('direction', 'in')
                ->whereDate('occurred_at', $selectedDate)
                ->sum('qty'),
            'out_qty' => (int) DB::table('stock_mutations')
                ->where('direction', 'out')
                ->whereDate('occurred_at', $selectedDate)
                ->sum('qty'),
            'in_count' => (int) DB::table('stock_mutations')
                ->where('direction', 'in')
                ->whereDate('occurred_at', $selectedDate)
                ->count(),
            'out_count' => (int) DB::table('stock_mutations')
                ->where('direction', 'out')
                ->whereDate('occurred_at', $selectedDate)
                ->count(),
        ];

        $fastMovingItems = DB::table('stock_mutations')
            ->join('items', 'items.id', '=', 'stock_mutations.item_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_mutations.warehouse_id')
            ->select([
                'items.sku',
                'items.name',
                DB::raw("COALESCE(warehouses.name, '-') as warehouse_name"),
                DB::raw('SUM(stock_mutations.qty) as total_qty'),
                DB::raw('COUNT(stock_mutations.id) as mutation_count'),
                DB::raw('MAX(stock_mutations.occurred_at) as latest_at'),
            ])
            ->where('stock_mutations.direction', 'out')
            ->whereBetween('stock_mutations.occurred_at', [$stockWindowStart, $stockWindowEnd])
            ->groupBy('items.id', 'items.sku', 'items.name', 'warehouses.name')
            ->orderByDesc('mutation_count')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        $recentMutations = DB::table('stock_mutations')
            ->join('items', 'items.id', '=', 'stock_mutations.item_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_mutations.warehouse_id')
            ->select([
                'stock_mutations.direction',
                'stock_mutations.qty',
                'stock_mutations.source_type',
                'stock_mutations.source_subtype',
                'stock_mutations.source_code',
                'stock_mutations.occurred_at',
                'items.sku',
                'items.name',
                DB::raw("COALESCE(warehouses.name, '-') as warehouse_name"),
            ])
            ->orderByDesc('stock_mutations.occurred_at')
            ->orderByDesc('stock_mutations.id')
            ->limit(10)
            ->get();

        $pendingStatus = fn ($query) => $query->where(function ($q) {
            $q->whereNull('status')->orWhere('status', 'pending');
        });

        $activeResiForStatus = Resi::query()
            ->whereDate('tanggal_upload', $selectedDate)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'canceled');
            });

        $statusControlSections = [
            [
                'title' => 'Proses Resi',
                'subtitle' => 'Kontrol resi import tanggal '.$selectedDate,
                'items' => [
                    [
                        'label' => 'Belum QC Scan',
                        'page' => 'Import Resi',
                        'count' => (clone $activeResiForStatus)
                            ->whereNotExists(function ($q) {
                                $q->selectRaw('1')
                                    ->from('qc_scan_resis')
                                    ->whereColumn('qc_scan_resis.resi_id', 'resis.id');
                            })
                            ->count(),
                        'description' => 'Resi aktif yang belum masuk QC scan.',
                        'url' => route('admin.inventory.resi-import.index'),
                        'icon' => 'fa-clipboard-check',
                        'tone' => 'amber',
                    ],
                    [
                        'label' => 'Belum Scan Out',
                        'page' => 'Import Resi',
                        'count' => (clone $activeResiForStatus)
                            ->whereNotExists(function ($q) {
                                $q->selectRaw('1')
                                    ->from('packer_scan_outs')
                                    ->whereColumn('packer_scan_outs.resi_id', 'resis.id');
                            })
                            ->count(),
                        'description' => 'Resi aktif yang belum selesai scan out.',
                        'url' => route('admin.inventory.resi-import.index'),
                        'icon' => 'fa-barcode',
                        'tone' => 'red',
                    ],
                ],
            ],
            [
                'title' => 'Inbound',
                'subtitle' => 'Penerimaan barang dan retur inbound',
                'items' => [
                    [
                        'label' => 'Belum Disetujui',
                        'page' => 'Penerimaan Barang',
                        'count' => $pendingStatus(DB::table('inbound_transactions')->where('type', 'receipt'))->count(),
                        'description' => 'Penerimaan barang yang masih menunggu approval.',
                        'url' => route('admin.inbound.receipts.index'),
                        'icon' => 'fa-boxes-packing',
                        'tone' => 'amber',
                    ],
                    [
                        'label' => 'Belum Difinalisasi',
                        'page' => 'Retur Inbound',
                        'count' => DB::table('inbound_transactions')
                            ->where('type', 'return')
                            ->where('status', 'approved')
                            ->count(),
                        'description' => 'Retur inbound yang sudah masuk area retur dan belum finalisasi.',
                        'url' => route('admin.inbound.returns.index'),
                        'icon' => 'fa-rotate-left',
                        'tone' => 'blue',
                    ],
                ],
            ],
            [
                'title' => 'Outbound',
                'subtitle' => 'Picker, outbound manual, dan retur outbound',
                'items' => [
                    [
                        'label' => 'Belum Disetujui',
                        'page' => 'Outbound Picker',
                        'count' => $pendingStatus(DB::table('outbound_transactions')->where('type', 'picker'))->count(),
                        'description' => 'Outbound picker yang masih menunggu approval.',
                        'url' => route('admin.outbound.pickers.index'),
                        'icon' => 'fa-person-dolly',
                        'tone' => 'amber',
                    ],
                    [
                        'label' => 'Belum Discan',
                        'page' => 'Outbound Manual',
                        'count' => DB::table('outbound_transactions')
                            ->where('type', 'manual')
                            ->where(function ($q) {
                                $q->whereNull('scan_status')->orWhere('scan_status', '!=', 'complete');
                            })
                            ->count(),
                        'description' => 'Outbound manual yang scan itemnya belum selesai.',
                        'url' => route('admin.outbound.manuals.index'),
                        'icon' => 'fa-barcode',
                        'tone' => 'red',
                    ],
                    [
                        'label' => 'Belum Disetujui',
                        'page' => 'Retur Outbound',
                        'count' => $pendingStatus(DB::table('outbound_transactions')->where('type', 'return'))->count(),
                        'description' => 'Retur outbound yang masih menunggu approval.',
                        'url' => route('admin.outbound.returns.index'),
                        'icon' => 'fa-right-left',
                        'tone' => 'amber',
                    ],
                ],
            ],
            [
                'title' => 'Inventori',
                'subtitle' => 'Kontrol dokumen stok yang belum selesai',
                'items' => [
                    [
                        'label' => 'Belum Disetujui',
                        'page' => 'Stock Adjustment',
                        'count' => $pendingStatus(DB::table('stock_adjustments'))->count(),
                        'description' => 'Adjustment stok yang belum diapprove.',
                        'url' => route('admin.inventory.stock-adjustments.index'),
                        'icon' => 'fa-sliders',
                        'tone' => 'amber',
                    ],
                    [
                        'label' => 'Belum Diterima',
                        'page' => 'Stock Transfer',
                        'count' => DB::table('stock_transfers')->whereIn('status', ['draft', 'shipped'])->count(),
                        'description' => 'Transfer stok draft atau sudah dikirim tapi belum diterima.',
                        'url' => route('admin.inventory.stock-transfers.index'),
                        'icon' => 'fa-truck-ramp-box',
                        'tone' => 'blue',
                    ],
                    [
                        'label' => 'Belum Selesai',
                        'page' => 'Stock Opname',
                        'count' => DB::table('stock_opnames')
                            ->where(function ($q) {
                                $q->whereNull('status')->orWhere('status', 'open');
                            })
                            ->count(),
                        'description' => 'Stock opname yang masih terbuka.',
                        'url' => route('admin.inventory.stock-opname.index'),
                        'icon' => 'fa-clipboard-list',
                        'tone' => 'blue',
                    ],
                    [
                        'label' => 'Belum Disetujui',
                        'page' => 'Barang Rusak',
                        'count' => $pendingStatus(DB::table('damaged_goods'))->count(),
                        'description' => 'Transaksi barang rusak yang masih menunggu approval.',
                        'url' => route('admin.inventory.damaged-goods.index'),
                        'icon' => 'fa-triangle-exclamation',
                        'tone' => 'amber',
                    ],
                    [
                        'label' => 'Belum Disetujui',
                        'page' => 'Alokasi Barang Rusak',
                        'count' => $pendingStatus(DB::table('damaged_allocations'))->count(),
                        'description' => 'Alokasi stok rusak yang belum diapprove.',
                        'url' => route('admin.inventory.damaged-allocations.index'),
                        'icon' => 'fa-box-open',
                        'tone' => 'amber',
                    ],
                ],
            ],
        ];

        $totalOpenStatus = collect($statusControlSections)
            ->sum(fn ($section) => collect($section['items'])->sum('count'));

        return view('admin.dashboard', [
            'today' => $selectedDate,
            'totalResi' => $totalResiActive,
            'totalResiCanceled' => $totalResiCanceled,
            'totalQcScan' => $totalQcScan,
            'totalScanOut' => $totalScanOut,
            'totalResiUpdated' => $totalResiUpdated,
            'totalQcScanUpdated' => $totalQcScanUpdated,
            'totalScanUpdated' => $totalScanUpdated,
            'kurirs' => $kurirs,
            'inventoryWarehouses' => $inventoryWarehouses,
            'selectedInventoryWarehouseId' => $selectedInventoryWarehouseId,
            'selectedInventoryWarehouse' => $selectedInventoryWarehouse,
            'lowStockLimit' => $lowStockLimit,
            'stockWindowStart' => $stockWindowStart->toDateString(),
            'stockWindowEnd' => $stockWindowEnd->toDateString(),
            'inventorySummary' => $inventorySummary,
            'outOfStockItems' => $outOfStockItems,
            'lowStockItems' => $lowStockItems,
            'stockMutationToday' => $stockMutationToday,
            'fastMovingItems' => $fastMovingItems,
            'recentMutations' => $recentMutations,
            'statusControlSections' => $statusControlSections,
            'totalOpenStatus' => $totalOpenStatus,
        ]);
    }

    public function kurirDetail(Request $request)
    {
        $validated = $request->validate([
            'kurir_id' => ['required', 'integer', 'exists:kurirs,id'],
            'date' => ['nullable', 'date'],
        ]);

        $date = Carbon::parse($validated['date'] ?? now())->toDateString();
        $kurir = Kurir::query()->findOrFail((int) $validated['kurir_id'], ['id', 'name']);

        $resis = Resi::query()
            ->with('details:id,resi_id,sku,qty')
            ->where('kurir_id', $kurir->id)
            ->whereDate('tanggal_upload', $date)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'id_pesanan', 'no_resi', 'tanggal_upload', 'status']);

        $scannedResiIds = PackerScanOut::query()
            ->whereIn('resi_id', $resis->pluck('id'))
            ->pluck('resi_id')
            ->flip();

        $data = $resis->map(function ($resi) use ($scannedResiIds) {
            $isCanceled = ($resi->status ?? 'active') === 'canceled';
            $isScanned = $scannedResiIds->has($resi->id);

            if ($isCanceled) {
                $statusKey = 'canceled';
                $statusLabel = 'Dibatalkan';
            } elseif ($isScanned) {
                $statusKey = 'scanned';
                $statusLabel = 'Sudah Scan Out';
            } else {
                $statusKey = 'pending';
                $statusLabel = 'Belum Scan Out';
            }

            $items = $resi->details
                ->map(fn ($detail) => [
                    'sku' => $detail->sku ?: '-',
                    'qty' => (int) $detail->qty,
                ])
                ->values();

            return [
                'id_pesanan' => $resi->id_pesanan ?? '-',
                'no_resi' => $resi->no_resi ?? '-',
                'status_key' => $statusKey,
                'status_label' => $statusLabel,
                'tanggal_upload' => $resi->tanggal_upload
                    ? Carbon::parse($resi->tanggal_upload)->format('Y-m-d')
                    : '-',
                'items' => $items,
                'total_qty' => (int) $items->sum('qty'),
            ];
        })->values();

        $scannedTotal = $data->where('status_key', 'scanned')->count();
        $pendingTotal = $data->where('status_key', 'pending')->count();
        $canceledTotal = $data->where('status_key', 'canceled')->count();

        return response()->json([
            'meta' => [
                'kurir_name' => $kurir->name,
                'date' => $date,
                'total_resi' => $scannedTotal + $pendingTotal,
                'scanned_total' => $scannedTotal,
                'remaining_total' => $pendingTotal,
                'canceled_total' => $canceledTotal,
            ],
            'data' => $data,
        ]);
    }
}
