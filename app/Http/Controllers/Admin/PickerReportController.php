<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\QcScanResi;
use App\Models\QcScanResiItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PickerReportController extends Controller
{
    public function index()
    {
        $authUser = request()->user();
        $divisiQuery = Divisi::orderBy('name');
        if ($authUser && $authUser->divisi_id !== null && (int) $authUser->divisi_id !== 1) {
            $divisiQuery->where('id', $authUser->divisi_id);
        }
        $divisis = $divisiQuery->get(['id', 'name']);

        return view('admin.outbound.picker-reports.index', [
            'dataUrl' => route('admin.outbound.picker-reports.data'),
            'divisis' => $divisis,
            'today' => now()->toDateString(),
            'generatedBy' => $authUser?->name ?? '-',
        ]);
    }

    public function data(Request $request)
    {
        $authUser = $request->user();
        $baseQuery = $this->buildReportQuery($request, $authUser, false);
        $query = $this->buildReportQuery($request, $authUser, true);

        $recordsTotal = DB::query()->fromSub($baseQuery, 't')->count();
        $recordsFiltered = DB::query()->fromSub($query, 't')->count();

        $summaryRow = DB::query()->fromSub($query, 't')
            ->selectRaw('COUNT(DISTINCT t.user_id) as petugas_count')
            ->selectRaw('COUNT(DISTINCT t.report_date) as day_count')
            ->selectRaw('COALESCE(SUM(t.total_resi), 0) as resi_total')
            ->selectRaw('COALESCE(SUM(t.completed_resi), 0) as completed_total')
            ->selectRaw('COALESCE(SUM(t.scanned_qty), 0) as qty_total')
            ->first();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $rows = $query->get();

        $data = $rows->map(function ($row) {
            $firstScan = $row->first_scan_at ? Carbon::parse($row->first_scan_at)->format('H:i') : '';
            $lastScan = $row->last_scan_at ? Carbon::parse($row->last_scan_at)->format('H:i') : '';
            $range = ($firstScan !== '' && $lastScan !== '') ? "{$firstScan} - {$lastScan}" : '-';

            $totalResi = (int) $row->total_resi;
            $completed = (int) $row->completed_resi;
            $pending = (int) $row->pending_resi;
            $completionPct = $totalResi > 0 ? (int) round($completed / $totalResi * 100) : 0;

            return [
                'date'           => $row->report_date,
                'user_id'        => (int) $row->user_id,
                'petugas'        => $row->petugas ?? '-',
                'total_resi'     => $totalResi,
                'completed'      => $completed,
                'pending'        => $pending,
                'completion_pct' => $completionPct,
                'sku_lines'      => (int) $row->sku_lines,
                'required_qty'   => (int) $row->required_qty,
                'scanned_qty'    => (int) $row->scanned_qty,
                'range'          => $range,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'summary' => [
                'petugas_count'   => (int) ($summaryRow->petugas_count ?? 0),
                'day_count'       => (int) ($summaryRow->day_count ?? 0),
                'resi_total'      => (int) ($summaryRow->resi_total ?? 0),
                'completed_total' => (int) ($summaryRow->completed_total ?? 0),
                'qty_total'       => (int) ($summaryRow->qty_total ?? 0),
            ],
        ]);
    }

    public function detail(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $userId = (int) $validated['user_id'];

        $authUser = $request->user();
        if ($authUser && $authUser->divisi_id !== null && (int) $authUser->divisi_id !== 1) {
            $targetUser = User::find($userId);
            if (!$targetUser || (int) $targetUser->divisi_id !== (int) $authUser->divisi_id) {
                return response()->json(['message' => 'Tidak diizinkan'], 403);
            }
        }

        $qcResis = QcScanResi::query()
            ->with([
                'resi:id,no_resi,id_pesanan',
                'items:id,qc_scan_resi_id,item_id,sku,required_qty,scanned_qty',
                'items.item:id,name',
            ])
            ->where('scanned_by', $userId)
            ->whereDate('scanned_at', $date)
            ->orderBy('scanned_at')
            ->get();

        $resis = $qcResis->map(function ($qc) {
            $items = $qc->items->map(fn ($it) => [
                'sku' => $it->sku,
                'name' => $it->item?->name ?? '-',
                'required_qty' => (int) $it->required_qty,
                'scanned_qty' => (int) $it->scanned_qty,
            ])->values();

            return [
                'no_resi'      => $qc->resi?->no_resi ?? '-',
                'id_pesanan'   => $qc->resi?->id_pesanan ?? '-',
                'status'       => $qc->status,
                'scanned_at'   => $qc->scanned_at ? Carbon::parse($qc->scanned_at)->format('H:i') : '-',
                'completed_at' => $qc->completed_at ? Carbon::parse($qc->completed_at)->format('H:i') : '-',
                'sku_count'    => $items->count(),
                'required_qty' => (int) $items->sum('required_qty'),
                'scanned_qty'  => (int) $items->sum('scanned_qty'),
                'items'        => $items,
            ];
        })->values();

        $totalResi = $resis->count();
        $completed = $qcResis->where('status', 'completed')->count();

        return response()->json([
            'date'         => $date,
            'petugas'      => User::where('id', $userId)->value('name') ?? '-',
            'total_resi'   => $totalResi,
            'completed'    => $completed,
            'pending'      => max(0, $totalResi - $completed),
            'total_sku'    => (int) $resis->sum('sku_count'),
            'required_qty' => (int) $resis->sum('required_qty'),
            'scanned_qty'  => (int) $resis->sum('scanned_qty'),
            'resis'        => $resis,
        ]);
    }

    private function buildReportQuery(Request $request, $authUser, bool $applyFilters)
    {
        $resiAgg = QcScanResi::query()
            ->selectRaw('DATE(qc_scan_resis.scanned_at) as report_date')
            ->selectRaw('qc_scan_resis.scanned_by as user_id')
            ->selectRaw('COUNT(*) as total_resi')
            ->selectRaw("SUM(CASE WHEN qc_scan_resis.status = 'completed' THEN 1 ELSE 0 END) as completed_resi")
            ->selectRaw("SUM(CASE WHEN qc_scan_resis.status <> 'completed' THEN 1 ELSE 0 END) as pending_resi")
            ->selectRaw('MIN(qc_scan_resis.scanned_at) as first_scan_at')
            ->selectRaw('MAX(qc_scan_resis.scanned_at) as last_scan_at')
            ->whereNotNull('qc_scan_resis.scanned_at')
            ->whereNotNull('qc_scan_resis.scanned_by')
            ->groupByRaw('DATE(qc_scan_resis.scanned_at)')
            ->groupBy('qc_scan_resis.scanned_by');

        $itemAgg = QcScanResiItem::query()
            ->join('qc_scan_resis', 'qc_scan_resis.id', '=', 'qc_scan_resi_items.qc_scan_resi_id')
            ->selectRaw('DATE(qc_scan_resis.scanned_at) as report_date')
            ->selectRaw('qc_scan_resis.scanned_by as user_id')
            ->selectRaw('COUNT(*) as sku_lines')
            ->selectRaw('COALESCE(SUM(qc_scan_resi_items.required_qty), 0) as required_qty')
            ->selectRaw('COALESCE(SUM(qc_scan_resi_items.scanned_qty), 0) as scanned_qty')
            ->whereNotNull('qc_scan_resis.scanned_at')
            ->whereNotNull('qc_scan_resis.scanned_by')
            ->groupByRaw('DATE(qc_scan_resis.scanned_at)')
            ->groupBy('qc_scan_resis.scanned_by');

        $query = DB::query()
            ->fromSub($resiAgg, 'r')
            ->join('users', 'users.id', '=', 'r.user_id')
            ->leftJoinSub($itemAgg, 'i', function ($join) {
                $join->on('i.report_date', '=', 'r.report_date')
                    ->on('i.user_id', '=', 'r.user_id');
            })
            ->selectRaw('r.report_date, r.user_id, users.name as petugas')
            ->selectRaw('r.total_resi, r.completed_resi, r.pending_resi')
            ->selectRaw('r.first_scan_at, r.last_scan_at')
            ->selectRaw('COALESCE(i.sku_lines, 0) as sku_lines')
            ->selectRaw('COALESCE(i.required_qty, 0) as required_qty')
            ->selectRaw('COALESCE(i.scanned_qty, 0) as scanned_qty')
            ->orderByRaw('r.report_date desc')
            ->orderBy('users.name');

        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $query->where('users.divisi_id', $divisiId);
            }
        }

        if ($applyFilters) {
            $search = trim((string) $request->input('q', ''));
            if ($search !== '') {
                $query->where('users.name', 'like', "%{$search}%");
            }
            $divisiId = $request->integer('divisi_id');
            if ($divisiId) {
                $query->where('users.divisi_id', $divisiId);
            }
            $this->applyDateFilter($query, $request);
        }

        return $query;
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $query->where('r.report_date', '>=', Carbon::parse($dateFrom)->toDateString());
            }
            if ($dateTo) {
                $query->where('r.report_date', '<=', Carbon::parse($dateTo)->toDateString());
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }
}
