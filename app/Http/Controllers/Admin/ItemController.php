<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use App\Imports\ItemsImport;
use App\Support\StockService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    protected ?int $defaultCategoryId = null;

    public function index()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        return view('admin.masterdata.items.index', compact('categories'));
    }

    public function show(Item $item)
    {
        $item->load('bundleComponents.componentItem');
        return response()->json([
            'id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'category_id' => $item->category_id,
            'address' => $item->address ?? '',
            'description' => $item->description ?? '',
            'safety_stock' => (int) ($item->safety_stock ?? 0),
            'is_bundle' => (bool) $item->is_bundle,
            'components' => $item->bundleComponents->map(fn ($bc) => [
                'component_item_id' => $bc->component_item_id,
                'sku' => $bc->componentItem?->sku ?? '',
                'name' => $bc->componentItem?->name ?? '',
                'qty' => (int) $bc->qty,
            ])->values(),
        ]);
    }

    public function data(Request $request)
    {
        $query = Item::with('category')->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $catFilter = $request->input('category_id');
        if ($catFilter !== null && $catFilter !== '') {
            if ((int)$catFilter === 0) {
                $query->where('category_id', 0);
            } else {
                $query->where('category_id', (int)$catFilter);
            }
        }

        $recordsTotal = Item::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($i) {
            return [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'category' => $i->category?->name ?? '-',
                'category_id' => $i->category_id,
                'address' => $i->address ?? '',
                'description' => $i->description ?? '',
                'safety_stock' => (int) ($i->safety_stock ?? 0),
                'is_bundle' => (bool) $i->is_bundle,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100', 'unique:items,sku'],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'min:0', function($attr, $value, $fail) {
                if ((int)$value === 0) return;
                if (!Category::where('id', $value)->exists()) {
                    $fail('Kategori tidak valid');
                }
            }],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
            'is_bundle' => ['nullable', 'boolean'],
            'components' => ['nullable', 'array'],
            'components.*.component_item_id' => ['required_with:components', 'integer', 'exists:items,id'],
            'components.*.qty' => ['required_with:components', 'integer', 'min:1'],
        ]);

        $isBundle = filter_var($request->input('is_bundle', false), FILTER_VALIDATE_BOOLEAN);
        $components = $isBundle ? ($validated['components'] ?? []) : [];

        if ($isBundle && empty($components)) {
            throw ValidationException::withMessages([
                'components' => 'Bundle harus memiliki minimal satu komponen.',
            ]);
        }

        $catId = $request->input('category_id');
        $validated['category_id'] = ($catId === null || (int)$catId === 0) ? 0 : $catId;
        if (array_key_exists('safety_stock', $validated)) {
            $validated['safety_stock'] = max(0, (int) $validated['safety_stock']);
        }
        $validated['is_bundle'] = $isBundle;
        unset($validated['components']);

        DB::beginTransaction();
        try {
            $item = Item::create($validated);

            if ($isBundle) {
                $this->syncBundleComponents($item, $components);
            } else {
                ItemStock::firstOrCreate(['item_id' => $item->id], ['stock' => 0]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Item berhasil dibuat',
                'item' => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'category_id' => $item->category_id,
                    'is_bundle' => $item->is_bundle,
                ]
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100', Rule::unique('items', 'sku')->ignore($item->id)],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'min:0', function($attr, $value, $fail) {
                if ((int)$value === 0) return;
                if (!Category::where('id', $value)->exists()) {
                    $fail('Kategori tidak valid');
                }
            }],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
            'is_bundle' => ['nullable', 'boolean'],
            'components' => ['nullable', 'array'],
            'components.*.component_item_id' => ['required_with:components', 'integer', 'exists:items,id'],
            'components.*.qty' => ['required_with:components', 'integer', 'min:1'],
        ]);

        $isBundle = filter_var($request->input('is_bundle', false), FILTER_VALIDATE_BOOLEAN);
        $components = $isBundle ? ($validated['components'] ?? []) : [];

        if ($isBundle && empty($components)) {
            throw ValidationException::withMessages([
                'components' => 'Bundle harus memiliki minimal satu komponen.',
            ]);
        }

        // Prevent toggling is_bundle once item has stock-related activity
        if ((bool)$item->is_bundle !== $isBundle) {
            $this->assertBundleToggleSafe($item, $isBundle);
        }

        $catId = $request->input('category_id');
        $validated['category_id'] = ($catId === null || (int)$catId === 0) ? 0 : $catId;
        if (array_key_exists('safety_stock', $validated)) {
            $validated['safety_stock'] = max(0, (int) $validated['safety_stock']);
        }
        $validated['is_bundle'] = $isBundle;
        unset($validated['components']);

        DB::beginTransaction();
        try {
            $item->update($validated);

            if ($isBundle) {
                $this->syncBundleComponents($item, $components);
                // Remove item_stocks row if switching to bundle (stock should be 0 since no activity allowed)
                ItemStock::where('item_id', $item->id)->where('stock', 0)->delete();
            } else {
                // Switching from bundle to regular: ensure item_stocks row exists, clear components
                ItemBundle::where('bundle_item_id', $item->id)->delete();
                ItemStock::firstOrCreate(['item_id' => $item->id], ['stock' => 0]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Item berhasil diperbarui',
                'item' => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'category_id' => $item->category_id,
                    'is_bundle' => $item->is_bundle,
                ]
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Item $item)
    {
        DB::beginTransaction();
        try {
            $this->assertItemCanBeDeleted($item);
            $item->delete();
            DB::commit();
            return response()->json(['message' => 'Item berhasil dihapus']);
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
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $created = 0;
        $updated = 0;
        DB::beginTransaction();
        try {
            $import = new ItemsImport();
            Excel::import($import, $request->file('file'));
            $created = $import->created;
            $updated = $import->updated;

            $initialStocks = $import->initialStocks ?? [];
            if (!empty($initialStocks)) {
                $code = 'INB-OPN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
                $transactedAt = now();

                $tx = InboundTransaction::create([
                    'code' => $code,
                    'type' => 'opening',
                    'ref_no' => null,
                    'note' => 'Saldo awal dari import items',
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'approved',
                    'approved_at' => $transactedAt,
                    'approved_by' => auth()->id(),
                ]);

                foreach ($initialStocks as $itemId => $qty) {
                    $qty = (int) $qty;
                    if ($qty <= 0) {
                        continue;
                    }
                    InboundItem::create([
                        'inbound_transaction_id' => $tx->id,
                        'item_id' => $itemId,
                        'qty' => $qty,
                        'note' => 'Saldo awal import',
                    ]);

                    StockService::mutate([
                        'item_id' => $itemId,
                        'direction' => 'in',
                        'qty' => $qty,
                        'source_type' => 'inbound',
                        'source_subtype' => 'opening',
                        'source_id' => $tx->id,
                        'source_code' => $tx->code,
                        'note' => 'Saldo awal import',
                        'occurred_at' => $transactedAt,
                        'created_by' => auth()->id(),
                        'idempotency_key' => StockService::idempotencyKey(['stock', 'inbound', 'opening', $tx->id, $itemId]),
                    ]);
                }
            }
            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal import: '.$e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Import selesai',
            'created' => $created,
            'updated' => $updated,
        ]);
    }

    /**
     * Sync bundle components: delete old entries then insert new ones.
     * Validates that components are not bundles themselves.
     */
    private function syncBundleComponents(Item $item, array $components): void
    {
        $componentItemIds = array_column($components, 'component_item_id');

        // A bundle's component cannot itself be a bundle
        $bundleComponentCount = Item::whereIn('id', $componentItemIds)
            ->where('is_bundle', true)
            ->count();
        if ($bundleComponentCount > 0) {
            throw ValidationException::withMessages([
                'components' => 'Komponen bundle tidak boleh berupa item bundle lain.',
            ]);
        }

        // A bundle cannot reference itself
        if (in_array($item->id, $componentItemIds, true)) {
            throw ValidationException::withMessages([
                'components' => 'Bundle tidak bisa menjadi komponen dirinya sendiri.',
            ]);
        }

        // Duplicate component check
        if (count($componentItemIds) !== count(array_unique($componentItemIds))) {
            throw ValidationException::withMessages([
                'components' => 'Terdapat duplikat komponen dalam bundle.',
            ]);
        }

        ItemBundle::where('bundle_item_id', $item->id)->delete();

        foreach ($components as $comp) {
            ItemBundle::create([
                'bundle_item_id' => $item->id,
                'component_item_id' => (int) $comp['component_item_id'],
                'qty' => max(1, (int) $comp['qty']),
            ]);
        }
    }

    /**
     * Prevent toggling is_bundle if the item already has stock-related history.
     */
    private function assertBundleToggleSafe(Item $item, bool $toBundle): void
    {
        if ($toBundle) {
            // Regular → Bundle: block if item has stock mutations or non-zero stock
            $hasMutations = DB::table('stock_mutations')->where('item_id', $item->id)->exists();
            $hasStock = (int) DB::table('item_stocks')->where('item_id', $item->id)->value('stock') > 0;
            if ($hasMutations || $hasStock) {
                throw ValidationException::withMessages([
                    'is_bundle' => 'Item tidak bisa diubah menjadi bundle karena sudah memiliki riwayat mutasi stok.',
                ]);
            }
        } else {
            // Bundle → Regular: block if bundle has been QC-scanned (transit exists)
            $hasTransit = DB::table('qc_transit_items')->where('item_id', $item->id)->exists();
            if ($hasTransit) {
                throw ValidationException::withMessages([
                    'is_bundle' => 'Item bundle tidak bisa diubah menjadi item biasa karena sudah memiliki riwayat transit QC.',
                ]);
            }
        }
    }

    protected function findOrCreateCategory(string $name, int $parentId = 0): ?Category
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }
        $normalized = mb_strtolower($trimmed);
        $category = Category::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($category) {
            if ($parentId !== 0 && $category->parent_id !== $parentId) {
                $category->parent_id = $parentId;
                $category->save();
            }
            return $category;
        }
        return Category::create([
            'name' => $trimmed,
            'parent_id' => $parentId,
        ]);
    }

    private function assertItemCanBeDeleted(Item $item): void
    {
        $references = [];

        // Block if this item is used as a bundle definition
        if (Schema::hasTable('item_bundles')) {
            if (DB::table('item_bundles')->where('bundle_item_id', $item->id)->exists()) {
                $references[] = 'definisi bundle';
            }
            if (DB::table('item_bundles')->where('component_item_id', $item->id)->exists()) {
                $references[] = 'komponen bundle';
            }
        }

        $itemTables = [
            'inbound_items' => 'penerimaan barang',
            'outbound_items' => 'barang keluar',
            'stock_mutations' => 'mutasi stok',
            'damaged_item_stocks' => 'stok barang rusak',
            'damaged_stock_mutations' => 'mutasi stok barang rusak',
            'damaged_allocation_items' => 'alokasi barang rusak',
            'stock_adjustment_items' => 'penyesuaian stok',
            'stock_opname_items' => 'stock opname',
            'damaged_good_items' => 'barang rusak',
            'qc_scan_resi_items' => 'QC scan resi',
            'qc_transit_items' => 'transit QC',
            'picker_session_items' => 'sesi picker',
            'picker_transit_items' => 'transit picker',
        ];

        foreach ($itemTables as $table => $label) {
            if (Schema::hasTable($table) && DB::table($table)->where('item_id', $item->id)->exists()) {
                $references[] = $label;
            }
        }

        if (Schema::hasTable('item_stocks')) {
            $stockQty = (int) DB::table('item_stocks')
                ->where('item_id', $item->id)
                ->value('stock');
            if ($stockQty > 0) {
                $references[] = 'stok berjalan '.$stockQty.' pcs';
            }
        }

        $skuTables = [
            'resi_details' => 'detail resi',
            'picking_lists' => 'picking list',
            'picking_list_exceptions' => 'exception picking list',
        ];

        foreach ($skuTables as $table => $label) {
            if (Schema::hasTable($table) && DB::table($table)->where('sku', $item->sku)->exists()) {
                $references[] = $label;
            }
        }

        if (!empty($references)) {
            $references = array_values(array_unique($references));
            throw ValidationException::withMessages([
                'item' => 'Item tidak bisa dihapus karena sudah dipakai pada '.implode(', ', $references).'. Nonaktifkan atau ubah data terkait jika memang diperlukan.',
            ]);
        }
    }

    protected function getDefaultCategoryId(): int
    {
        if ($this->defaultCategoryId !== null) {
            return $this->defaultCategoryId;
        }
        $default = Category::firstOrCreate(
            ['name' => 'Tanpa Kategori'],
            ['parent_id' => 0]
        );
        $this->defaultCategoryId = $default->id;
        return $this->defaultCategoryId;
    }
}
