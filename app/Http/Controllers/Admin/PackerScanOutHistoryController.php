<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackerScanOut;
use Illuminate\Http\Request;

class PackerScanOutHistoryController extends Controller
{
    public function index()
    {
        return view('admin.outbound.packer-scan-outs.index', [
            'dataUrl' => route('admin.outbound.packer-scan-outs.data'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = PackerScanOut::query()
            ->with(['resi', 'scanner'])
            ->orderByDesc('scanned_at');

        $this->applyDateFilter($baseQuery, $request);

        $query = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('scan_code', 'like', "%{$search}%")
                    ->orWhere('scan_type', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('id_pesanan', 'like', "%{$search}%")
                            ->orWhere('no_resi', 'like', "%{$search}%");
                    })
                    ->orWhereHas('scanner', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = (clone $baseQuery)->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $allowedLengths = [10, 20, 50, 100, 500];
        $length = (int) $request->input('length', 10);
        if (!in_array($length, $allowedLengths, true)) {
            $length = 10;
        }
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            return [
                'id' => $row->id,
                'scan_date' => $row->scan_date?->format('Y-m-d') ?? '-',
                'scanned_at' => $row->scanned_at?->format('Y-m-d H:i') ?? '-',
                'scanner' => $row->scanner?->name ?? '-',
                'scan_type' => $row->scan_type ?? '-',
                'scan_code' => $row->scan_code ?? '-',
                'id_pesanan' => $row->resi?->id_pesanan ?? '-',
                'no_resi' => $row->resi?->no_resi ?? '-',
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

        if (!$dateFrom && !$dateTo) {
            $today = now()->toDateString();
            $query->whereDate('scan_date', $today);
            return;
        }

        if ($dateFrom) {
            $query->whereDate('scan_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('scan_date', '<=', $dateTo);
        }
    }
}
