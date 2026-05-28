<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackerResiScan;
use App\Models\Resi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PackerPackingReportController extends Controller
{
    public function index()
    {
        $packerIds = PackerResiScan::query()
            ->whereNotNull('scanned_by')
            ->distinct()
            ->pluck('scanned_by');

        $packers = User::query()
            ->whereIn('id', $packerIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.outbound.packer-packing-reports.index', [
            'dataUrl' => route('admin.reports.packer-packing-reports.data'),
            'packers' => $packers,
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $query = DB::table('packer_resi_scans as ps')
            ->join('users as u', 'u.id', '=', 'ps.scanned_by')
            ->selectRaw('ps.scan_date, ps.scanned_by, u.name as packer_name, COUNT(*) as total_scan, COUNT(DISTINCT ps.resi_id) as unique_scan, MIN(ps.scanned_at) as first_scan, MAX(ps.scanned_at) as last_scan')
            ->groupBy('ps.scan_date', 'ps.scanned_by', 'u.name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                    ->orWhere('ps.scan_date', 'like', "%{$search}%");
            });
        }

        $packerId = $request->input('packer_id');
        if ($packerId) {
            $query->where('ps.scanned_by', $packerId);
        }

        $dateFrom = $this->parseDate($request->input('date_from'));
        $dateTo = $this->parseDate($request->input('date_to'));
        if ($dateFrom) {
            $query->where('ps.scan_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('ps.scan_date', '<=', $dateTo);
        }

        $rows = $query
            ->orderByDesc('ps.scan_date')
            ->orderBy('packer_name')
            ->limit(500)
            ->get();

        $data = $rows->map(function ($row) {
            $first = $row->first_scan ? Carbon::parse($row->first_scan) : null;
            $last = $row->last_scan ? Carbon::parse($row->last_scan) : null;
            $durationHours = null;
            if ($first && $last) {
                $minutes = max(0, $first->diffInMinutes($last));
                $durationHours = $minutes > 0 ? $minutes / 60 : null;
            }
            $avgPerHour = $durationHours && $durationHours > 0
                ? round($row->total_scan / $durationHours, 2)
                : (int) $row->total_scan;

            return [
                'date' => Carbon::parse($row->scan_date)->format('Y-m-d'),
                'packer_id' => (int) $row->scanned_by,
                'packer' => $row->packer_name ?? '-',
                'total_scan' => (int) $row->total_scan,
                'unique_scan' => (int) $row->unique_scan,
                'avg_per_hour' => $avgPerHour,
                'first_scan' => $first?->format('H:i') ?? '-',
                'last_scan' => $last?->format('H:i') ?? '-',
            ];
        });

        $comparison = $this->buildComparisonData($dateFrom, $dateTo);

        return response()->json([
            'data' => $data,
            'comparison' => $comparison,
        ]);
    }

    public function detail(Request $request)
    {
        $validated = $request->validate([
            'packer_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
        ]);

        $rows = PackerResiScan::query()
            ->with(['resi', 'scanner'])
            ->where('scanned_by', (int) $validated['packer_id'])
            ->whereDate('scan_date', $validated['date'])
            ->orderByDesc('scanned_at')
            ->limit(500)
            ->get();

        $data = $rows->map(function ($row) {
            return [
                'id_pesanan' => $row->resi?->id_pesanan ?? '-',
                'no_resi' => $row->resi?->no_resi ?? '-',
                'scan_type' => $row->scan_type ?? '-',
                'scan_code' => $row->scan_code ?? '-',
                'scanned_at' => $row->scanned_at
                    ? Carbon::parse($row->scanned_at)->format('Y-m-d H:i')
                    : '-',
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function searchResi(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:100'],
        ]);

        $keyword = trim($validated['q']);
        if ($keyword === '') {
            return response()->json([
                'message' => 'Nomor resi tidak boleh kosong.',
            ], 422);
        }

        $scan = PackerResiScan::query()
            ->with(['resi', 'scanner'])
            ->whereHas('resi', function ($q) use ($keyword) {
                $q->where('no_resi', $keyword)
                    ->orWhere('id_pesanan', $keyword);
            })
            ->orderByDesc('scanned_at')
            ->first();

        if (!$scan) {
            return response()->json([
                'message' => 'Resi belum terpacking atau tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'packer' => $scan->scanner?->name ?? '-',
                'scan_date' => $scan->scan_date
                    ? Carbon::parse($scan->scan_date)->format('Y-m-d')
                    : '-',
                'scanned_at' => $scan->scanned_at
                    ? Carbon::parse($scan->scanned_at)->format('Y-m-d H:i')
                    : '-',
                'id_pesanan' => $scan->resi?->id_pesanan ?? '-',
                'no_resi' => $scan->resi?->no_resi ?? '-',
            ],
        ]);
    }

    private function parseDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildComparisonData(?string $dateFrom, ?string $dateTo): array
    {
        $resiQuery = Resi::query();
        if ($dateFrom) {
            $resiQuery->whereDate('tanggal_upload', '>=', $dateFrom);
        }
        if ($dateTo) {
            $resiQuery->whereDate('tanggal_upload', '<=', $dateTo);
        }

        $importTotal = (clone $resiQuery)->count();

        $packedTotal = 0;
        if ($importTotal > 0) {
            $packedTotal = PackerResiScan::query()
                ->whereIn('resi_id', (clone $resiQuery)->select('id'))
                ->distinct('resi_id')
                ->count('resi_id');
        }

        $missingBase = (clone $resiQuery)
            ->leftJoin('packer_resi_scans as ps', 'ps.resi_id', '=', 'resis.id')
            ->whereNull('ps.id')
            ->select('resis.id_pesanan', 'resis.no_resi', 'resis.tanggal_upload');

        $missingTotal = (clone $missingBase)->count();
        $missingSamples = (clone $missingBase)
            ->orderByDesc('resis.tanggal_upload')
            ->limit(50)
            ->get()
            ->map(function ($row) {
                return [
                    'id_pesanan' => (string) ($row->id_pesanan ?? '-'),
                    'no_resi' => (string) ($row->no_resi ?? '-'),
                    'tanggal_upload' => $row->tanggal_upload
                        ? Carbon::parse($row->tanggal_upload)->format('Y-m-d')
                        : '-',
                ];
            });

        return [
            'import_total' => $importTotal,
            'scanned_total' => $packedTotal,
            'missing_total' => $missingTotal,
            'missing_samples' => $missingSamples,
        ];
    }
}
