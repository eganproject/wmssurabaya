<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\StockOpnameReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class StockOpnameReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.stock-opname.index', [
            'dataUrl' => route('admin.reports.stock-opname.data'),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = DB::table('stock_opnames as so')
            ->join('stock_opname_items as soi', 'soi.stock_opname_id', '=', 'so.id')
            ->where('so.status', 'completed');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->whereRaw('DATE(so.transacted_at) like ?', ["%{$search}%"]);
        }

        $this->applyDateFilter($baseQuery, $request);

        $recordsTotal = DB::table('stock_opnames as so')
            ->join('stock_opname_items as soi', 'soi.stock_opname_id', '=', 'so.id')
            ->where('so.status', 'completed')
            ->selectRaw('COUNT(DISTINCT DATE(so.transacted_at)) as total_days')
            ->value('total_days') ?? 0;

        $recordsFiltered = (clone $baseQuery)
            ->selectRaw('COUNT(DISTINCT DATE(so.transacted_at)) as total_days')
            ->value('total_days') ?? 0;

        $query = (clone $baseQuery)
            ->selectRaw('DATE(so.transacted_at) as report_date')
            ->selectRaw('COUNT(DISTINCT so.id) as batch_count')
            ->selectRaw('COUNT(DISTINCT soi.item_id) as sku_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN soi.adjustment <> 0 THEN soi.item_id END) as diff_sku_count')
            ->groupBy('report_date')
            ->orderBy('report_date', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $skuCount = (int) $row->sku_count;
            $diffCount = (int) $row->diff_sku_count;
            $accuracy = $skuCount > 0 ? (($skuCount - $diffCount) / $skuCount) * 100 : 100;
            return [
                'date' => $row->report_date,
                'batch_count' => (int) $row->batch_count,
                'sku_count' => $skuCount,
                'diff_sku_count' => $diffCount,
                'accuracy' => round($accuracy, 2),
            ];
        });

        $summaryRow = (clone $baseQuery)
            ->selectRaw('COUNT(DISTINCT DATE(so.transacted_at)) as total_days')
            ->selectRaw('COUNT(DISTINCT so.id) as total_batches')
            ->selectRaw('COUNT(DISTINCT soi.item_id) as total_sku')
            ->selectRaw('COUNT(DISTINCT CASE WHEN soi.adjustment <> 0 THEN soi.item_id END) as total_diff_sku')
            ->first();

        $totalSku = (int) ($summaryRow->total_sku ?? 0);
        $totalDiff = (int) ($summaryRow->total_diff_sku ?? 0);
        $summaryAccuracy = $totalSku > 0 ? (($totalSku - $totalDiff) / $totalSku) * 100 : 100;

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'summary' => [
                'total_days' => (int) ($summaryRow->total_days ?? 0),
                'total_batches' => (int) ($summaryRow->total_batches ?? 0),
                'total_sku' => $totalSku,
                'total_diff_sku' => $totalDiff,
                'accuracy' => round($summaryAccuracy, 2),
            ],
        ]);
    }

    public function diffSku(Request $request)
    {
        $type = $request->input('type', 'plus');
        $isMinus = $type === 'minus';

        $baseQuery = DB::table('stock_opname_items as soi')
            ->join('stock_opnames as so', 'so.id', '=', 'soi.stock_opname_id')
            ->join('items as i', 'i.id', '=', 'soi.item_id')
            ->where('so.status', 'completed');

        if ($isMinus) {
            $baseQuery->where('soi.adjustment', '<', 0);
        } else {
            $baseQuery->where('soi.adjustment', '>', 0);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%");
            });
        }

        $this->applyDateFilter($baseQuery, $request);

        $recordsTotal = DB::table('stock_opname_items as soi')
            ->join('stock_opnames as so', 'so.id', '=', 'soi.stock_opname_id')
            ->where('so.status', 'completed')
            ->when($isMinus, fn ($q) => $q->where('soi.adjustment', '<', 0), fn ($q) => $q->where('soi.adjustment', '>', 0))
            ->selectRaw('COUNT(DISTINCT soi.item_id) as total_sku')
            ->value('total_sku') ?? 0;

        $recordsFiltered = (clone $baseQuery)
            ->selectRaw('COUNT(DISTINCT soi.item_id) as total_sku')
            ->value('total_sku') ?? 0;

        $query = (clone $baseQuery)
            ->select('i.sku', 'i.name')
            ->selectRaw('SUM(soi.adjustment) as total_adjustment')
            ->groupBy('i.id', 'i.sku', 'i.name')
            ->orderByRaw('ABS(SUM(soi.adjustment)) DESC');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) use ($isMinus) {
            $qty = (int) $row->total_adjustment;
            return [
                'sku' => $row->sku ?? '-',
                'name' => $row->name ?? '-',
                'qty' => $isMinus ? abs($qty) : $qty,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];
        $filename = 'laporan-stock-opname-'.now()->format('YmdHis').'.xlsx';

        return Excel::download(new StockOpnameReportExport($filters), $filename);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('so.transacted_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('so.transacted_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }
}
