<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Kurir;
use App\Models\PackerScanException;
use App\Models\PackerScanOut;
use App\Models\QcScanResi;
use App\Models\QcTransitItem;
use App\Models\Resi;
use App\Models\ResiDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PackerScanOutController extends Controller
{
    public function index()
    {
        return view('picker.scan-out', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'scan' => route('picker.scan-out.scan'),
                'history' => route('picker.scan-out.history'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function indexV2()
    {
        return view('picker.scan-out-v2', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'scan' => route('picker.scan-out.scan'),
                'history' => route('picker.scan-out.history'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function history()
    {
        return view('picker.scan-out-history', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'scanOut' => route('picker.scan-out.index'),
                'data' => route('picker.scan-out.history-data'),
                'logout' => route('logout'),
            ],
            'today' => now()->toDateString(),
        ]);
    }

    public function historyData(Request $request)
    {
        $query = PackerScanOut::query()
            ->with('resi')
            ->orderByDesc('scanned_at')
            ->orderByDesc('id');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('scan_code', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('id_pesanan', 'like', "%{$search}%")
                            ->orWhere('no_resi', 'like', "%{$search}%");
                    });
            });
        }

        $date = $request->input('date') ?: now()->toDateString();
        try {
            if ($date) {
                $query->whereDate('scan_date', $date);
            }
        } catch (\Throwable) {
            // ignore invalid date
        }

        $items = $query->get()->map(function ($row) {
            return [
                'id_pesanan' => $row->resi?->id_pesanan ?? '-',
                'no_resi' => $row->resi?->no_resi ?? '-',
                'scan_type' => $row->scan_type ?? '-',
                'scan_code' => $row->scan_code ?? '-',
                'scanned_at' => $row->scanned_at?->format('Y-m-d H:i') ?? '-',
            ];
        });

        return response()->json([
            'items' => $items,
        ]);
    }

    public function scan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:id_pesanan,no_resi'],
            'code' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $first = collect($errors)->flatten()->first() ?: 'Data scan tidak valid.';

            return response()->json([
                'message' => $first,
                'errors' => $errors,
            ], 422);
        }

        $validated = $validator->validated();
        $type = $validated['type'];
        $code = trim((string) $validated['code']);
        if ($code === '') {
            return response()->json([
                'message' => 'Kode tidak boleh kosong.',
            ], 422);
        }

        $resiQuery = Resi::query();
        if ($type === 'no_resi') {
            $resiQuery->where('no_resi', $code);
        } else {
            $resiQuery->where('id_pesanan', $code);
        }

        $resi = $resiQuery->first();
        if (!$resi) {
            return response()->json([
                'message' => 'Resi tidak ditemukan.',
            ], 422);
        }
        if (($resi->status ?? 'active') === 'canceled') {
            return response()->json([
                'message' => 'Resi sudah dibatalkan.',
            ], 422);
        }

        $scanDate = now()->toDateString();
        $details = ResiDetail::where('resi_id', $resi->id)->get(['sku', 'qty']);
        if ($details->isEmpty()) {
            return response()->json([
                'message' => 'Detail resi belum tersedia.',
            ], 422);
        }

        $exceptionSkus = PackerScanException::query()
            ->pluck('sku')
            ->map(fn ($sku) => strtolower(trim((string) $sku)))
            ->filter()
            ->values()
            ->all();
        $exceptionLookup = array_flip($exceptionSkus);

        $skuTotals = [];
        $excludedTotals = [];
        foreach ($details as $detail) {
            $sku = trim((string) $detail->sku);
            $qty = (int) $detail->qty;
            if ($sku === '' || $qty <= 0) {
                continue;
            }
            $skuKey = strtolower($sku);
            if (isset($exceptionLookup[$skuKey])) {
                $excludedTotals[$sku] = ($excludedTotals[$sku] ?? 0) + $qty;
                continue;
            }
            $skuTotals[$sku] = ($skuTotals[$sku] ?? 0) + $qty;
        }

        if (empty($skuTotals) && empty($excludedTotals)) {
            return response()->json([
                'message' => 'Detail resi tidak valid.',
            ], 422);
        }

        uksort($skuTotals, 'strcasecmp');
        uksort($excludedTotals, 'strcasecmp');
        $itemsPayload = $this->buildItemsPayload($skuTotals, $excludedTotals);

        DB::beginTransaction();
        try {
            $existingScan = PackerScanOut::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();
            if ($existingScan) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi sudah discan keluar.',
                ], 422);
            }

            $qcResi = QcScanResi::where('resi_id', $resi->id)
                ->where('status', 'completed')
                ->latest('completed_at')
                ->lockForUpdate()
                ->first();

            if (!$qcResi) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi belum selesai QC scan resi.',
                ], 422);
            }

            $items = collect();
            if (!empty($skuTotals)) {
                $skuKeys = collect(array_keys($skuTotals))
                    ->map(fn ($sku) => strtolower(trim((string) $sku)))
                    ->filter()
                    ->values()
                    ->all();

                $items = Item::whereIn(DB::raw('LOWER(sku)'), $skuKeys)
                    ->get(['id', 'sku', 'name'])
                    ->keyBy(fn ($item) => strtolower(trim((string) $item->sku)));
            }

            $issues = [];
            $allocations = [];

            foreach ($skuTotals as $sku => $qty) {
                $item = $items->get(strtolower(trim((string) $sku)));
                if (!$item) {
                    $issues[] = [
                        'sku' => $sku,
                        'required' => $qty,
                        'reason' => 'SKU tidak ditemukan',
                    ];
                    continue;
                }

                $transitRows = QcTransitItem::where('item_id', $item->id)
                    ->whereDate('transit_date', '<=', $scanDate)
                    ->where('remaining_qty', '>', 0)
                    ->orderBy('transit_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($transitRows->isEmpty()) {
                    $issues[] = [
                        'sku' => $sku,
                        'required' => $qty,
                        'reason' => 'QC transit belum tersedia',
                    ];
                    continue;
                }

                $available = (int) $transitRows->sum('remaining_qty');
                if ($available < $qty) {
                    $issues[] = [
                        'sku' => $sku,
                        'required' => $qty,
                        'available' => $available,
                        'reason' => 'Sisa QC transit tidak mencukupi',
                    ];
                    continue;
                }

                $allocations[] = [
                    'sku' => $sku,
                    'qty' => $qty,
                    'rows' => $transitRows,
                ];
            }

            if (!empty($issues)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Masih ada SKU dengan sisa QC transit tidak mencukupi.',
                    'details' => $issues,
                ], 422);
            }

            foreach ($allocations as $allocation) {
                $need = (int) $allocation['qty'];
                foreach ($allocation['rows'] as $row) {
                    if ($need <= 0) {
                        break;
                    }
                    $rowRemaining = (int) $row->remaining_qty;
                    if ($rowRemaining <= 0) {
                        continue;
                    }
                    $take = min($rowRemaining, $need);
                    $row->remaining_qty = max(0, $rowRemaining - $take);
                    $row->save();
                    $need -= $take;
                }
            }

            $kurirId = $this->resolveKurirId($resi);

            PackerScanOut::create([
                'resi_id' => $resi->id,
                'kurir_id' => $kurirId,
                'scan_type' => $type,
                'scan_code' => $code,
                'scan_date' => $scanDate,
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses scan out.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Scan out berhasil.',
            'resi' => [
                'id_pesanan' => $resi->id_pesanan,
                'no_resi' => $resi->no_resi,
                'tanggal_pesanan' => $resi->tanggal_pesanan?->format('Y-m-d'),
            ],
            'items' => $itemsPayload,
        ]);
    }

    private function resolveKurirId(Resi $resi): int
    {
        $kurirId = $resi->kurir_id;
        if ($kurirId) {
            return (int) $kurirId;
        }

        $kurirId = Kurir::where('name', 'Tidak ditemukan kurir')->value('id');
        if (!$kurirId) {
            $kurirId = Kurir::create(['name' => 'Tidak ditemukan kurir'])->id;
        }

        return (int) $kurirId;
    }

    private function buildItemsPayload(array $skuTotals, array $excludedTotals): array
    {
        $payload = [];

        foreach ($skuTotals as $sku => $qty) {
            $payload[] = [
                'sku' => $sku,
                'qty' => (int) $qty,
                'excluded' => false,
            ];
        }

        foreach ($excludedTotals as $sku => $qty) {
            $payload[] = [
                'sku' => $sku,
                'qty' => (int) $qty,
                'excluded' => true,
            ];
        }

        return $payload;
    }
}
