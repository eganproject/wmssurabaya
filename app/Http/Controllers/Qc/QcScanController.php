<?php

namespace App\Http\Controllers\Qc;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\PackerScanException;
use App\Models\QcScanResi;
use App\Models\QcScanResiItem;
use App\Models\QcTransitItem;
use App\Models\Resi;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QcScanController extends Controller
{
    public function current()
    {
        return response()->json([
            'session' => $this->serializeDailyScanSummary(),
        ]);
    }

    public function start()
    {
        return response()->json([
            'session' => $this->serializeDailyScanSummary(),
        ]);
    }

    public function lookupResi(Request $request)
    {
        $type = $request->input('type', 'no_resi');
        $code = trim((string) $request->input('code', ''));

        if ($code === '') {
            return response()->json(['message' => 'Kode tidak boleh kosong.'], 422);
        }

        if (!in_array($type, ['no_resi', 'id_pesanan'], true)) {
            return response()->json(['message' => 'Tipe tidak valid.'], 422);
        }

        $resi = Resi::query()
            ->with(['details', 'kurir'])
            ->when($type === 'no_resi', fn ($q) => $q->where('no_resi', $code))
            ->when($type === 'id_pesanan', fn ($q) => $q->where('id_pesanan', $code))
            ->first();

        if (!$resi) {
            return response()->json(['message' => 'Resi tidak ditemukan.'], 422);
        }

        if (($resi->status ?? 'active') === 'canceled') {
            return response()->json(['message' => 'Resi sudah dibatalkan, tidak bisa di-QC.'], 422);
        }

        $skuBuckets = $this->buildResiSkuBuckets($resi);
        $skuTotals = $skuBuckets['required'];
        if (empty($skuTotals) && empty($skuBuckets['excluded'])) {
            return response()->json(['message' => 'Resi tidak memiliki detail SKU valid.'], 422);
        }

        $ledgerQty = [];
        $alreadyScanned = false;
        $isComplete = false;
        $qcResi = QcScanResi::where('resi_id', $resi->id)
            ->with(['items', 'scanner'])
            ->first();
        if ($qcResi) {
            if ((int) $qcResi->scanned_by !== (int) auth()->id()) {
                $scannerName = $qcResi->scanner?->name ?? 'petugas lain';
                return response()->json([
                    'message' => "Resi ini sedang/ sudah tercatat QC oleh {$scannerName}.",
                ], 422);
            }

            $alreadyScanned = true;
            $isComplete = ($qcResi->status === 'completed');
            foreach ($qcResi->items as $item) {
                $ledgerQty[$item->sku] = (int) $item->scanned_qty;
            }
        }

        $items = collect($skuTotals)->map(fn ($qty, $sku) => [
            'sku' => $sku,
            'qty' => $qty,
            'scanned_qty' => min((int) ($ledgerQty[$sku] ?? 0), (int) $qty),
        ])->values();

        return response()->json([
            'resi' => [
                'id' => $resi->id,
                'id_pesanan' => $resi->id_pesanan,
                'no_resi' => $resi->no_resi,
                'tanggal_pesanan' => $resi->tanggal_pesanan?->format('Y-m-d'),
                'kurir_name' => $resi->kurir?->name ?? 'Tidak diketahui',
            ],
            'items' => $items,
            'already_scanned' => $alreadyScanned,
            'is_complete' => $isComplete,
        ]);
    }

    public function recordResi(Request $request)
    {
        $validated = $request->validate([
            'resi_id' => ['required', 'integer', 'exists:resis,id'],
        ]);

        $qcResi = DB::transaction(function () use ($validated) {
            $resi = Resi::with('details')->lockForUpdate()->findOrFail((int) $validated['resi_id']);
            if (($resi->status ?? 'active') === 'canceled') {
                throw ValidationException::withMessages(['resi' => 'Resi sudah dibatalkan, tidak bisa di-QC.']);
            }

            return $this->ensureQcResi($resi);
        });

        return response()->json([
            'message' => 'Resi tercatat untuk QC.',
            'status' => $qcResi->status,
            'session' => $this->serializeDailyScanSummary(),
        ]);
    }

    public function scanItem(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'resi_id' => ['required', 'integer', 'exists:resis,id'],
            'request_id' => ['nullable', 'string', 'max:120'],
        ]);

        $code = trim((string) $validated['code']);
        $qty = (int) ($validated['qty'] ?? 1);
        $idempotencyKey = $this->requestIdempotencyKey('qc.scan-item', $validated['request_id'] ?? null);

        try {
            $summary = DB::transaction(function () use ($validated, $code, $qty, $idempotencyKey) {
                // Idempotency check: if any mutation with this key (or derived key) exists, skip.
                // For bundle scans the first component uses $idempotencyKey directly.
                if ($idempotencyKey && StockMutation::where('idempotency_key', $idempotencyKey)->lockForUpdate()->exists()) {
                    return $this->serializeDailyScanSummary();
                }

                $item = Item::where('sku', $code)->lockForUpdate()->first();
                if (!$item) {
                    throw ValidationException::withMessages(['code' => 'SKU tidak ditemukan pada master item.']);
                }

                $resi = Resi::with('details')->lockForUpdate()->findOrFail((int) $validated['resi_id']);
                if (($resi->status ?? 'active') === 'canceled') {
                    throw ValidationException::withMessages(['resi' => 'Resi sudah dibatalkan, tidak bisa di-QC.']);
                }

                $qcResi = $this->ensureQcResi($resi);
                if ($qcResi->status === 'completed') {
                    throw ValidationException::withMessages(['resi' => 'Resi sudah selesai di-QC.']);
                }

                $ledger = QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)
                    ->where('sku', $item->sku)
                    ->lockForUpdate()
                    ->first();

                if (!$ledger) {
                    throw ValidationException::withMessages([
                        'code' => "SKU {$item->sku} tidak ditemukan dalam resi tersebut.",
                    ]);
                }

                $remaining = max(0, (int) $ledger->required_qty - (int) $ledger->scanned_qty);
                if ($qty > $remaining) {
                    throw ValidationException::withMessages([
                        'qty' => "Qty scan ({$qty}) melebihi sisa resi untuk SKU {$item->sku} ({$remaining}).",
                    ]);
                }

                $scanAt = now();
                $date = $qcResi->scanned_at?->toDateString() ?? $scanAt->toDateString();

                $this->ensurePickingListCapacity($date, $item->sku, $qty);

                $ledger->scanned_qty = (int) $ledger->scanned_qty + $qty;
                $ledger->item_id = $item->id;
                $ledger->save();

                // Transit is always recorded against the scanned item's item_id
                // (for bundles this is the bundle's item_id, which scan-out will look up by SKU)
                $transit = QcTransitItem::where('item_id', $item->id)
                    ->where('transit_date', $date)
                    ->lockForUpdate()
                    ->first();

                if ($transit) {
                    $transit->qty += $qty;
                    $transit->remaining_qty += $qty;
                    $transit->last_qc_at = $scanAt;
                    $transit->save();
                } else {
                    QcTransitItem::create([
                        'item_id' => $item->id,
                        'transit_date' => $date,
                        'qty' => $qty,
                        'remaining_qty' => $qty,
                        'last_qc_at' => $scanAt,
                    ]);
                }

                if ($item->is_bundle) {
                    $this->deductBundleComponents($item, $qty, $qcResi, $resi, $scanAt, $idempotencyKey);
                } else {
                    StockService::mutate([
                        'item_id' => $item->id,
                        'direction' => 'out',
                        'qty' => $qty,
                        'source_type' => 'qc_resi',
                        'source_subtype' => 'scan',
                        'source_id' => $qcResi->id,
                        'source_code' => $resi->no_resi ?: $resi->id_pesanan,
                        'note' => 'QC scan resi',
                        'occurred_at' => $scanAt,
                        'created_by' => auth()->id(),
                        'idempotency_key' => $idempotencyKey,
                    ]);
                }

                $this->adjustPickingRemaining($date, $item->sku, $qty);
                $this->markCompletedIfReady($qcResi);

                return $this->serializeDailyScanSummary();
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal memproses scan QC.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Item berhasil discan.',
            'session' => $summary,
        ]);
    }

    /**
     * Deduct stock from each bundle component.
     * The first component uses $baseIdempotencyKey so the top-level idempotency check
     * can detect a completed bundle scan on retry. Subsequent components use derived keys.
     * Transit was already recorded against the bundle item_id — this only handles stock deduction.
     */
    private function deductBundleComponents(
        Item $item,
        int $bundleQty,
        QcScanResi $qcResi,
        Resi $resi,
        \DateTimeInterface $scanAt,
        ?string $baseIdempotencyKey
    ): void {
        $components = ItemBundle::where('bundle_item_id', $item->id)
            ->lockForUpdate()
            ->get();

        if ($components->isEmpty()) {
            throw ValidationException::withMessages([
                'code' => "Bundle {$item->sku} tidak memiliki komponen. Hubungi administrator.",
            ]);
        }

        // Pre-check virtual stock before acquiring individual component locks
        BundleService::assertVirtualStockSufficient($item->id, $bundleQty);

        $sourceCode = $resi->no_resi ?: $resi->id_pesanan;

        foreach ($components as $index => $component) {
            $componentQty = $bundleQty * (int) $component->qty;

            // First component uses the base key so the outer idempotency check catches retries.
            // Subsequent components use a derived key so each mutation is independently idempotent.
            $compKey = $index === 0
                ? $baseIdempotencyKey
                : ($baseIdempotencyKey
                    ? StockService::idempotencyKey([$baseIdempotencyKey, 'comp', $component->component_item_id])
                    : null);

            StockService::mutate([
                'item_id' => $component->component_item_id,
                'direction' => 'out',
                'qty' => $componentQty,
                'source_type' => 'qc_resi',
                'source_subtype' => 'scan_bundle',
                'source_id' => $qcResi->id,
                'source_code' => $sourceCode,
                'note' => "QC scan bundle {$item->sku}",
                'occurred_at' => $scanAt,
                'created_by' => auth()->id(),
                'idempotency_key' => $compKey,
            ]);
        }
    }

    private function requestIdempotencyKey(string $action, ?string $requestId): ?string
    {
        $requestId = trim((string) ($requestId ?? ''));
        if ($requestId === '') {
            return null;
        }

        return StockService::idempotencyKey(['request', $action, auth()->id(), $requestId]);
    }

    public function searchItems(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $query = Item::with([
            'warehouseSettings' => fn ($settings) => $settings->where('warehouse_id', Warehouse::defaultId()),
        ]);
        if ($search !== '') {
            $query->where('sku', 'like', "%{$search}%");
        }

        $items = $query->orderBy('sku')
            ->get(['id', 'sku', 'name'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'address' => $item->warehouseSettings->first()?->location ?? '',
            ]);

        return response()->json(['items' => $items]);
    }

    private function ensureQcResi(Resi $resi): QcScanResi
    {
        $qcResi = QcScanResi::where('resi_id', $resi->id)
            ->lockForUpdate()
            ->first();

        if (!$qcResi) {
            $qcResi = QcScanResi::create([
                'resi_id' => $resi->id,
                'status' => 'in_progress',
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
            ]);
        } elseif ((int) $qcResi->scanned_by !== (int) auth()->id()) {
            $qcResi->loadMissing('scanner:id,name');
            $scannerName = $qcResi->scanner?->name ?? 'petugas lain';
            throw ValidationException::withMessages([
                'resi' => "Resi ini sedang/ sudah tercatat QC oleh {$scannerName}.",
            ]);
        }

        $skuBuckets = $this->buildResiSkuBuckets($resi);
        $skuTotals = $skuBuckets['required'];
        if (empty($skuTotals)) {
            if (!empty($skuBuckets['excluded'])) {
                QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)
                    ->where('scanned_qty', 0)
                    ->delete();

                $qcResi->status = 'completed';
                $qcResi->completed_at = $qcResi->completed_at ?: now();
                $qcResi->completed_by = $qcResi->completed_by ?: auth()->id();
                $qcResi->save();

                return $qcResi->fresh(['resi', 'items.item']);
            }

            throw ValidationException::withMessages(['resi' => 'Resi tidak memiliki detail SKU valid.']);
        }

        $itemIdsBySku = Item::whereIn('sku', array_keys($skuTotals))->pluck('id', 'sku')->all();
        QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)
            ->whereNotIn('sku', array_keys($skuTotals))
            ->where('scanned_qty', 0)
            ->delete();

        foreach ($skuTotals as $sku => $requiredQty) {
            QcScanResiItem::updateOrCreate(
                ['qc_scan_resi_id' => $qcResi->id, 'sku' => $sku],
                ['item_id' => $itemIdsBySku[$sku] ?? null, 'required_qty' => $requiredQty]
            );
        }

        $this->markCompletedIfReady($qcResi);

        return $qcResi->fresh(['resi', 'items.item']);
    }

    private function buildResiSkuTotals(Resi $resi): array
    {
        return $this->buildResiSkuBuckets($resi)['required'];
    }

    private function buildResiSkuBuckets(Resi $resi): array
    {
        $resi->loadMissing('details');
        $exceptionLookup = $this->packerScanExceptionLookup();
        $totals = [];
        $excludedTotals = [];
        foreach ($resi->details as $detail) {
            $sku = trim((string) ($detail->sku ?? ''));
            $qty = (int) ($detail->qty ?? 0);
            if ($sku !== '' && isset($exceptionLookup[strtolower($sku)])) {
                if ($qty > 0) {
                    $excludedTotals[$sku] = ($excludedTotals[$sku] ?? 0) + $qty;
                }
                continue;
            }
            if ($sku !== '' && $qty > 0) {
                $totals[$sku] = ($totals[$sku] ?? 0) + $qty;
            }
        }
        ksort($totals, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($excludedTotals, SORT_NATURAL | SORT_FLAG_CASE);
        return [
            'required' => $totals,
            'excluded' => $excludedTotals,
        ];
    }

    private function markCompletedIfReady(QcScanResi $qcResi): void
    {
        $exceptionLookup = $this->packerScanExceptionLookup();
        $items = QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)
            ->lockForUpdate()
            ->get()
            ->reject(fn ($item) => isset($exceptionLookup[strtolower((string) $item->sku)]));
        $complete = $items->isNotEmpty()
            && $items->every(fn ($item) => (int) $item->scanned_qty >= (int) $item->required_qty);

        if ($complete) {
            $qcResi->status = 'completed';
            $qcResi->completed_at = now();
            $qcResi->completed_by = auth()->id();
            $qcResi->save();
            return;
        }

        if ($qcResi->status !== 'in_progress') {
            $qcResi->status = 'in_progress';
            $qcResi->completed_at = null;
            $qcResi->completed_by = null;
            $qcResi->save();
        }
    }

    private function serializeDailyScanSummary(): array
    {
        $exceptionLookup = $this->packerScanExceptionLookup();
        $resis = QcScanResi::with(['resi.kurir', 'items.item'])
            ->where('scanned_by', auth()->id())
            ->whereDate('scanned_at', now()->toDateString())
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->get();

        $items = $resis
            ->flatMap(fn ($resi) => $resi->items)
            ->reject(fn ($item) => isset($exceptionLookup[strtolower((string) $item->sku)]))
            ->groupBy('sku')
            ->map(function ($rows, $sku) {
                $first = $rows->first();
                return [
                    'sku' => $sku,
                    'name' => $first?->item?->name ?? '-',
                    'qty' => (int) $rows->sum('scanned_qty'),
                    'required_qty' => (int) $rows->sum('required_qty'),
                ];
            })->values();
        $firstScanAt = $resis->min('scanned_at');
        $lastScanAt = $resis->max(function ($row) {
            return $row->completed_at ?: $row->updated_at ?: $row->scanned_at;
        });

        return [
            'id' => null,
            'code' => null,
            'status' => 'active',
            'started_at' => $firstScanAt ? Carbon::parse($firstScanAt)->format('Y-m-d H:i') : null,
            'last_scan_at' => $lastScanAt ? Carbon::parse($lastScanAt)->format('Y-m-d H:i') : null,
            'items' => $items,
            'resis' => $resis->map(function ($row) use ($exceptionLookup) {
                $items = $row->items->reject(fn ($item) => isset($exceptionLookup[strtolower((string) $item->sku)]));
                $requiredQty = (int) $items->sum('required_qty');
                $scannedQty = (int) $items->sum('scanned_qty');
                return [
                    'id' => $row->id,
                    'resi_id' => $row->resi_id,
                    'no_resi' => $row->resi?->no_resi,
                    'id_pesanan' => $row->resi?->id_pesanan,
                    'tanggal_pesanan' => $row->resi?->tanggal_pesanan?->format('Y-m-d'),
                    'kurir_name' => $row->resi?->kurir?->name ?? '-',
                    'status' => $row->status,
                    'scanned_at' => $row->scanned_at?->format('Y-m-d H:i'),
                    'completed_at' => $row->completed_at?->format('Y-m-d H:i'),
                    'required_qty' => $requiredQty,
                    'scanned_qty' => $scannedQty,
                    'progress' => $requiredQty > 0 ? (int) floor(min(100, ($scannedQty / $requiredQty) * 100)) : 0,
                    'items' => $items->map(fn ($item) => [
                        'sku' => $item->sku,
                        'name' => $item->item?->name ?? '-',
                        'required_qty' => (int) $item->required_qty,
                        'scanned_qty' => (int) $item->scanned_qty,
                    ])->values(),
                ];
            })->values(),
        ];
    }

    private function packerScanExceptionLookup(): array
    {
        return PackerScanException::query()
            ->pluck('sku')
            ->map(fn ($sku) => strtolower(trim((string) $sku)))
            ->filter()
            ->flip()
            ->all();
    }

    private function ensurePickingListCapacity(string $date, string $sku, int $requiredQty): void
    {
        $row = PickingList::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            throw ValidationException::withMessages([
                'code' => "SKU {$sku} tidak ada di picking list tanggal {$date}.",
            ]);
        }

        if ((int) $row->remaining_qty < $requiredQty) {
            throw ValidationException::withMessages([
                'qty' => "Qty scan melebihi sisa picking list. SKU {$sku} tersisa {$row->remaining_qty}, diminta {$requiredQty}.",
            ]);
        }
    }

    private function adjustPickingRemaining(string $date, string $sku, int $deltaPicked): void
    {
        $row = PickingList::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            $this->adjustPickingException($date, $sku, $deltaPicked);
            return;
        }

        $remaining = (int) $row->remaining_qty;
        if ($remaining >= $deltaPicked) {
            $row->remaining_qty = $remaining - $deltaPicked;
            $row->save();
            return;
        }

        $overflow = $deltaPicked - max(0, $remaining);
        $row->remaining_qty = 0;
        $row->save();

        if ($overflow > 0) {
            $this->adjustPickingException($date, $sku, $overflow);
        }
    }

    private function adjustPickingException(string $date, string $sku, int $deltaPicked): void
    {
        $exception = PickingListException::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if ($exception) {
            $exception->qty += $deltaPicked;
            $exception->save();
            return;
        }

        PickingListException::create([
            'list_date' => $date,
            'sku' => $sku,
            'qty' => $deltaPicked,
        ]);
    }
}
