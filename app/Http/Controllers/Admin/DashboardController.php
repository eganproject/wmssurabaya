<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurir;
use App\Models\PackerScanOut;
use App\Models\QcScanResi;
use App\Models\Resi;
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
