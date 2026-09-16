<?php

namespace App\Http\Controllers\Admin;

use App\Exports\QcPerformanceReportExport;
use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\QcScanResi;
use App\Models\User;
use App\Support\QcPerformanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

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
            'exportUrl' => route('admin.outbound.picker-reports.export'),
            'divisis' => $divisis,
            'today' => now()->toDateString(),
            'generatedBy' => $authUser?->name ?? '-',
        ]);
    }

    public function data(Request $request)
    {
        $authUser = $request->user();
        $filters = $this->filters($request);
        $dailyRows = QcPerformanceReport::dailyRows($filters, $authUser);
        $hourlyRows = QcPerformanceReport::hourlyRows($filters, $authUser);
        $dailyRows = QcPerformanceReport::enrichDailyRows($dailyRows, $hourlyRows);
        $summary = QcPerformanceReport::summary($dailyRows, $hourlyRows);
        $performers = QcPerformanceReport::perUserRows($dailyRows);

        $recordsTotal = QcPerformanceReport::dailyGroupCount($authUser);
        $recordsFiltered = $dailyRows->count();

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $rows = $length > 0 ? $dailyRows->slice($start, $length)->values() : $dailyRows;

        $data = $rows->map(function ($row) {
            $firstScan = $row->first_scan_at ? Carbon::parse($row->first_scan_at)->format('H:i') : '';
            $lastScan = $row->last_scan_at ? Carbon::parse($row->last_scan_at)->format('H:i') : '';
            $range = ($firstScan !== '' && $lastScan !== '') ? "{$firstScan} - {$lastScan}" : '-';

            $totalResi = (int) $row->total_resi;
            $completed = (int) $row->completed_resi;
            $pending = (int) $row->pending_resi;
            return [
                'date'           => $row->report_date,
                'user_id'        => (int) $row->user_id,
                'petugas'        => $row->petugas ?? '-',
                'total_resi'     => $totalResi,
                'completed'      => $completed,
                'pending'        => $pending,
                'completion_pct' => (float) $row->completion_pct,
                'scan_pct'       => (float) $row->scan_pct,
                'sku_lines'      => (int) $row->sku_lines,
                'required_qty'   => (int) $row->required_qty,
                'scanned_qty'    => (int) $row->scanned_qty,
                'active_hours'   => (int) $row->active_hours,
                'resi_per_hour'  => (float) $row->resi_per_hour,
                'qty_per_hour'   => (float) $row->qty_per_hour,
                'peak_hour'      => $row->peak_hour ?: '-',
                'peak_hour_resi' => (int) $row->peak_hour_resi,
                'avg_cycle_minutes' => $row->avg_cycle_minutes,
                'range'          => $range,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'summary' => $summary,
            'performers' => $performers,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        $authUser = $request->user();
        $dailyRows = QcPerformanceReport::dailyRows($filters, $authUser);
        $hourlyRows = QcPerformanceReport::hourlyRows($filters, $authUser);
        $dailyRows = QcPerformanceReport::enrichDailyRows($dailyRows, $hourlyRows);
        $detailRows = QcPerformanceReport::detailRows($filters, $authUser);
        $summary = QcPerformanceReport::summary($dailyRows, $hourlyRows);
        $perUserRows = QcPerformanceReport::perUserRows($dailyRows);

        $from = $filters['date_from'] ?? 'awal';
        $to = $filters['date_to'] ?? 'akhir';

        return Excel::download(
            new QcPerformanceReportExport($dailyRows, $hourlyRows, $perUserRows, $detailRows, $summary, $filters, $authUser?->name),
            "laporan-performa-qc-{$from}-{$to}.xlsx",
        );
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
        $hourly = $qcResis->groupBy(fn ($qc) => $qc->scanned_at?->format('H:00') ?? '-')
            ->map(function ($rows, $hour) {
                $completedRows = $rows->where('status', 'completed');
                $durations = $completedRows->filter(fn ($qc) => $qc->completed_at && $qc->scanned_at && $qc->completed_at->gte($qc->scanned_at))
                    ->map(fn ($qc) => round($qc->scanned_at->diffInSeconds($qc->completed_at) / 60, 1));

                return [
                    'hour' => $hour,
                    'total_resi' => $rows->count(),
                    'completed' => $completedRows->count(),
                    'pending' => $rows->count() - $completedRows->count(),
                    'sku_lines' => (int) $rows->sum(fn ($qc) => $qc->items->count()),
                    'scanned_qty' => (int) $rows->sum(fn ($qc) => $qc->items->sum('scanned_qty')),
                    'completion_pct' => $rows->count() > 0 ? round($completedRows->count() / $rows->count() * 100, 1) : 0,
                    'avg_cycle_minutes' => $durations->isNotEmpty() ? round($durations->avg(), 1) : null,
                ];
            })->sortKeys()->values();

        return response()->json([
            'date'         => $date,
            'petugas'      => User::where('id', $userId)->value('name') ?? '-',
            'total_resi'   => $totalResi,
            'completed'    => $completed,
            'pending'      => max(0, $totalResi - $completed),
            'total_sku'    => (int) $resis->sum('sku_count'),
            'required_qty' => (int) $resis->sum('required_qty'),
            'scanned_qty'  => (int) $resis->sum('scanned_qty'),
            'active_hours' => $hourly->count(),
            'resi_per_hour' => $hourly->count() > 0 ? round($totalResi / $hourly->count(), 2) : 0,
            'hourly' => $hourly,
            'resis'        => $resis,
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'divisi_id' => ['nullable', 'integer', 'exists:divisis,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);
    }
}
