<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QcScanResi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QcScanHistoryController extends Controller
{
    public function index()
    {
        $authUser = request()->user();
        $userQuery = User::orderBy('name');
        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $userQuery->where('divisi_id', $divisiId);
            }
        }
        $users = $userQuery->get(['id', 'name']);

        return view('admin.outbound.qc-scan-history.index', [
            'dataUrl' => route('admin.outbound.qc-scan-history.data'),
            'users' => $users,
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $authUser = $request->user();

        $baseQuery = QcScanResi::query()
            ->with(['scanner', 'resi', 'items.item'])
            ->orderBy('scanned_at', 'desc')
            ->orderBy('id', 'desc');

        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $baseQuery->whereHas('scanner', function ($q) use ($divisiId) {
                    $q->where('divisi_id', $divisiId);
                });
            }
        }

        $query = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('scanner', function ($userQ) use ($search) {
                    $userQ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('no_resi', 'like', "%{$search}%")
                            ->orWhere('id_pesanan', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%");
                    });
            });
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('scanned_by', $userId);
        }

        $status = $request->input('status');
        if (in_array($status, ['in_progress', 'completed'], true)) {
            $query->where('status', $status);
        }

        $this->applyDateFilter($query, $request);

        $recordsTotal = (clone $baseQuery)->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $ledgerItems = $row->items ?? collect();
            $requiredQty = (int) $ledgerItems->sum('required_qty');
            $scannedQty = (int) $ledgerItems->sum('scanned_qty');
            $qcProgress = $requiredQty > 0 ? (int) floor(min(100, ($scannedQty / $requiredQty) * 100)) : 0;
            $items = $ledgerItems->map(fn ($it) => [
                'sku' => $it->sku,
                'name' => $it->item?->name ?? '-',
                'required_qty' => (int) $it->required_qty,
                'scanned_qty' => (int) $it->scanned_qty,
                'status' => (int) $it->scanned_qty >= (int) $it->required_qty ? 'completed' : 'in_progress',
            ])->values();
            $labels = $items->map(fn ($item) => sprintf('%s (%d/%d)', $item['sku'], $item['scanned_qty'], $item['required_qty']))->values();

            return [
                'id'           => $row->id,
                'no_resi'      => $row->resi?->no_resi ?? '-',
                'id_pesanan'   => $row->resi?->id_pesanan ?? '-',
                'picker'       => $row->scanner?->name ?? '-',
                'status'       => $row->status,
                'scanned_at'   => $row->scanned_at ? Carbon::parse($row->scanned_at)->format('Y-m-d H:i') : '',
                'completed_at' => $row->completed_at ? Carbon::parse($row->completed_at)->format('Y-m-d H:i') : '',
                'item'         => $labels->implode(', ') ?: '-',
                'qty'          => $scannedQty,
                'items'        => $items,
                'required_qty' => $requiredQty,
                'scanned_qty' => $scannedQty,
                'qc_progress' => $qcProgress,
                'items_detail' => [],
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        DB::beginTransaction();
        try {
            $qcResi = QcScanResi::where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            $authUser = $request->user();
            if ($authUser && $authUser->divisi_id !== null && (int) $authUser->divisi_id !== 1) {
                $qcResi->loadMissing('scanner:id,divisi_id');
                if ((int) $qcResi->scanner?->divisi_id !== (int) $authUser->divisi_id) {
                    DB::rollBack();
                    return response()->json(['message' => 'Tidak diizinkan'], 403);
                }
            }

            if ($qcResi->status !== 'in_progress') {
                DB::rollBack();
                return response()->json(['message' => 'Resi QC sudah selesai dan tidak bisa dihapus'], 422);
            }

            $qcResi->load('items');
            $hasScannedQty = $qcResi->items->sum('scanned_qty') > 0;
            if ($hasScannedQty) {
                DB::rollBack();
                return response()->json(['message' => 'Resi sudah berisi scan SKU, tidak bisa dihapus'], 422);
            }

            $qcResi->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus resi QC',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resi QC berhasil dihapus',
        ]);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('scanned_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('scanned_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

}
