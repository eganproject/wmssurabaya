<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PickerSession;
use App\Models\PickerSessionItem;
use App\Models\PickerTransitItem;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\StockMutation;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PickerSessionController extends Controller
{
    public function index()
    {
        $session = $this->currentDraftSession();

        return view('picker/session', [
            'session' => $session ? $this->serializeSession($session) : null,
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'start' => route('picker.start'),
                'current' => route('picker.current'),
                'itemsStore' => route('picker.items.store'),
                'itemsUpdate' => route('picker.items.update', ':id'),
                'itemsDestroy' => route('picker.items.destroy', ':id'),
                'submit' => route('picker.submit'),
                'searchItems' => route('picker.items.search'),
                'scanItem' => route('picker.scan-item'),
                'logout' => route('logout'),
                'qcCurrent' => route('qc.current'),
                'qcStart' => route('qc.start'),
                'qcScanItem' => route('qc.scan-item'),
                'qcResiLookup' => route('qc.resi-lookup'),
                'qcResiRecord' => route('qc.resi-record'),
            ],
        ]);
    }

    public function current()
    {
        $session = $this->currentDraftSession();

        return response()->json([
            'session' => $session ? $this->serializeSession($session) : null,
        ]);
    }

    public function start()
    {
        $session = $this->currentDraftSession();
        if (!$session) {
            $session = $this->ensureDraftSession();
        }

        return response()->json([
            'session' => $this->serializeSession($session),
        ]);
    }

    public function storeItem(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
            'request_id' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $session = $this->addItemToSession(
                (int) $validated['item_id'],
                (int) $validated['qty'],
                $validated['note'] ?? null,
                $validated['request_id'] ?? null
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal menyimpan item',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'session' => $this->serializeSession($session),
        ]);
    }

    public function updateItem(Request $request, int $id)
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);

        $session = $this->currentDraftSession();
        if (!$session) {
            throw ValidationException::withMessages([
                'session' => 'Sesi belum tersedia',
            ]);
        }

        DB::beginTransaction();
        try {
            $itemRow = PickerSessionItem::where('picker_session_id', $session->id)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            $newQty = (int) $validated['qty'];
            $oldQty = (int) $itemRow->qty;
            $delta = $newQty - $oldQty;
            $occurredAt = now();
            $pickedDate = $session->started_at?->toDateString() ?? $occurredAt->toDateString();
            $sku = Item::where('id', $itemRow->item_id)->value('sku') ?? '';

            if ($delta > 0) {
                $this->ensurePickingListCapacity($pickedDate, $sku, $delta);
            }

            $transitRow = PickerTransitItem::where('item_id', $itemRow->item_id)
                ->where('picked_date', $pickedDate)
                ->lockForUpdate()
                ->first();

            if ($delta < 0) {
                $remainingTransit = (int) ($transitRow?->remaining_qty ?? 0);
                if (abs($delta) > $remainingTransit) {
                    throw ValidationException::withMessages([
                        'qty' => 'Qty tidak bisa dikurangi karena sudah scan out.',
                    ]);
                }
            }

            if ($delta !== 0) {
                StockService::mutate([
                    'item_id' => $itemRow->item_id,
                    'direction' => $delta > 0 ? 'out' : 'in',
                    'qty' => abs($delta),
                    'source_type' => 'qc',
                    'source_subtype' => 'mobile',
                    'source_id' => $session->id,
                    'source_code' => $session->code,
                    'note' => $validated['note'] ?? $itemRow->note,
                    'occurred_at' => $occurredAt,
                    'created_by' => auth()->id(),
                ]);
            }

            $itemRow->qty = $newQty;
            $itemRow->note = $validated['note'] ?? $itemRow->note;
            $itemRow->save();

            if ($delta !== 0) {
                $this->adjustPickingRemaining($pickedDate, $sku, $delta);
            }

            if ($transitRow) {
                $transitRow->qty += $delta;
                $transitRow->remaining_qty = max(0, $transitRow->remaining_qty + $delta);
                if ($delta !== 0) {
                    $transitRow->picked_at = $occurredAt;
                }
                if ($transitRow->qty <= 0) {
                    $transitRow->delete();
                } else {
                    $transitRow->save();
                }
            } elseif ($newQty > 0) {
                PickerTransitItem::create([
                    'item_id' => $itemRow->item_id,
                    'picked_date' => $pickedDate,
                    'qty' => $newQty,
                    'remaining_qty' => $newQty,
                    'picked_at' => $occurredAt,
                ]);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui item',
                'error' => $e->getMessage(),
            ], 500);
        }

        $session->load('items.item');

        return response()->json([
            'session' => $this->serializeSession($session),
        ]);
    }

    public function destroyItem(int $id)
    {
        $session = $this->currentDraftSession();
        if (!$session) {
            throw ValidationException::withMessages([
                'session' => 'Sesi belum tersedia',
            ]);
        }

        DB::beginTransaction();
        try {
            $itemRow = PickerSessionItem::where('picker_session_id', $session->id)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            $qty = (int) $itemRow->qty;
            $occurredAt = now();
            $pickedDate = $session->started_at?->toDateString() ?? $occurredAt->toDateString();
            $sku = Item::where('id', $itemRow->item_id)->value('sku') ?? '';

            $transitRow = PickerTransitItem::where('item_id', $itemRow->item_id)
                ->where('picked_date', $pickedDate)
                ->lockForUpdate()
                ->first();

            $remainingTransit = (int) ($transitRow?->remaining_qty ?? 0);
            if ($qty > $remainingTransit) {
                throw ValidationException::withMessages([
                    'qty' => 'Qty tidak bisa dihapus karena sudah scan out.',
                ]);
            }

            $itemRow->delete();

            if ($qty > 0) {
                StockService::mutate([
                    'item_id' => $itemRow->item_id,
                    'direction' => 'in',
                    'qty' => $qty,
                    'source_type' => 'qc',
                    'source_subtype' => 'mobile',
                    'source_id' => $session->id,
                    'source_code' => $session->code,
                    'note' => $itemRow->note ?? null,
                    'occurred_at' => $occurredAt,
                    'created_by' => auth()->id(),
                ]);
            }

            if ($transitRow) {
                $transitRow->qty -= $qty;
                $transitRow->remaining_qty = max(0, $transitRow->remaining_qty - $qty);
                $transitRow->picked_at = $occurredAt;
                if ($transitRow->qty <= 0) {
                    $transitRow->delete();
                } else {
                    $transitRow->save();
                }
            }

            $this->adjustPickingRemaining($pickedDate, $sku, -$qty);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus item',
                'error' => $e->getMessage(),
            ], 500);
        }

        $session->load('items.item');

        return response()->json([
            'session' => $this->serializeSession($session),
        ]);
    }

    public function submit()
    {
        DB::beginTransaction();
        try {
            $session = PickerSession::where('user_id', auth()->id())
                ->where('status', 'draft')
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if (!$session) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi belum tersedia',
                ]);
            }

            $session->load('items.item');
            if ($session->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Minimal 1 item diperlukan',
                ]);
            }

            $occurredAt = now();

            $session->status = 'submitted';
            $session->submitted_at = $occurredAt;
            $session->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyelesaikan sesi',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'QC scan selesai',
            'session' => $this->serializeSession($session->fresh('items.item')),
        ]);
    }

    public function searchItems(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $query = Item::query();
        if ($search !== '') {
            $query->where('sku', 'like', "%{$search}%");
        }

        $items = $query->orderBy('sku')
            ->get(['id', 'sku', 'name', 'address']);

        return response()->json([
            'items' => $items,
        ]);
    }

    public function scanItem(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'request_id' => ['nullable', 'string', 'max:120'],
        ]);

        $code = trim($validated['code']);
        $qty = (int) ($validated['qty'] ?? 1);

        $item = Item::where('sku', $code)->first();
        if (!$item) {
            return response()->json([
                'message' => 'SKU tidak ditemukan pada master item.',
                'errors' => [
                    'code' => ['SKU tidak ditemukan pada master item.'],
                ],
            ], 422);
        }

        try {
            $session = $this->addItemToSession($item->id, $qty, null, $validated['request_id'] ?? null);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal menambahkan item hasil scan',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Item berhasil ditambahkan melalui scan.',
            'session' => $this->serializeSession($session),
        ]);
    }

    private function currentDraftSession(): ?PickerSession
    {
        $today = now()->toDateString();
        return PickerSession::with('items.item')
            ->where('user_id', auth()->id())
            ->where('status', 'draft')
            ->whereDate('started_at', $today)
            ->latest('id')
            ->first();
    }

    private function ensureDraftSession(): PickerSession
    {
        return DB::transaction(function () {
            $today = now()->toDateString();
            $session = PickerSession::where('user_id', auth()->id())
                ->where('status', 'draft')
                ->whereDate('started_at', $today)
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if ($session) {
                return $session;
            }

            return PickerSession::create([
                'code' => $this->generateCode('QC'),
                'user_id' => auth()->id(),
                'status' => 'draft',
                'started_at' => now(),
            ]);
        });
    }

    private function addItemToSession(int $itemId, int $qty, ?string $note = null, ?string $requestId = null): PickerSession
    {
        if ($qty <= 0) {
            throw ValidationException::withMessages([
                'qty' => 'Qty harus lebih dari 0',
            ]);
        }

        $session = $this->ensureDraftSession();
        $idempotencyKey = $this->requestIdempotencyKey('picker.add-item', $requestId);

        DB::beginTransaction();
        try {
            if ($idempotencyKey && StockMutation::where('idempotency_key', $idempotencyKey)->lockForUpdate()->exists()) {
                DB::commit();
                return $session->fresh('items.item');
            }

            $deltaQty = $qty;
            $occurredAt = now();
            $pickedDate = $session->started_at?->toDateString() ?? $occurredAt->toDateString();
            $item = Item::findOrFail($itemId);
            $sku = $item->sku ?? '';

            $this->ensurePickingListCapacity($pickedDate, $sku, $deltaQty);

            $itemRow = PickerSessionItem::where('picker_session_id', $session->id)
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->first();

            if ($itemRow) {
                $itemRow->qty += $deltaQty;
                if (!empty($note)) {
                    $itemRow->note = $note;
                }
                $itemRow->save();
            } else {
                PickerSessionItem::create([
                    'picker_session_id' => $session->id,
                    'item_id' => $itemId,
                    'qty' => $deltaQty,
                    'note' => $note,
                ]);
            }

            $transitRow = PickerTransitItem::where('item_id', $itemId)
                ->where('picked_date', $pickedDate)
                ->lockForUpdate()
                ->first();

            if ($transitRow) {
                $transitRow->qty += $deltaQty;
                $transitRow->remaining_qty += $deltaQty;
                $transitRow->picked_at = $occurredAt;
                $transitRow->save();
            } else {
                PickerTransitItem::create([
                    'item_id' => $itemId,
                    'picked_date' => $pickedDate,
                    'qty' => $deltaQty,
                    'remaining_qty' => $deltaQty,
                    'picked_at' => $occurredAt,
                ]);
            }

            StockService::mutate([
                'item_id' => $itemId,
                'direction' => 'out',
                'qty' => $deltaQty,
                'source_type' => 'qc',
                'source_subtype' => 'mobile',
                'source_id' => $session->id,
                'source_code' => $session->code,
                'note' => $note,
                'occurred_at' => $occurredAt,
                'created_by' => auth()->id(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->adjustPickingRemaining($pickedDate, $sku, $deltaQty);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $session->load('items.item');

        return $session;
    }

    private function requestIdempotencyKey(string $action, ?string $requestId): ?string
    {
        $requestId = trim((string) ($requestId ?? ''));
        if ($requestId === '') {
            return null;
        }

        return StockService::idempotencyKey(['request', $action, auth()->id(), $requestId]);
    }

    private function serializeSession(PickerSession $session): array
    {
        return [
            'id' => $session->id,
            'code' => $session->code,
            'status' => $session->status,
            'started_at' => $session->started_at?->format('Y-m-d H:i'),
            'submitted_at' => $session->submitted_at?->format('Y-m-d H:i'),
            'items' => $session->items->map(function ($row) {
                return [
                    'id' => $row->id,
                    'item_id' => $row->item_id,
                    'sku' => $row->item?->sku ?? '',
                    'name' => $row->item?->name ?? '',
                    'address' => $row->item?->address ?? '',
                    'qty' => (int) $row->qty,
                    'note' => $row->note,
                ];
            })->values(),
        ];
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function adjustPickingRemaining(string $date, string $sku, int $deltaPicked): void
    {
        if ($sku === '' || $deltaPicked === 0) {
            return;
        }

        $row = PickingList::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            $this->adjustPickingException($date, $sku, $deltaPicked);
            return;
        }

        $remaining = (int) $row->remaining_qty;
        $total = (int) $row->qty;

        if ($deltaPicked > 0) {
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
            return;
        }

        $reduce = abs($deltaPicked);
        if ($reduce <= 0) {
            return;
        }

        $exception = PickingListException::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if ($exception) {
            $exceptionQty = (int) $exception->qty;
            if ($exceptionQty > $reduce) {
                $exception->qty = $exceptionQty - $reduce;
                $exception->save();
                $reduce = 0;
            } else {
                $reduce -= $exceptionQty;
                $exception->delete();
            }
        }

        if ($reduce > 0) {
            $row->remaining_qty = min($total, $remaining + $reduce);
            $row->save();
        }
    }

    private function adjustPickingException(string $date, string $sku, int $deltaPicked): void
    {
        $exception = PickingListException::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if ($exception) {
            $exception->qty = max(0, (int) $exception->qty + $deltaPicked);
            if ($exception->qty <= 0) {
                $exception->delete();
            } else {
                $exception->save();
            }
        } elseif ($deltaPicked > 0) {
            PickingListException::create([
                'list_date' => $date,
                'sku' => $sku,
                'qty' => $deltaPicked,
            ]);
        }
    }

    private function ensurePickingListCapacity(string $date, string $sku, int $requiredQty): void
    {
        if ($requiredQty <= 0) {
            return;
        }

        if ($sku === '') {
            throw ValidationException::withMessages([
                'code' => 'SKU tidak valid.',
            ]);
        }

        $row = PickingList::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            throw ValidationException::withMessages([
                'code' => ["SKU {$sku} tidak ada di picking list tanggal {$date}."],
            ]);
        }

        $remainingQty = (int) $row->remaining_qty;
        if ($remainingQty < $requiredQty) {
            throw ValidationException::withMessages([
                'qty' => ["Qty scan melebihi sisa picking list. SKU {$sku} tersisa {$remainingQty}, diminta {$requiredQty}."],
            ]);
        }
    }
}
