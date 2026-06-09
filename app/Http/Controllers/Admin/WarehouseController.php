<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\ItemWarehouseSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WarehouseController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.warehouses.index');
    }

    public function data(Request $request)
    {
        $query = Warehouse::query()->orderByDesc('is_default')->orderBy('name');
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%"));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $warehouse = DB::transaction(function () use ($request) {
            $warehouse = Warehouse::create($this->validated($request));
            Item::select('id')->chunkById(500, function ($items) use ($warehouse) {
                if ($items->isEmpty()) {
                    return;
                }
                $now = now();
                ItemWarehouseSetting::insert($items->map(fn ($item) => [
                    'warehouse_id' => $warehouse->id,
                    'item_id' => $item->id,
                    'safety_stock' => 0,
                    'location' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

            return $warehouse;
        });

        return response()->json(['message' => 'Gudang berhasil dibuat', 'warehouse' => $warehouse]);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $this->validated($request, $warehouse);
        if ($warehouse->is_default && (!$validated['is_active'] || $validated['type'] !== Warehouse::TYPE_FULFILLMENT)) {
            throw ValidationException::withMessages([
                'warehouse' => 'Gudang default harus tetap aktif dan bertipe Gudang Kecil / Fulfillment.',
            ]);
        }
        $warehouse->update($validated);

        return response()->json(['message' => 'Gudang berhasil diperbarui', 'warehouse' => $warehouse]);
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->is_default || $warehouse->stocks()->where('stock', '!=', 0)->exists()) {
            return response()->json(['message' => 'Gudang default atau gudang yang masih memiliki stok tidak dapat dihapus'], 422);
        }
        $referenced = collect([
            ['inbound_transactions', 'warehouse_id'],
            ['outbound_transactions', 'warehouse_id'],
            ['stock_opnames', 'warehouse_id'],
            ['stock_adjustments', 'warehouse_id'],
            ['stock_transfers', 'source_warehouse_id'],
            ['stock_transfers', 'destination_warehouse_id'],
            ['damaged_goods', 'warehouse_id'],
            ['damaged_allocations', 'warehouse_id'],
        ])->contains(fn ($ref) => DB::table($ref[0])->where($ref[1], $warehouse->id)->exists());
        if ($referenced) {
            return response()->json(['message' => 'Gudang sudah memiliki riwayat transaksi dan tidak dapat dihapus'], 422);
        }
        $warehouse->delete();

        return response()->json(['message' => 'Gudang berhasil dihapus']);
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('warehouses', 'code')->ignore($warehouse?->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in([Warehouse::TYPE_BULK, Warehouse::TYPE_FULFILLMENT])],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
