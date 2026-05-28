<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\PickingListExport;
use App\Models\Item;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\PackerScanException;
use App\Models\QcTransitItem;
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
