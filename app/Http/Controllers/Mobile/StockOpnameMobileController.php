<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockOpnameMobileController extends Controller
{
    public function index()
    {
        return view('mobile.stock-opname.index', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'batchCreate' => route('opname.batch.create'),
                'batchShow' => route('opname.batch.show', '__CODE__'),
                'batchComplete' => route('opname.batch.complete', '__CODE__'),
                'itemsSearch' => route('opname.items.search'),
                'itemsStore' => route('opname.items.store', '__CODE__'),
                'itemsUpdate' => route('opname.items.update', ['__CODE__', '__ID__']),
                'itemsDestroy' => route('opname.items.destroy', ['__CODE__', '__ID__']),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function createBatch(Request $request)
    {
        return response()->json([
            'message' => 'Fitur membuat batch baru dinonaktifkan. Silakan gabung batch yang sudah ada.',
        ], 403);
    }

    public function showBatch(string $code)
    {
        $opname = StockOpname::where('code', $code)->first();
        if (!$opname) {
            return response()->json(['message' => 'Batch tidak ditemukan'], 404);
        }
        if ($opname->status === 'completed') {
            return response()->json([
                'message' => 'Batch sudah diselesaikan',
            ], 409);
        }

        return response()->json($this->serializeBatch($opname));
    }

    public function searchItems(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json(['items' => []]);
        }

        $query = Item::query()
            ->where(function ($query) use ($q) {
                $query->where('sku', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(12);

        $batchCode = trim((string) $request->input('batch_code', ''));
        if ($batchCode !== '') {
            $batch = StockOpname::where('code', $batchCode)->first();
            if ($batch) {
                $existingIds = StockOpnameItem::where('stock_opname_id', $batch->id)
                    ->pluck('item_id');
                if ($existingIds->isNotEmpty()) {
                    $query->whereNotIn('id', $existingIds);
                }
            }
        }

        $items = $query->get(['id', 'sku', 'name']);

        return response()->json([
            'items' => $items,
        ]);
    }

    public function storeItem(Request $request, string $code)
    {
        $opname = StockOpname::where('code', $code)->first();
        if (!$opname) {
            return response()->json(['message' => 'Batch tidak ditemukan'], 404);
        }
        if ($opname->status !== 'open') {
            throw ValidationException::withMessages([
                'batch' => 'Batch sudah diselesaikan',
            ]);
        }

        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'counted_qty' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        DB::beginTransaction();
        try {
            $this->clearOpenOpnameMutations($opname);

            $exists = StockOpnameItem::where('stock_opname_id', $opname->id)
                ->where('item_id', $validated['item_id'])
                ->lockForUpdate()
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'items' => 'Item sudah ada pada batch ini',
                ]);
            }

            $warehouseId = Warehouse::defaultId();
            $stock = ItemStock::where('warehouse_id', $warehouseId)
                ->where('item_id', $validated['item_id'])->lockForUpdate()->first();
            if (!$stock) {
                ItemStock::create(['warehouse_id' => $warehouseId, 'item_id' => $validated['item_id'], 'stock' => 0]);
                $stock = ItemStock::where('warehouse_id', $warehouseId)
                    ->where('item_id', $validated['item_id'])->lockForUpdate()->first();
            }

            $systemQty = (int) ($stock?->stock ?? 0);
            $countedQty = (int) $validated['counted_qty'];
            $adjustment = $countedQty - $systemQty;

            StockOpnameItem::create([
                'stock_opname_id' => $opname->id,
                'item_id' => $validated['item_id'],
                'system_qty' => $systemQty,
                'counted_qty' => $countedQty,
                'adjustment' => $adjustment,
                'note' => $validated['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan item opname',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json($this->serializeBatch($opname->fresh()));
    }

    public function updateItem(Request $request, string $code, int $id)
    {
        $opname = StockOpname::where('code', $code)->first();
        if (!$opname) {
            return response()->json(['message' => 'Batch tidak ditemukan'], 404);
        }
        if ($opname->status !== 'open') {
            throw ValidationException::withMessages([
                'batch' => 'Batch sudah diselesaikan',
            ]);
        }

        $validated = $request->validate([
            'counted_qty' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        DB::beginTransaction();
        try {
            $this->clearOpenOpnameMutations($opname);

            $item = StockOpnameItem::where('stock_opname_id', $opname->id)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            $newCounted = (int) $validated['counted_qty'];
            $newAdjustment = $newCounted - (int) $item->system_qty;
            $delta = $newAdjustment - (int) $item->adjustment;

            $item->counted_qty = $newCounted;
            $item->adjustment = $newAdjustment;
            $item->note = $validated['note'] ?? $item->note;
            $item->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui item opname',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json($this->serializeBatch($opname->fresh()));
    }

    public function destroyItem(string $code, int $id)
    {
        $opname = StockOpname::where('code', $code)->first();
        if (!$opname) {
            return response()->json(['message' => 'Batch tidak ditemukan'], 404);
        }
        if ($opname->status !== 'open') {
            throw ValidationException::withMessages([
                'batch' => 'Batch sudah diselesaikan',
            ]);
        }

        DB::beginTransaction();
        try {
            $this->clearOpenOpnameMutations($opname);

            $item = StockOpnameItem::where('stock_opname_id', $opname->id)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            $item->delete();
            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus item opname',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json($this->serializeBatch($opname->fresh()));
    }

    private function serializeBatch(StockOpname $opname): array
    {
        $opname->load([
            'creator:id,name',
            'completer:id,name',
            'items.item:id,sku,name',
            'items.creator:id,name',
        ]);

        return [
            'batch' => [
                'id' => $opname->id,
                'code' => $opname->code,
                'transacted_at' => $opname->transacted_at?->format('Y-m-d H:i'),
                'note' => $opname->note,
                'creator' => $opname->creator?->name,
                'status' => $opname->status ?? 'open',
                'completed_at' => $opname->completed_at?->format('Y-m-d H:i'),
                'completed_by' => $opname->completer?->name,
            ],
            'items' => $opname->items->sortByDesc('id')->values()->map(function ($row) {
                return [
                    'id' => $row->id,
                    'item_id' => $row->item_id,
                    'sku' => $row->item?->sku ?? '-',
                    'name' => $row->item?->name ?? '-',
                    'system_qty' => (int) $row->system_qty,
                    'counted_qty' => (int) $row->counted_qty,
                    'adjustment' => (int) $row->adjustment,
                    'note' => $row->note,
                    'created_by' => $row->creator?->name ?? '-',
                ];
            }),
        ];
    }

    public function completeBatch(string $code)
    {
        return response()->json([
            'message' => 'Fitur menyelesaikan batch dinonaktifkan. Hubungi admin untuk menyelesaikan batch.',
        ], 403);
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function clearOpenOpnameMutations(StockOpname $opname): void
    {
        if (($opname->status ?? 'open') !== 'open') {
            return;
        }

        if (!StockMutation::where('source_type', 'opname')->where('source_id', $opname->id)->exists()) {
            return;
        }

        StockService::rollbackBySource('opname', $opname->id);
        StockMutation::where('source_type', 'opname')->where('source_id', $opname->id)->delete();
    }
}
