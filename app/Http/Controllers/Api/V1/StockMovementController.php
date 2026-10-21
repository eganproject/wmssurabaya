<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\StockMovementReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * JSON API for Laporan Stok → Analisis Pergerakan. Uses StockMovementReport,
 * the same builder as the web table and Excel export, so the numbers match.
 */
class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$filters, $error] = $this->filters($request, [
            'sort' => ['nullable', 'in:'.implode(',', array_keys(StockMovementReport::SORTS))],
            'dir' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'include_trend' => ['nullable', 'in:0,1,true,false'],
        ]);
        if ($error) {
            return $error;
        }

        $report = StockMovementReport::generate($filters, includeTrend: $request->boolean('include_trend'));
        $rows = StockMovementReport::sortRows($report['rows'], $filters['sort'] ?? null, $filters['dir'] ?? 'asc');

        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 100);
        $total = $rows->count();

        $response = [
            'success' => true,
            'meta' => $this->meta($report) + [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_unfiltered' => $report['summary']['total_sku'],
                'total_pages' => (int) ceil($total / $perPage),
            ],
            'summary' => $report['filtered_summary'],
            'data' => $rows->forPage($page, $perPage)->map(fn (array $row) => $this->row($row))->values(),
        ];
        if ($request->boolean('include_trend')) {
            $response['trend'] = $report['trend'];
        }

        return response()->json($response);
    }

    public function stores(Request $request): JsonResponse
    {
        [$filters, $error] = $this->filters($request, [
            'include_items' => ['nullable', 'in:0,1,true,false'],
        ]);
        if ($error) {
            return $error;
        }

        $breakdown = StockMovementReport::storeBreakdown($filters);
        $includeItems = $request->boolean('include_items');

        return response()->json([
            'success' => true,
            'meta' => $this->meta($breakdown),
            'data' => $breakdown['stores']
                ->map(fn (array $store) => $includeItems ? $store : collect($store)->except('items')->all())
                ->values(),
        ]);
    }

    /**
     * Same filter rules as the web report. `sku` is accepted as an alias of `q` (exact SKU).
     */
    private function filters(Request $request, array $extraRules): array
    {
        $input = $request->query();
        if (! isset($input['q']) && isset($input['sku'])) {
            $input['q'] = $input['sku'];
        }

        $validator = Validator::make(
            $input,
            array_merge(StockMovementReport::validationRules($input['cover_min'] ?? null), $extraRules),
        );
        if ($validator->fails()) {
            return [[], response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PARAMETER',
                    'message' => $validator->errors()->first(),
                    'fields' => $validator->errors()->toArray(),
                ],
            ], 400)];
        }

        return [$validator->validated(), null];
    }

    private function meta(array $report): array
    {
        return [
            'warehouse_code' => config('stock_api.warehouse_code'),
            'server_time' => now('Asia/Jakarta')->toIso8601String(),
            'date_from' => $report['date_from']->toDateString(),
            'date_to' => $report['date_to']->toDateString(),
            'period_days' => $report['period_days'],
        ];
    }

    private function row(array $row): array
    {
        unset($row['movement_order']);

        return $row;
    }
}
