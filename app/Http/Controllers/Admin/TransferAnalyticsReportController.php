<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransferAnalyticsReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.transfer-analytics.index', [
            'dataUrl' => route('admin.reports.transfer-analytics.data'),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request)
    {
        $base = $this->baseQuery($request);
        $recordsTotal = DB::table('stock_transfer_items')->count();
        $recordsFiltered = (clone $base)->count();

        $summary = $this->summary($request);
        $analytics = [
            'trend' => $this->trend($request),
            'top_items' => $this->topItems($request),
            'routes' => $this->routePerformance($request),
            'oldest_transit' => $this->oldestTransit($request),
        ];

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $query = (clone $base)
            ->select([
                'st.id',
                'st.code',
                'st.status',
                'st.transacted_at',
                'st.shipped_at',
                'st.received_at',
                'st.discrepancy_note',
                'sw.name as source_warehouse',
                'dw.name as destination_warehouse',
                'i.sku',
                'i.name as item_name',
                'c.name as category_name',
                'sti.qty_input',
                'sti.qty_base',
                'sti.qty_received_unit',
                'sti.qty_received_base',
                'sti.qty_discrepancy_base',
                'sti.discrepancy_note as item_discrepancy_note',
                'send_unit.name as sent_unit',
                'receive_unit.name as received_unit',
                'creator.name as creator_name',
                'shipper.name as shipper_name',
                'receiver.name as receiver_name',
            ])
            ->orderByDesc('st.transacted_at')
            ->orderByDesc('st.id')
            ->orderBy('i.sku');

        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $sent = (int) $row->qty_base;
            $received = (int) $row->qty_received_base;
            $discrepancy = (int) $row->qty_discrepancy_base;
            $fillRate = $sent > 0 && in_array($row->status, ['received', 'received_with_discrepancy'], true)
                ? ($received / $sent) * 100
                : null;

            return [
                'id' => (int) $row->id,
                'code' => $row->code,
                'status' => $row->status,
                'transacted_at' => $this->formatDateTime($row->transacted_at),
                'shipped_at' => $this->formatDateTime($row->shipped_at),
                'received_at' => $this->formatDateTime($row->received_at),
                'route' => $row->source_warehouse.' -> '.$row->destination_warehouse,
                'source_warehouse' => $row->source_warehouse,
                'destination_warehouse' => $row->destination_warehouse,
                'sku' => $row->sku,
                'item_name' => $row->item_name,
                'category' => $row->category_name ?: 'Tanpa Kategori',
                'sent_input' => (int) $row->qty_input,
                'sent_unit' => $row->sent_unit ?: 'UNIT',
                'sent_base' => $sent,
                'received_input' => (int) $row->qty_received_unit,
                'received_unit' => $row->received_unit ?: '-',
                'received_base' => $received,
                'discrepancy_base' => $discrepancy,
                'fill_rate' => $fillRate === null ? null : round($fillRate, 2),
                'lead_time_hours' => $this->hoursBetween($row->shipped_at, $row->received_at),
                'discrepancy_note' => $row->item_discrepancy_note ?: $row->discrepancy_note ?: '-',
                'creator' => $row->creator_name ?: '-',
                'shipper' => $row->shipper_name ?: '-',
                'receiver' => $row->receiver_name ?: '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'analytics' => $analytics,
            'data' => $data,
        ]);
    }

    private function baseQuery(Request $request): Builder
    {
        $query = DB::table('stock_transfer_items as sti')
            ->join('stock_transfers as st', 'st.id', '=', 'sti.stock_transfer_id')
            ->join('warehouses as sw', 'sw.id', '=', 'st.source_warehouse_id')
            ->join('warehouses as dw', 'dw.id', '=', 'st.destination_warehouse_id')
            ->join('items as i', 'i.id', '=', 'sti.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('item_units as send_unit', 'send_unit.id', '=', 'sti.unit_id')
            ->leftJoin('item_units as receive_unit', 'receive_unit.id', '=', 'sti.received_unit_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'st.created_by')
            ->leftJoin('users as shipper', 'shipper.id', '=', 'st.shipped_by')
            ->leftJoin('users as receiver', 'receiver.id', '=', 'st.received_by');

        $this->applyFilters($query, $request);

        return $query;
    }

    private function applyFilters(Builder $query, Request $request, string $dateColumn = 'st.transacted_at'): void
    {
        if ($warehouseId = $request->integer('warehouse_id')) {
            $query->where(function ($q) use ($warehouseId) {
                $q->where('st.source_warehouse_id', $warehouseId)
                    ->orWhere('st.destination_warehouse_id', $warehouseId);
            });
        }
        if ($sourceId = $request->integer('source_warehouse_id')) {
            $query->where('st.source_warehouse_id', $sourceId);
        }
        if ($destinationId = $request->integer('destination_warehouse_id')) {
            $query->where('st.destination_warehouse_id', $destinationId);
        }
        if ($categoryId = $request->input('category_id')) {
            $query->where('i.category_id', (int) $categoryId);
        }
        if ($status = trim((string) $request->input('status'))) {
            $query->where('st.status', $status);
        }

        $search = trim((string) $request->input('q'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('st.code', 'like', "%{$search}%")
                    ->orWhere('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('sw.name', 'like', "%{$search}%")
                    ->orWhere('dw.name', 'like', "%{$search}%")
                    ->orWhere('sti.discrepancy_note', 'like', "%{$search}%")
                    ->orWhere('st.discrepancy_note', 'like', "%{$search}%");
            });
        }

        try {
            if ($request->filled('date_from')) {
                $query->where($dateColumn, '>=', Carbon::parse($request->input('date_from'))->startOfDay());
            }
            if ($request->filled('date_to')) {
                $query->where($dateColumn, '<=', Carbon::parse($request->input('date_to'))->endOfDay());
            }
        } catch (\Throwable) {
            // Invalid dates are ignored so the report remains usable.
        }
    }

    private function summary(Request $request): array
    {
        $header = $this->baseQuery($request)
            ->where('st.status', '!=', 'cancelled');

        $totalTransfers = (clone $header)->distinct()->count('st.id');
        $completed = (clone $header)
            ->whereIn('st.status', ['received', 'received_with_discrepancy'])
            ->distinct()
            ->count('st.id');
        $complete = (clone $header)->where('st.status', 'received')->distinct()->count('st.id');
        $withDiscrepancy = (clone $header)
            ->where('st.status', 'received_with_discrepancy')
            ->distinct()
            ->count('st.id');
        $inTransit = (clone $header)->where('st.status', 'shipped')->distinct()->count('st.id');

        $items = $this->baseQuery($request)
            ->whereIn('st.status', ['received', 'received_with_discrepancy'])
            ->selectRaw('COALESCE(SUM(sti.qty_base), 0) as sent_base')
            ->selectRaw('COALESCE(SUM(sti.qty_received_base), 0) as received_base')
            ->selectRaw('COALESCE(SUM(sti.qty_discrepancy_base), 0) as discrepancy_base')
            ->first();

        $transitQty = (int) $this->baseQuery($request)
            ->where('st.status', 'shipped')
            ->sum('sti.qty_base');

        $leadRows = (clone $header)
            ->whereIn('st.status', ['received', 'received_with_discrepancy'])
            ->whereNotNull('st.shipped_at')
            ->whereNotNull('st.received_at')
            ->select('st.id', 'st.shipped_at', 'st.received_at')
            ->distinct()
            ->get();
        $leadHours = $leadRows
            ->map(fn ($row) => $this->hoursBetween($row->shipped_at, $row->received_at))
            ->filter(fn ($hours) => $hours !== null)
            ->values();

        $sentBase = (int) ($items->sent_base ?? 0);
        $receivedBase = (int) ($items->received_base ?? 0);
        $discrepancyBase = (int) ($items->discrepancy_base ?? 0);

        return [
            'total_transfers' => $totalTransfers,
            'completed_transfers' => $completed,
            'in_transit_transfers' => $inTransit,
            'in_transit_qty' => $transitQty,
            'complete_transfers' => $complete,
            'discrepancy_transfers' => $withDiscrepancy,
            'document_accuracy' => $completed > 0 ? round(($complete / $completed) * 100, 2) : 100.0,
            'fill_rate' => $sentBase > 0 ? round(($receivedBase / $sentBase) * 100, 2) : 100.0,
            'sent_base' => $sentBase,
            'received_base' => $receivedBase,
            'discrepancy_base' => $discrepancyBase,
            'unit_discrepancy_rate' => $sentBase > 0 ? round(($discrepancyBase / $sentBase) * 100, 2) : 0.0,
            'average_lead_hours' => $leadHours->isNotEmpty() ? round((float) $leadHours->average(), 2) : 0.0,
        ];
    }

    private function trend(Request $request): array
    {
        $query = $this->baseQuery($request)
            ->whereIn('st.status', ['received', 'received_with_discrepancy'])
            ->whereNotNull('st.received_at')
            ->selectRaw('DATE(st.received_at) as report_date')
            ->selectRaw('SUM(sti.qty_base) as sent_base')
            ->selectRaw('SUM(sti.qty_received_base) as received_base')
            ->selectRaw('SUM(sti.qty_discrepancy_base) as discrepancy_base')
            ->groupByRaw('DATE(st.received_at)')
            ->orderBy('report_date');

        return $query->get()->map(fn ($row) => [
            'date' => $row->report_date,
            'sent' => (int) $row->sent_base,
            'received' => (int) $row->received_base,
            'discrepancy' => (int) $row->discrepancy_base,
        ])->all();
    }

    private function topItems(Request $request): array
    {
        return $this->baseQuery($request)
            ->whereIn('st.status', ['received', 'received_with_discrepancy'])
            ->where('sti.qty_discrepancy_base', '>', 0)
            ->select('i.sku', 'i.name')
            ->selectRaw('COUNT(DISTINCT st.id) as transfer_count')
            ->selectRaw('SUM(sti.qty_discrepancy_base) as discrepancy_base')
            ->groupBy('i.id', 'i.sku', 'i.name')
            ->orderByDesc('discrepancy_base')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'sku' => $row->sku,
                'name' => $row->name,
                'transfer_count' => (int) $row->transfer_count,
                'discrepancy' => (int) $row->discrepancy_base,
            ])->all();
    }

    private function routePerformance(Request $request): array
    {
        return $this->baseQuery($request)
            ->whereIn('st.status', ['received', 'received_with_discrepancy'])
            ->select('sw.name as source_name', 'dw.name as destination_name')
            ->selectRaw('COUNT(DISTINCT st.id) as transfer_count')
            ->selectRaw('SUM(sti.qty_base) as sent_base')
            ->selectRaw('SUM(sti.qty_received_base) as received_base')
            ->selectRaw('SUM(sti.qty_discrepancy_base) as discrepancy_base')
            ->groupBy('st.source_warehouse_id', 'sw.name', 'st.destination_warehouse_id', 'dw.name')
            ->orderByDesc('transfer_count')
            ->get()
            ->map(function ($row) {
                $sent = (int) $row->sent_base;
                return [
                    'route' => $row->source_name.' -> '.$row->destination_name,
                    'transfer_count' => (int) $row->transfer_count,
                    'sent' => $sent,
                    'discrepancy' => (int) $row->discrepancy_base,
                    'fill_rate' => $sent > 0 ? round(((int) $row->received_base / $sent) * 100, 2) : 100.0,
                ];
            })->all();
    }

    private function oldestTransit(Request $request): array
    {
        $query = $this->baseQuery($request)
            ->where('st.status', 'shipped');

        return $query
            ->select('st.id', 'st.code', 'st.shipped_at', 'sw.name as source_name', 'dw.name as destination_name')
            ->selectRaw('SUM(sti.qty_base) as qty_base')
            ->groupBy('st.id', 'st.code', 'st.shipped_at', 'sw.name', 'dw.name')
            ->orderBy('st.shipped_at')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'route' => $row->source_name.' -> '.$row->destination_name,
                'qty' => (int) $row->qty_base,
                'age_hours' => $this->hoursBetween($row->shipped_at, now()),
            ])->all();
    }

    private function formatDateTime(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d H:i') : null;
    }

    private function hoursBetween(mixed $from, mixed $to): ?float
    {
        if (!$from || !$to) {
            return null;
        }

        return round(Carbon::parse($from)->diffInMinutes(Carbon::parse($to), true) / 60, 2);
    }
}
