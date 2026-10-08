<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CbmForecastExport;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Support\Permission as PermissionSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Kalkulator / forecast CBM. Hanya membaca master item (dimensi koli),
 * tidak menyimpan atau mengubah data apa pun.
 */
class CbmForecastController extends Controller
{
    private const INDEX_ROUTE = 'admin.reports.cbm-forecast.index';

    private const MAX_LINES = 1000;

    private const MAX_KOLI = 1000000;

    /** Kapasitas nominal kontainer (m³) untuk estimasi jumlah kontainer. */
    public const CONTAINERS = [
        '20gp' => ['label' => "20' GP", 'capacity' => 33.2],
        '40gp' => ['label' => "40' GP", 'capacity' => 67.7],
        '40hc' => ['label' => "40' HC", 'capacity' => 76.4],
    ];

    public function index()
    {
        return view('admin.reports.cbm-forecast.index', [
            'dataUrl' => route('admin.reports.cbm-forecast.data'),
            'lookupUrl' => route('admin.reports.cbm-forecast.lookup'),
            'exportUrl' => route('admin.reports.cbm-forecast.export'),
            'printUrl' => route('admin.reports.cbm-forecast.print'),
            'containers' => self::CONTAINERS,
            'maxLines' => self::MAX_LINES,
        ]);
    }

    /** Pencarian item untuk select2. */
    public function data(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        $items = Item::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('sku')
            ->limit(30)
            ->get(['id', 'sku', 'name', 'is_active']);

        return response()->json([
            'results' => $items->map(fn (Item $item) => [
                'id' => $item->sku,
                'text' => $item->sku.' - '.$item->name.($item->is_active ? '' : ' (Nonaktif)'),
            ])->values(),
        ]);
    }

    /** Ambil dimensi & CBM per koli untuk daftar SKU (dipisah koma). */
    public function lookup(Request $request)
    {
        $this->authorizeView($request);

        $skus = collect(explode(',', (string) $request->input('skus', '')))
            ->map(fn ($sku) => trim($sku))
            ->filter()
            ->unique()
            ->take(200)
            ->values();

        return response()->json([
            'data' => $this->itemsBySku($skus)->map(fn (Item $item) => $this->itemPayload($item))->values(),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeView($request);
        $result = $this->calculate($request);

        $filename = 'forecast-cbm-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new CbmForecastExport($result['rows'], $result['summary'], $result['title'], self::CONTAINERS), $filename);
    }

    /** Halaman cetak; disimpan sebagai PDF lewat dialog print browser. */
    public function print(Request $request)
    {
        $this->authorizeView($request);
        $result = $this->calculate($request);

        return view('admin.reports.cbm-forecast.print', $result + [
            'containers' => self::CONTAINERS,
            'printedAt' => now(),
            'printedBy' => $request->user()?->name,
        ]);
    }

    /**
     * Hitung ulang dari master item (nilai CBM dari browser tidak dipakai).
     */
    private function calculate(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.sku' => ['required', 'string', 'max:191'],
            'lines.*.koli' => ['required', 'integer', 'min:1', 'max:'.self::MAX_KOLI],
        ], [
            'lines.required' => 'Tambahkan minimal satu SKU.',
            'lines.max' => 'Maksimal '.self::MAX_LINES.' baris SKU.',
            'lines.*.koli.min' => 'Jumlah koli minimal 1.',
        ]);

        $lines = collect($validated['lines'])->map(fn ($line) => [
            'sku' => trim($line['sku']),
            'koli' => (int) $line['koli'],
        ]);
        $items = $this->itemsBySku($lines->pluck('sku')->unique()->values())
            ->keyBy(fn (Item $item) => mb_strtolower($item->sku));

        $rows = $lines->map(function (array $line) use ($items) {
            $item = $items->get(mb_strtolower($line['sku']));
            $cbm = $item?->cbm_per_koli;

            return [
                'sku' => $item?->sku ?? $line['sku'],
                'name' => $item?->name,
                'found' => $item !== null,
                'is_active' => $item?->is_active,
                'length' => $item?->koli_length_cm,
                'width' => $item?->koli_width_cm,
                'height' => $item?->koli_height_cm,
                'cbm_per_koli' => $cbm,
                'koli' => $line['koli'],
                'total_cbm' => $cbm !== null ? round($cbm * $line['koli'], 6) : null,
                'note' => $item === null ? 'SKU tidak ditemukan' : ($cbm === null ? 'Dimensi koli belum diisi' : null),
            ];
        })->values();

        $totalCbm = round((float) $rows->sum(fn ($row) => $row['total_cbm'] ?? 0), 6);

        return [
            'title' => $validated['title'] ?? null,
            'rows' => $rows,
            'summary' => [
                'sku_count' => $rows->count(),
                'total_koli' => (int) $rows->sum('koli'),
                'total_cbm' => $totalCbm,
                'incomplete_count' => $rows->whereNotNull('note')->count(),
                'incomplete_koli' => (int) $rows->whereNotNull('note')->sum('koli'),
            ],
        ];
    }

    private function itemsBySku(Collection $skus): Collection
    {
        if ($skus->isEmpty()) {
            return collect();
        }

        return Item::query()
            ->whereIn('sku', $skus->all())
            ->get(['id', 'sku', 'name', 'is_active', ...Item::KOLI_DIMENSION_FIELDS]);
    }

    private function itemPayload(Item $item): array
    {
        return [
            'sku' => $item->sku,
            'name' => $item->name,
            'is_active' => $item->is_active,
            'length' => $item->koli_length_cm,
            'width' => $item->koli_width_cm,
            'height' => $item->koli_height_cm,
            'cbm_per_koli' => $item->cbm_per_koli,
        ];
    }

    // Route lookup/export/print tidak terpetakan ke menu, jadi izin dicek manual ke menu index.
    private function authorizeView(Request $request): void
    {
        abort_unless(PermissionSupport::can($request->user(), self::INDEX_ROUTE, 'view'), 403, 'Anda tidak memiliki akses ke halaman ini');
    }
}
