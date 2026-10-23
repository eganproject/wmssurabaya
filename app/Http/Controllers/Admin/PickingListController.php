<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\PickingListExport;
use App\Models\Item;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\PackerScanException;
use App\Models\QcTransitItem;
use App\Models\ResiImportBatch;
use App\Models\StockMutation;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class PickingListController extends Controller
{
    public function index()
    {
        return view('admin.inventory.picking-list.index', [
            'dataUrl' => route('admin.inventory.picking-list.data'),
            'dataUrlExceptions' => route('admin.inventory.picking-list.exceptions'),
            'batchesUrl' => route('admin.inventory.picking-list.batches'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = PickingList::query()
            ->with('item')
            ->orderBy('list_date', 'desc')
            ->orderBy('sku');
        $this->applyPackerExceptionFilter($baseQuery);
        $recordsTotalQuery = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($baseQuery, $request);

        $recordsTotal = (clone $recordsTotalQuery)->count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'ongoing' => (clone $summaryQuery)->where('remaining_qty', '>', 0)->count(),
            'done' => (clone $summaryQuery)->where('remaining_qty', '<=', 0)->count(),
        ];

        $query = clone $baseQuery;
        $this->applyStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $item = $row->item;
            return [
                'date' => $row->list_date?->format('Y-m-d') ?? '-',
                'sku' => $row->sku ?? '-',
                'name' => $item?->name ?? '-',
                'qty' => (int) $row->qty,
                'remaining_qty' => (int) $row->remaining_qty,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    /**
     * Picking list per batch import resi, for one date.
     *
     * The main picking list stays aggregated per date and SKU. This view splits the
     * same need by the import batch that brought each resi in. A resi re-imported in a
     * later batch belongs to that later batch. Resis without a batch (imported before
     * batches existed) are grouped as "Tanpa Batch".
     *
     * Picking itself is not recorded per batch, so the picked qty per SKU for the date
     * is shared out to batches in upload order (earliest batch first).
     */
    public function batches(Request $request)
    {
        $date = $this->resolveDate($request);
        $groups = $this->buildBatchBreakdown($date);

        $search = mb_strtolower(trim((string) $request->input('q', '')));
        $status = (string) $request->input('status', '');

        $batches = array_map(function ($group) use ($search, $status) {
            $rows = array_values(array_filter($group['rows'], function ($row) use ($search, $status) {
                if ($search !== ''
                    && !str_contains(mb_strtolower($row['sku']), $search)
                    && !str_contains(mb_strtolower($row['name']), $search)) {
                    return false;
                }
                if ($status === 'ongoing' && $row['remaining_qty'] <= 0) {
                    return false;
                }
                if ($status === 'done' && $row['remaining_qty'] > 0) {
                    return false;
                }
                return true;
            }));
            $group['rows'] = $rows;
            return $group;
        }, $groups);

        return response()->json([
            'date' => $date,
            'data' => array_values($batches),
        ]);
    }

    private function buildBatchBreakdown(string $date): array
    {
        $resiIdsOnDate = DB::table('resis')
            ->whereDate('tanggal_upload', $date)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'canceled');
            })
            ->select('id');

        // The latest active batch that touched each resi owns it.
        $owners = DB::table('resi_import_batch_items as bi')
            ->join('resi_import_batches as b', 'b.id', '=', 'bi.resi_import_batch_id')
            ->where(function ($q) {
                $q->whereNull('b.status')->orWhere('b.status', '!=', 'deleted');
            })
            ->whereIn('bi.resi_id', $resiIdsOnDate)
            ->groupBy('bi.resi_id')
            ->select('bi.resi_id', DB::raw('MAX(bi.resi_import_batch_id) as batch_id'))
            ->pluck('batch_id', 'resi_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $excludedSkus = PackerScanException::query()->pluck('sku')->map(fn ($sku) => trim((string) $sku))->flip()->all();

        $details = DB::table('resi_details as rd')
            ->whereIn('rd.resi_id', $resiIdsOnDate)
            ->select('rd.resi_id', 'rd.sku', 'rd.qty')
            ->get();

        $need = [];      // batchKey => sku => qty
        $resiCount = []; // batchKey => resi_id => true
        foreach ($details as $detail) {
            $sku = trim((string) ($detail->sku ?? ''));
            $qty = (int) ($detail->qty ?? 0);
            if ($sku === '' || $qty <= 0 || isset($excludedSkus[$sku])) {
                continue;
            }
            $key = (int) ($owners[$detail->resi_id] ?? 0);
            $need[$key][$sku] = ($need[$key][$sku] ?? 0) + $qty;
            $resiCount[$key][$detail->resi_id] = true;
        }

        if (empty($need)) {
            return [];
        }

        $batchModels = ResiImportBatch::query()
            ->with('uploader:id,name')
            ->whereIn('id', array_filter(array_keys($need)))
            ->get()
            ->keyBy('id');

        // Upload order: resis without a batch came first, then batches by upload time.
        $keys = array_keys($need);
        usort($keys, function ($a, $b) use ($batchModels) {
            if ($a === 0 || $b === 0) {
                return $a === 0 ? -1 : 1;
            }
            $ta = $batchModels->get($a)?->uploaded_at?->timestamp ?? 0;
            $tb = $batchModels->get($b)?->uploaded_at?->timestamp ?? 0;
            return $ta <=> $tb ?: $a <=> $b;
        });

        $picked = [];
        foreach (PickingList::whereDate('list_date', $date)->get(['sku', 'qty', 'remaining_qty']) as $row) {
            $picked[trim((string) $row->sku)] = max(0, (int) $row->qty - (int) $row->remaining_qty);
        }

        $allSkus = array_unique(array_merge(...array_map('array_keys', array_values($need))));
        $names = Item::whereIn('sku', $allSkus)->pluck('name', 'sku')->all();

        $groups = [];
        $number = 0;
        foreach ($keys as $key) {
            $rows = [];
            $totalQty = 0;
            $totalRemaining = 0;
            ksort($need[$key]);
            foreach ($need[$key] as $sku => $qty) {
                $sku = (string) $sku;
                $take = min($qty, $picked[$sku] ?? 0);
                $picked[$sku] = ($picked[$sku] ?? 0) - $take;
                $remaining = $qty - $take;
                $totalQty += $qty;
                $totalRemaining += $remaining;
                $rows[] = [
                    'sku' => $sku,
                    'name' => $names[$sku] ?? '-',
                    'qty' => $qty,
                    'picked_qty' => $take,
                    'remaining_qty' => $remaining,
                ];
            }

            $batch = $key ? $batchModels->get($key) : null;
            if ($key) {
                $number++;
            }
            $groups[] = [
                'id' => $key ?: null,
                'number' => $key ? $number : null,
                'batch_code' => $batch?->batch_code ?? 'Tanpa Batch',
                'file_name' => $batch?->file_name,
                'note' => $batch?->note,
                'uploaded_at' => $batch?->uploaded_at?->format('H:i'),
                'uploaded_by' => $batch?->uploader?->name,
                'total_resis' => count($resiCount[$key] ?? []),
                'total_sku' => count($rows),
                'total_qty' => $totalQty,
                'picked_qty' => $totalQty - $totalRemaining,
                'remaining_qty' => $totalRemaining,
                'sku_done' => count(array_filter($rows, fn ($r) => $r['remaining_qty'] <= 0)),
                'rows' => $rows,
            ];
        }

        return $groups;
    }

    private function resolveDate(Request $request): string
    {
        try {
            return Carbon::parse($request->input('date') ?: now()->toDateString())->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    public function dataExceptions(Request $request)
    {
        $baseQuery = PickingListException::query()
            ->with('item')
            ->orderBy('list_date', 'desc')
            ->orderBy('sku');
        $this->applyPackerExceptionFilter($baseQuery);
        $recordsTotalQuery = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($baseQuery, $request);

        $recordsTotal = (clone $recordsTotalQuery)->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $baseQuery->skip($start)->take($length);
        }

        $data = $baseQuery->get()->map(function ($row) {
            $item = $row->item;
            return [
                'date' => $row->list_date?->format('Y-m-d') ?? '-',
                'list_date' => $row->list_date?->format('Y-m-d') ?? null,
                'sku' => $row->sku ?? '-',
                'name' => $item?->name ?? '-',
                'qty' => (int) $row->qty,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function returnException(Request $request)
    {
        $validated = $request->validate([
            'list_date' => ['required', 'date'],
            'sku' => ['required', 'string', 'max:100'],
            'qty' => ['required', 'integer', 'min:1'],
            'request_id' => ['nullable', 'string', 'max:120'],
        ]);

        $listDate = Carbon::parse($validated['list_date'])->toDateString();
        $sku = trim($validated['sku']);
        $qty = (int) $validated['qty'];
        $idempotencyKey = $this->requestIdempotencyKey('picking-exception.return', $validated['request_id'] ?? null);

        DB::beginTransaction();
        try {
            if ($idempotencyKey && StockMutation::where('idempotency_key', $idempotencyKey)->lockForUpdate()->exists()) {
                DB::commit();
                return response()->json([
                    'message' => 'Stok berhasil dikembalikan.',
                ]);
            }

            $exception = PickingListException::where('list_date', $listDate)
                ->where('sku', $sku)
                ->lockForUpdate()
                ->first();

            if (!$exception) {
                throw ValidationException::withMessages([
                    'sku' => 'Exception tidak ditemukan.',
                ]);
            }

            $currentQty = (int) $exception->qty;
            if ($qty > $currentQty) {
                throw ValidationException::withMessages([
                    'qty' => 'Qty melebihi jumlah exception.',
                ]);
            }

            $item = Item::where('sku', $sku)->first();
            if (!$item) {
                throw ValidationException::withMessages([
                    'sku' => 'SKU tidak ditemukan.',
                ]);
            }

            $transit = QcTransitItem::where('item_id', $item->id)
                ->where('transit_date', $listDate)
                ->lockForUpdate()
                ->first();

            $remainingTransit = (int) ($transit?->remaining_qty ?? 0);
            if (!$transit || $remainingTransit < $qty) {
                throw ValidationException::withMessages([
                    'qty' => 'Sisa transit tidak mencukupi untuk retur.',
                ]);
            }

            $exceptionId = $exception->id;

            $exception->qty = $currentQty - $qty;
            if ($exception->qty <= 0) {
                $exception->delete();
            } else {
                $exception->save();
            }

            $transit->qty = max(0, (int) $transit->qty - $qty);
            $transit->remaining_qty = max(0, (int) $transit->remaining_qty - $qty);
            if ($transit->qty <= 0) {
                $transit->delete();
            } else {
                $transit->save();
            }

            StockService::mutate([
                'item_id' => $item->id,
                'direction' => 'in',
                'qty' => $qty,
                'source_type' => 'picking_exception',
                'source_subtype' => 'return',
                'source_id' => $exceptionId,
                'source_code' => $sku,
                'note' => 'Retur dari picking exception',
                'occurred_at' => now(),
                'created_by' => auth()->id(),
                'idempotency_key' => $idempotencyKey,
            ]);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mengembalikan stok.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Stok berhasil dikembalikan.',
        ]);
    }

    private function requestIdempotencyKey(string $action, ?string $requestId): ?string
    {
        $requestId = trim((string) ($requestId ?? ''));
        if ($requestId === '') {
            return null;
        }

        return StockService::idempotencyKey(['request', $action, auth()->id(), $requestId]);
    }

    public function recalculate(Request $request)
    {
        $validated = $request->validate([
            'list_date' => ['required', 'date'],
        ]);

        $listDate = Carbon::parse($validated['list_date'])->toDateString();
        $today = now()->toDateString();
        if ($listDate !== $today) {
            return response()->json([
                'message' => 'Recalculate hanya boleh untuk tanggal hari ini.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $requiredRows = DB::table('resi_details as rd')
                ->join('resis as r', 'r.id', '=', 'rd.resi_id')
                ->whereDate('r.tanggal_upload', $listDate)
                ->where(function ($q) {
                    $q->whereNull('r.status')
                        ->orWhere('r.status', '!=', 'canceled');
                })
                ->select('rd.sku', DB::raw('SUM(rd.qty) as qty'))
                ->groupBy('rd.sku')
                ->get();

            $required = [];
            foreach ($requiredRows as $row) {
                $sku = trim((string) ($row->sku ?? ''));
                $qty = (int) ($row->qty ?? 0);
                if ($sku === '' || $qty <= 0) {
                    continue;
                }
                $required[$sku] = $qty;
            }

            $pickedRows = DB::table('qc_transit_items as pt')
                ->join('items as i', 'i.id', '=', 'pt.item_id')
                ->whereDate('pt.transit_date', $listDate)
                ->select('i.sku', DB::raw('SUM(pt.qty) as qty'))
                ->groupBy('i.sku')
                ->get();

            $picked = [];
            foreach ($pickedRows as $row) {
                $sku = trim((string) ($row->sku ?? ''));
                $qty = (int) ($row->qty ?? 0);
                if ($sku === '' || $qty <= 0) {
                    continue;
                }
                $picked[$sku] = $qty;
            }

            $existingListSkus = PickingList::where('list_date', $listDate)->pluck('sku')->all();
            $existingExceptionSkus = PickingListException::where('list_date', $listDate)->pluck('sku')->all();

            $allSkus = array_values(array_unique(array_merge(
                array_keys($required),
                array_keys($picked),
                $existingListSkus,
                $existingExceptionSkus
            )));

            $updated = 0;
            $deleted = 0;
            $exceptions = 0;

            foreach ($allSkus as $sku) {
                $sku = trim((string) $sku);
                if ($sku === '') {
                    continue;
                }

                $listQty = (int) ($required[$sku] ?? 0);
                $pickedQty = (int) ($picked[$sku] ?? 0);
                $remaining = max(0, $listQty - $pickedQty);
                $exceptionQty = max(0, $pickedQty - $listQty);

                $listRow = PickingList::where('list_date', $listDate)
                    ->where('sku', $sku)
                    ->lockForUpdate()
                    ->first();

                if ($listQty > 0) {
                    if ($listRow) {
                        $listRow->qty = $listQty;
                        $listRow->remaining_qty = $remaining;
                        $listRow->save();
                    } else {
                        PickingList::create([
                            'list_date' => $listDate,
                            'sku' => $sku,
                            'qty' => $listQty,
                            'remaining_qty' => $remaining,
                        ]);
                    }
                    $updated++;
                } elseif ($listRow) {
                    $listRow->delete();
                    $deleted++;
                }

                $exceptionRow = PickingListException::where('list_date', $listDate)
                    ->where('sku', $sku)
                    ->lockForUpdate()
                    ->first();

                if ($exceptionQty > 0) {
                    if ($exceptionRow) {
                        $exceptionRow->qty = $exceptionQty;
                        $exceptionRow->save();
                    } else {
                        PickingListException::create([
                            'list_date' => $listDate,
                            'sku' => $sku,
                            'qty' => $exceptionQty,
                        ]);
                    }
                    $exceptions++;
                } elseif ($exceptionRow) {
                    $exceptionRow->delete();
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Rekalkulasi picking list selesai.',
                'summary' => [
                    'updated' => $updated,
                    'deleted' => $deleted,
                    'exceptions' => $exceptions,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal melakukan rekalkulasi picking list.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'date' => $request->input('date'),
            'status' => $request->input('status', ''),
        ];

        $date = $filters['date'] ?: now()->toDateString();
        $suffix = $filters['status'] ?: 'all';
        $filename = "picking-list-{$date}-{$suffix}.xlsx";

        return Excel::download(new PickingListExport($filters), $filename);
    }

    public function storeQty(Request $request)
    {
        $validated = $request->validate([
            'list_date' => ['required', 'date'],
            'sku' => ['required', 'string', 'max:100'],
            'qty' => ['required', 'integer', 'min:1'],
            'mode' => ['required', 'in:add,reduce'],
        ]);

        $listDate = Carbon::parse($validated['list_date'])->toDateString();
        $sku = trim($validated['sku']);
        $qty = (int) $validated['qty'];
        $mode = $validated['mode'];
        $delta = $mode === 'reduce' ? -$qty : $qty;

        try {
            $row = DB::transaction(function () use ($listDate, $sku, $delta) {
                $existing = PickingList::where('list_date', $listDate)
                    ->where('sku', $sku)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $newQty = max(0, (int) $existing->qty + $delta);
                    $balances = $this->getPickingBalances($listDate, $sku, $newQty);
                    $existing->qty = $newQty;
                    $existing->remaining_qty = $balances['remaining'];
                    if ($existing->qty <= 0 && $existing->remaining_qty <= 0) {
                        $existing->delete();
                        return null;
                    }
                    $existing->save();
                    $this->syncPickingException($listDate, $sku, $balances['exception']);
                    return $existing;
                }

                if ($delta <= 0) {
                    throw ValidationException::withMessages([
                        'sku' => 'Data picking list tidak ditemukan untuk tanggal tersebut.',
                    ]);
                }

                $balances = $this->getPickingBalances($listDate, $sku, $delta);
                $created = PickingList::create([
                    'list_date' => $listDate,
                    'sku' => $sku,
                    'qty' => $delta,
                    'remaining_qty' => $balances['remaining'],
                ]);
                $this->syncPickingException($listDate, $sku, $balances['exception']);
                return $created;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'Gagal menyimpan data.',
            ], 500);
        }

        $message = $mode === 'reduce' ? 'Qty berhasil dikurangi.' : 'Qty berhasil ditambahkan.';
        if (!$row && $mode === 'reduce') {
            $message = 'Qty berhasil dikurangi dan baris picking list dihapus.';
        }

        return response()->json([
            'message' => $message,
            'data' => $row ? [
                'id' => $row->id,
                'sku' => $row->sku,
                'list_date' => $row->list_date?->format('Y-m-d'),
                'qty' => (int) $row->qty,
                'remaining_qty' => (int) $row->remaining_qty,
            ] : null,
        ]);
    }

    private function getPickingBalances(string $date, string $sku, int $listQty): array
    {
        if ($listQty <= 0) {
            return [
                'remaining' => 0,
                'exception' => $this->getPickedQty($date, $sku),
            ];
        }

        $pickedQty = $this->getPickedQty($date, $sku);
        $remaining = $listQty - $pickedQty;
        if ($remaining < 0) {
            $remaining = 0;
        }
        $exception = $pickedQty - $listQty;
        if ($exception < 0) {
            $exception = 0;
        }

        return [
            'remaining' => $remaining,
            'exception' => $exception,
        ];
    }

    private function getPickedQty(string $date, string $sku): int
    {
        $itemId = Item::where('sku', $sku)->value('id');
        if (!$itemId) {
            return 0;
        }

        return (int) QcTransitItem::where('item_id', $itemId)
            ->where('transit_date', $date)
            ->value('qty');
    }

    private function syncPickingException(string $date, string $sku, int $exceptionQty): void
    {
        $exception = PickingListException::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if ($exceptionQty > 0) {
            if ($exception) {
                $exception->qty = $exceptionQty;
                $exception->save();
            } else {
                PickingListException::create([
                    'list_date' => $date,
                    'sku' => $sku,
                    'qty' => $exceptionQty,
                ]);
            }
            return;
        }

        if ($exception) {
            $exception->delete();
        }
    }

    private function applyDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->where('list_date', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function applyStatusFilter($query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status === 'ongoing') {
            $query->where('remaining_qty', '>', 0);
        } elseif ($status === 'done') {
            $query->where('remaining_qty', '<=', 0);
        }
    }

    private function applyPackerExceptionFilter($query): void
    {
        $query->whereNotIn('sku', PackerScanException::query()->select('sku'));
    }
}
