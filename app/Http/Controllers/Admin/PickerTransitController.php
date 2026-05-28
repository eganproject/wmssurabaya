<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\PackerTransitStatusExport;
use App\Exports\PickerTransitStatusExport;
use App\Models\PackerTransitHistory;
use App\Models\PackerScanException;
use App\Models\QcScanResi;
use App\Models\QcTransitItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PickerTransitController extends Controller
{
    public function index()
    {
        return view('admin.inventory.picker-transit.index', [
            'dataUrl' => route('admin.inventory.picker-transit.data'),
            // Backward compatible: some legacy views/scripts expect this variable.
            'dataUrlPacker' => route('admin.inventory.picker-transit.packer-data'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = QcScanResi::query()
            ->with(['scanner', 'resi', 'items.item'])
            ->whereHas('resi', function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->orderBy('scanned_at', 'desc')
            ->orderBy('id', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->whereHas('resi', function ($resiQ) use ($search) {
                    $resiQ->where('no_resi', 'like', "%{$search}%")
                        ->orWhere('id_pesanan', 'like', "%{$search}%");
                })
                    ->orWhereHas('scanner', fn ($scannerQ) => $scannerQ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhereHas('item', fn ($masterQ) => $masterQ->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        $this->applyDateFilter($baseQuery, $request);

        $recordsTotal = (clone $baseQuery)->count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'ongoing' => $this->applyPickerStatusFilter(clone $summaryQuery, 'ongoing')->count(),
            'done' => $this->applyPickerStatusFilter(clone $summaryQuery, 'done')->count(),
        ];

        $query = clone $baseQuery;
        $this->applyPickerStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $requiredQty = (int) $row->items->sum('required_qty');
            $scannedQty = (int) $row->items->sum('scanned_qty');
            $progress = $requiredQty > 0 ? (int) floor(min(100, ($scannedQty / $requiredQty) * 100)) : 0;
            $scanOut = DB::table('packer_scan_outs')
                ->where('resi_id', $row->resi_id)
                ->orderByDesc('scanned_at')
                ->first(['scanned_at']);
            $skuSummary = $row->items->map(function ($item) {
                return sprintf(
                    '%s (%d/%d)',
                    $item->sku,
                    (int) $item->scanned_qty,
                    (int) $item->required_qty
                );
            })->implode(', ');

            return [
                'id' => $row->id,
                'date' => $row->scanned_at?->format('Y-m-d') ?? '-',
                'id_pesanan' => $row->resi?->id_pesanan ?? '-',
                'no_resi' => $row->resi?->no_resi ?? '-',
                'scanner_name' => $row->scanner?->name ?? '-',
                'sku_count' => $row->items->count(),
                'sku_summary' => $skuSummary ?: '-',
                'qc_required_qty' => $requiredQty,
                'qc_scanned_qty' => $scannedQty,
                'qc_progress' => $progress,
                'qc_status' => $row->status,
                'scan_out_status' => $scanOut ? 'done' : 'ongoing',
                'scan_out_at' => $scanOut?->scanned_at ? Carbon::parse($scanOut->scanned_at)->format('Y-m-d H:i') : '-',
                'picked_at' => $row->scanned_at?->format('Y-m-d H:i') ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function pickerDetail(Request $request)
    {
        $validated = $request->validate([
            'qc_resi_id' => ['required', 'integer', 'exists:qc_scan_resis,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $search = strtolower(trim((string) ($validated['q'] ?? '')));
        $qcResi = QcScanResi::with(['scanner', 'resi', 'items.item'])
            ->findOrFail((int) $validated['qc_resi_id']);

        $items = $qcResi->items;
        if ($search !== '') {
            $items = $items->filter(function ($item) use ($search) {
                return str_contains(strtolower((string) $item->sku), $search)
                    || str_contains(strtolower((string) ($item->item?->name ?? '')), $search);
            })->values();
        }

        $mapped = $items->map(function ($row) use ($qcResi) {
            $requiredQty = (int) $row->required_qty;
            $scannedQty = (int) $row->scanned_qty;
            $qcStatus = $scannedQty >= $requiredQty ? 'completed' : 'in_progress';
            $progress = $requiredQty > 0 ? (int) floor(min(100, ($scannedQty / $requiredQty) * 100)) : 0;

            return [
                'sku' => $row->sku,
                'name' => $row->item?->name ?? '-',
                'required_qty' => $requiredQty,
                'scanned_qty' => $scannedQty,
                'progress' => $progress,
                'qc_status' => $qcStatus,
                'scanner_name' => $qcResi->scanner?->name ?? '-',
                'scanned_at' => $qcResi->scanned_at ? Carbon::parse($qcResi->scanned_at)->format('Y-m-d H:i') : '-',
                'completed_at' => $qcResi->completed_at ? Carbon::parse($qcResi->completed_at)->format('Y-m-d H:i') : '-',
            ];
        })->values();

        $qcScannedCount = (int) $mapped->where('qc_status', 'completed')->count();
        $qcInProgressCount = (int) $mapped->where('qc_status', 'in_progress')->count();
        $totalRequiredQty = (int) $mapped->sum('required_qty');
        $totalScannedQty = (int) $mapped->sum('scanned_qty');

        return response()->json([
            'meta' => [
                'date'          => $qcResi->scanned_at?->format('Y-m-d') ?? '-',
                'id_pesanan'    => $qcResi->resi?->id_pesanan ?? '-',
                'no_resi'       => $qcResi->resi?->no_resi ?? '-',
                'qc_status'     => $qcResi->status,
                'total_sku'     => (int) $mapped->count(),
                'qc_scanned'    => $qcScannedCount,
                'qc_in_progress' => $qcInProgressCount,
                'qc_required_qty' => $totalRequiredQty,
                'qc_scanned_qty' => $totalScannedQty,
            ],
            'data' => $mapped,
        ]);
    }

    public function recalculateToday(Request $request)
    {
        if ((auth()->user()->email ?? '') !== 'superadmin@gmail.com') {
            abort(403);
        }

        $date = now()->toDateString();

        DB::beginTransaction();
        try {
            $consumedRows = DB::table('packer_scan_outs as pso')
                ->join('resis as r', 'r.id', '=', 'pso.resi_id')
                ->join('resi_details as rd', 'rd.resi_id', '=', 'r.id')
                ->join('items as i', 'i.sku', '=', 'rd.sku')
                ->where('pso.scan_date', $date)
                ->where(function ($q) {
                    $q->whereNull('r.status')
                        ->orWhere('r.status', '!=', 'canceled');
                })
                ->select([
                    'i.id as item_id',
                    DB::raw('SUM(rd.qty) as qty'),
                ])
                ->groupBy('i.id')
                ->get();

            $consumedByItemId = [];
            foreach ($consumedRows as $row) {
                $itemId = (int) ($row->item_id ?? 0);
                if ($itemId <= 0) {
                    continue;
                }
                $consumedByItemId[$itemId] = (int) ($row->qty ?? 0);
            }

            $pickedRows = QcTransitItem::query()
                ->where('transit_date', $date)
                ->lockForUpdate()
                ->get(['id', 'item_id', 'qty', 'remaining_qty']);

            $updated = 0;
            foreach ($pickedRows as $row) {
                $pickedQty = (int) $row->qty;
                $consumedQty = (int) ($consumedByItemId[(int) $row->item_id] ?? 0);
                $newRemaining = $pickedQty - $consumedQty;
                if ($newRemaining < 0) {
                    $newRemaining = 0;
                }
                if ($newRemaining > $pickedQty) {
                    $newRemaining = $pickedQty;
                }

                if ((int) $row->remaining_qty !== $newRemaining) {
                    $row->remaining_qty = $newRemaining;
                    $row->save();
                    $updated++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghitung ulang transit hari ini.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Recalculate transit hari ini berhasil.',
            'meta' => [
                'date' => $date,
                'updated_rows' => $updated,
            ],
        ]);
    }

    public function auditPickerRemaining(Request $request)
    {
        if ((auth()->user()->email ?? '') !== 'superadmin@gmail.com') {
            abort(403);
        }

        $date = $request->query('date') ?: now()->toDateString();
        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            $date = now()->toDateString();
        }

        $transitRows = DB::table('qc_transit_items as pti')
            ->join('items as i', 'i.id', '=', 'pti.item_id')
            ->where('pti.transit_date', $date)
            ->where('pti.remaining_qty', '>', 0)
            ->select([
                'pti.item_id',
                'i.sku',
                'i.name',
                'pti.qty as picked_qty',
                'pti.remaining_qty',
            ])
            ->orderByDesc('pti.remaining_qty')
            ->limit(300)
            ->get();

        if ($transitRows->isEmpty()) {
            return response()->json([
                'meta' => [
                    'date' => $date,
                    'total' => 0,
                ],
                'data' => [],
            ]);
        }

        $skus = $transitRows->pluck('sku')->filter()->values()->all();

        $exceptionSkus = PackerScanException::query()
            ->whereIn('sku', $skus)
            ->pluck('sku')
            ->map(fn ($sku) => strtolower(trim((string) $sku)))
            ->filter()
            ->values()
            ->all();
        $exceptionLookup = array_flip($exceptionSkus);

        // Qty scan out berdasarkan tanggal upload resi (tanpa peduli kapan scan out dilakukan)
        $scannedOutByUpload = DB::table('resis as r')
            ->join('packer_scan_outs as pso', 'pso.resi_id', '=', 'r.id')
            ->join('resi_details as rd', 'rd.resi_id', '=', 'r.id')
            ->whereDate('r.tanggal_upload', $date)
            ->whereIn('rd.sku', $skus)
            ->where(function ($q) {
                $q->whereNull('r.status')
                    ->orWhere('r.status', '!=', 'canceled');
            })
            ->select([
                'rd.sku',
                DB::raw('SUM(rd.qty) as qty'),
            ])
            ->groupBy('rd.sku')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) ($row->sku ?? '') => (int) ($row->qty ?? 0)])
            ->all();

        // Qty scan out yang benar-benar discan pada tanggal ini
        $scannedOutByScanDate = DB::table('packer_scan_outs as pso')
            ->join('resis as r', 'r.id', '=', 'pso.resi_id')
            ->join('resi_details as rd', 'rd.resi_id', '=', 'r.id')
            ->where('pso.scan_date', $date)
            ->whereIn('rd.sku', $skus)
            ->where(function ($q) {
                $q->whereNull('r.status')
                    ->orWhere('r.status', '!=', 'canceled');
            })
            ->select([
                'rd.sku',
                DB::raw('SUM(rd.qty) as qty'),
            ])
            ->groupBy('rd.sku')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) ($row->sku ?? '') => (int) ($row->qty ?? 0)])
            ->all();

        $data = $transitRows->map(function ($row) use ($scannedOutByUpload, $scannedOutByScanDate, $exceptionLookup) {
            $sku = (string) ($row->sku ?? '');
            $skuKey = strtolower(trim($sku));
            $pickedQty = (int) ($row->picked_qty ?? 0);
            $remainingQty = (int) ($row->remaining_qty ?? 0);
            $scannedOutUploadQty = (int) ($scannedOutByUpload[$sku] ?? 0);
            $scannedOutScanQty = (int) ($scannedOutByScanDate[$sku] ?? 0);
            $isException = $skuKey !== '' && isset($exceptionLookup[$skuKey]);

            $suspect = 'Belum semua qty selesai scan out';
            if ($isException) {
                $suspect = 'SKU ada di exception scan out, sehingga tidak mengurangi QC transit';
            } elseif ($scannedOutUploadQty >= $pickedQty && $remainingQty > 0) {
                $suspect = 'Semua resi QC tanggal ini sudah scan out, tapi remaining masih ada (indikasi mismatch alokasi tanggal/row transit)';
            } elseif ($scannedOutScanQty >= $pickedQty && $remainingQty > 0) {
                $suspect = 'Scan out hari ini sudah cukup, tapi remaining masih ada (indikasi QC transit belum ter-reduce penuh)';
            }

            return [
                'sku' => $sku !== '' ? $sku : '-',
                'name' => (string) ($row->name ?? '-'),
                'picked_qty' => $pickedQty,
                'remaining_qty' => $remainingQty,
                'packed_qty_by_upload_date' => $scannedOutUploadQty,
                'packed_qty_by_scan_date' => $scannedOutScanQty,
                'is_exception' => $isException ? 1 : 0,
                'suspect' => $suspect,
            ];
        })->values();

        return response()->json([
            'meta' => [
                'date' => $date,
                'total' => (int) $data->count(),
            ],
            'data' => $data,
        ]);
    }

    public function dataPacker(Request $request)
    {
        $baseQuery = PackerTransitHistory::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('id_pesanan', 'like', "%{$search}%")
                    ->orWhere('no_resi', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $this->applyPackerDateFilter($baseQuery, $request);

        $recordsTotal = PackerTransitHistory::count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'pending' => (clone $summaryQuery)->where('status', 'menunggu scan out')->count(),
            'done' => (clone $summaryQuery)->where('status', 'selesai')->count(),
        ];

        $query = clone $baseQuery;
        $this->applyPackerStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            return [
                'created_at' => $row->created_at?->format('Y-m-d H:i') ?? '-',
                'id_pesanan' => $row->id_pesanan ?? '-',
                'no_resi' => $row->no_resi ?? '-',
                'status' => $row->status ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function exportPickerStatus(Request $request)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'date' => $request->input('date'),
            'status' => $request->input('status', ''),
        ];

        $date = $filters['date'] ?: now()->toDateString();
        $suffix = $filters['status'] ?: 'all';
        $filename = "qc-transit-{$date}-{$suffix}.xlsx";

        return Excel::download(new PickerTransitStatusExport($filters), $filename);
    }

    public function exportPackerStatus(Request $request)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'date' => $request->input('date'),
            'status' => $request->input('status', ''),
        ];

        $date = $filters['date'] ?: now()->toDateString();
        $suffix = $filters['status'] ?: 'all';
        $filename = "packer-transit-{$date}-{$suffix}.xlsx";

        return Excel::download(new PackerTransitStatusExport($filters), $filename);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->whereDate('scanned_at', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function qcLedgerStatsForSkuDate(string $sku, string $date): array
    {
        $sku = trim($sku);
        if ($sku === '' || $date === '') {
            return [
                'required_qty' => 0,
                'scanned_qty' => 0,
                'resi_count' => 0,
                'completed_resi_count' => 0,
                'in_progress_resi_count' => 0,
                'progress' => 0,
            ];
        }

        $rows = DB::table('qc_scan_resi_items as qsri')
            ->join('qc_scan_resis as qsr', 'qsr.id', '=', 'qsri.qc_scan_resi_id')
            ->join('resis as r', 'r.id', '=', 'qsr.resi_id')
            ->where('qsri.sku', $sku)
            ->whereDate('r.tanggal_upload', $date)
            ->where(function ($q) {
                $q->whereNull('r.status')
                    ->orWhere('r.status', '!=', 'canceled');
            })
            ->select([
                'qsr.resi_id',
                'qsr.status',
                'qsri.required_qty',
                'qsri.scanned_qty',
            ])
            ->get();

        $requiredQty = (int) $rows->sum('required_qty');
        $scannedQty = (int) $rows->sum('scanned_qty');
        $resiCount = (int) $rows->pluck('resi_id')->unique()->count();
        $completedResiCount = (int) $rows->where('status', 'completed')->pluck('resi_id')->unique()->count();
        $inProgressResiCount = max(0, $resiCount - $completedResiCount);
        $progress = $requiredQty > 0 ? (int) floor(min(100, ($scannedQty / $requiredQty) * 100)) : 0;

        return [
            'required_qty' => $requiredQty,
            'scanned_qty' => $scannedQty,
            'resi_count' => $resiCount,
            'completed_resi_count' => $completedResiCount,
            'in_progress_resi_count' => $inProgressResiCount,
            'progress' => $progress,
        ];
    }

    private function applyPackerDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->whereDate('created_at', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function applyPickerStatusFilter($query, Request|string $request): mixed
    {
        $status = $request instanceof Request ? (string) $request->input('status', '') : $request;
        if ($status === 'ongoing') {
            $query->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('packer_scan_outs as pso')
                    ->whereColumn('pso.resi_id', 'qc_scan_resis.resi_id');
            });
        } elseif ($status === 'done') {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('packer_scan_outs as pso')
                    ->whereColumn('pso.resi_id', 'qc_scan_resis.resi_id');
            });
        }

        return $query;
    }

    private function applyPackerStatusFilter($query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status === 'pending') {
            $query->where('status', 'menunggu scan out');
        } elseif ($status === 'done') {
            $query->where('status', 'selesai');
        }
    }
}
