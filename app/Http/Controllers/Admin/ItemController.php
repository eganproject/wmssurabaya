<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use App\Models\ItemUnit;
use App\Models\ItemWarehouseSetting;
use App\Models\Warehouse;
use App\Models\Uom;
use App\Exports\ItemsTemplateExport;
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
    public function index()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']);
        $uoms = Uom::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        return view('admin.masterdata.items.index', compact('categories', 'warehouses', 'uoms'));
    }

    public function show(Item $item)
    {
        $item->load(['bundleComponents.componentItem', 'units.uom', 'warehouseSettings']);
        $baseUnit = $item->units->firstWhere('is_base', true);
        $packageUnit = $item->units->firstWhere('is_base', false);
        return response()->json([
            'id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'category_id' => $item->category_id,
            'description' => $item->description ?? '',
            'warehouse_settings' => $item->warehouseSettings->map(fn ($setting) => [
                'warehouse_id' => $setting->warehouse_id,
                'safety_stock' => (int) $setting->safety_stock,
                'location' => $setting->location ?? '',
            ])->values(),
            'is_bundle' => (bool) $item->is_bundle,
            'base_unit_name' => $baseUnit?->name ?? 'PCS',
            'base_uom_id' => $baseUnit?->uom_id,
            'package_unit_name' => $packageUnit?->name ?? '',
            'package_uom_id' => $packageUnit?->uom_id,
            'package_conversion_qty' => (int) ($packageUnit?->conversion_qty ?? 1),
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
        $defaultWarehouseId = Warehouse::defaultId();
        $query = Item::with([
            'category',
            'units.uom',
            'warehouseSettings' => fn ($settings) => $settings->where('warehouse_id', $defaultWarehouseId),
        ])->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search, $defaultWarehouseId) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('warehouseSettings', fn ($settings) => $settings
                        ->where('warehouse_id', $defaultWarehouseId)
                        ->where('location', 'like', "%{$search}%"))
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $catFilter = $request->input('category_id');
        if ($catFilter !== null && $catFilter !== '') {
            if ((int)$catFilter === 0) {
                $query->whereNull('category_id');
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
                'default_location' => $i->warehouseSettings->first()?->location ?? '',
                'description' => $i->description ?? '',
                'default_safety_stock' => (int) ($i->warehouseSettings->first()?->safety_stock ?? 0),
                'is_bundle' => (bool) $i->is_bundle,
                'base_unit' => $i->units->firstWhere('is_base', true)?->name ?? 'PCS',
                'package_unit' => $i->units->firstWhere('is_base', false)?->name,
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
            'category_id' => ['nullable', 'integer', 'min:0', function ($attribute, $value, $fail) {
                if ((int) $value !== 0 && !Category::whereKey($value)->exists()) {
                    $fail('Kategori tidak valid.');
                }
            }],
            'description' => ['nullable', 'string'],
            'is_bundle' => ['nullable', 'boolean'],
            'components' => ['nullable', 'array'],
            'components.*.component_item_id' => ['required_with:components', 'integer', 'exists:items,id'],
            'components.*.qty' => ['required_with:components', 'integer', 'min:1'],
            'base_unit_name' => ['nullable', 'string', 'max:30'],
            'base_uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'package_unit_name' => ['nullable', 'string', 'max:30', 'different:base_unit_name'],
            'package_uom_id' => ['nullable', 'integer', 'exists:uoms,id', 'different:base_uom_id'],
            'package_conversion_qty' => ['nullable', 'integer', 'min:2'],
            'warehouse_settings' => ['nullable', 'array'],
            'warehouse_settings.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'warehouse_settings.*.safety_stock' => ['nullable', 'integer', 'min:0'],
            'warehouse_settings.*.location' => ['nullable', 'string', 'max:255'],
        ]);

        $isBundle = filter_var($request->input('is_bundle', false), FILTER_VALIDATE_BOOLEAN);
        $components = $isBundle ? ($validated['components'] ?? []) : [];

        if ($isBundle && empty($components)) {
            throw ValidationException::withMessages([
                'components' => 'Bundle harus memiliki minimal satu komponen.',
            ]);
        }

        $catId = $request->input('category_id');
        $validated['category_id'] = ($catId === null || (int)$catId === 0) ? null : (int) $catId;
        $validated['is_bundle'] = $isBundle;
        $warehouseSettings = $validated['warehouse_settings'] ?? [];
        $unitPayload = $this->unitPayload($validated);
        unset($validated['components'], $validated['base_unit_name'], $validated['base_uom_id'], $validated['package_unit_name'], $validated['package_uom_id'], $validated['package_conversion_qty'], $validated['warehouse_settings']);

        DB::beginTransaction();
        try {
            $item = Item::create($validated);
            $this->syncWarehouseSettings($item, $warehouseSettings);

            if ($isBundle) {
                $this->syncBundleComponents($item, $components);
            } else {
                $this->ensureWarehouseStocks($item);
                $this->syncItemUnits($item, $unitPayload);
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
            'category_id' => ['nullable', 'integer', 'min:0', function ($attribute, $value, $fail) {
                if ((int) $value !== 0 && !Category::whereKey($value)->exists()) {
                    $fail('Kategori tidak valid.');
                }
            }],
            'description' => ['nullable', 'string'],
            'is_bundle' => ['nullable', 'boolean'],
            'components' => ['nullable', 'array'],
            'components.*.component_item_id' => ['required_with:components', 'integer', 'exists:items,id'],
            'components.*.qty' => ['required_with:components', 'integer', 'min:1'],
            'base_unit_name' => ['nullable', 'string', 'max:30'],
            'base_uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'package_unit_name' => ['nullable', 'string', 'max:30', 'different:base_unit_name'],
            'package_uom_id' => ['nullable', 'integer', 'exists:uoms,id', 'different:base_uom_id'],
            'package_conversion_qty' => ['nullable', 'integer', 'min:2'],
            'warehouse_settings' => ['nullable', 'array'],
            'warehouse_settings.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'warehouse_settings.*.safety_stock' => ['nullable', 'integer', 'min:0'],
            'warehouse_settings.*.location' => ['nullable', 'string', 'max:255'],
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
        $validated['category_id'] = ($catId === null || (int)$catId === 0) ? null : (int) $catId;
        $validated['is_bundle'] = $isBundle;
        $warehouseSettings = $validated['warehouse_settings'] ?? [];
        $unitPayload = $this->unitPayload($validated);
        unset($validated['components'], $validated['base_unit_name'], $validated['base_uom_id'], $validated['package_unit_name'], $validated['package_uom_id'], $validated['package_conversion_qty'], $validated['warehouse_settings']);

        DB::beginTransaction();
        try {
            $item->update($validated);
            $this->syncWarehouseSettings($item, $warehouseSettings);

            if ($isBundle) {
                $this->syncBundleComponents($item, $components);
                // Remove item_stocks row if switching to bundle (stock should be 0 since no activity allowed)
                ItemStock::where('item_id', $item->id)->where('stock', 0)->delete();
            } else {
                // Switching from bundle to regular: ensure item_stocks row exists, clear components
                ItemBundle::where('bundle_item_id', $item->id)->delete();
                $this->ensureWarehouseStocks($item);
                $this->syncItemUnits($item, $unitPayload);
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

            $initialStocksByWarehouse = $import->initialStocks ?? [];
            foreach ($initialStocksByWarehouse as $warehouseId => $initialStocks) {
                if (empty($initialStocks)) {
                    continue;
                }

                $warehouse = Warehouse::findOrFail($warehouseId);
                $transactedAt = now();
                $tx = InboundTransaction::create([
                    'warehouse_id' => $warehouse->id,
                    'code' => 'INB-OPN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                    'type' => 'opening',
                    'ref_no' => null,
                    'note' => 'Saldo awal import items - '.$warehouse->name,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'approved',
                    'approved_at' => $transactedAt,
                    'approved_by' => auth()->id(),
                ]);

                foreach ($initialStocks as $itemId => $stockPayload) {
                    $qtyBase = (int) ($stockPayload['qty_base'] ?? 0);
                    if ($qtyBase <= 0) {
                        continue;
                    }
                    InboundItem::create([
                        'inbound_transaction_id' => $tx->id,
                        'item_id' => $itemId,
                        'unit_id' => $stockPayload['unit_id'] ?? null,
                        'qty_input' => (int) ($stockPayload['qty_input'] ?? $qtyBase),
                        'conversion_qty' => (int) ($stockPayload['conversion_qty'] ?? 1),
                        'qty' => $qtyBase,
                        'note' => 'Saldo awal import '.$warehouse->name,
                    ]);

                    StockService::mutate([
                        'warehouse_id' => $warehouse->id,
                        'item_id' => $itemId,
                        'unit_id' => $stockPayload['unit_id'] ?? null,
                        'direction' => 'in',
                        'qty' => $qtyBase,
                        'qty_input' => (int) ($stockPayload['qty_input'] ?? $qtyBase),
                        'conversion_qty' => (int) ($stockPayload['conversion_qty'] ?? 1),
                        'source_type' => 'inbound',
                        'source_subtype' => 'opening',
                        'source_id' => $tx->id,
                        'source_code' => $tx->code,
                        'note' => 'Saldo awal import '.$warehouse->name,
                        'occurred_at' => $transactedAt,
                        'created_by' => auth()->id(),
                        'idempotency_key' => StockService::idempotencyKey([
                            'stock',
                            'inbound',
                            'opening',
                            $warehouse->id,
                            $tx->id,
                            $itemId,
                        ]),
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

    public function template()
    {
        return Excel::download(new ItemsTemplateExport(), 'template-import-items-multi-gudang.xlsx');
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

    private function unitPayload(array $validated): array
    {
        $baseUom = !empty($validated['base_uom_id']) ? Uom::find($validated['base_uom_id']) : null;
        $packageUom = !empty($validated['package_uom_id']) ? Uom::find($validated['package_uom_id']) : null;
        return [
            'base_name' => $baseUom?->code ?? (strtoupper(trim((string) ($validated['base_unit_name'] ?? 'PCS'))) ?: 'PCS'),
            'base_uom_id' => $baseUom?->id,
            'package_name' => $packageUom?->code ?? strtoupper(trim((string) ($validated['package_unit_name'] ?? ''))),
            'package_uom_id' => $packageUom?->id,
            'package_conversion_qty' => max(2, (int) ($validated['package_conversion_qty'] ?? 2)),
        ];
    }

    private function syncWarehouseSettings(Item $item, array $settings): void
    {
        foreach ($settings as $setting) {
            ItemWarehouseSetting::updateOrCreate(
                [
                    'warehouse_id' => (int) $setting['warehouse_id'],
                    'item_id' => $item->id,
                ],
                [
                    'safety_stock' => max(0, (int) ($setting['safety_stock'] ?? 0)),
                    'location' => trim((string) ($setting['location'] ?? '')) ?: null,
                ]
            );
        }
    }

    private function ensureWarehouseStocks(Item $item): void
    {
        Warehouse::query()->pluck('id')->each(function ($warehouseId) use ($item) {
            ItemStock::firstOrCreate([
                'warehouse_id' => $warehouseId,
                'item_id' => $item->id,
            ], ['stock' => 0]);
        });
    }

    private function syncItemUnits(Item $item, array $payload): void
    {
        $packageName = $payload['package_name'];
        if ($packageName !== '' && strcasecmp($payload['base_name'], $packageName) === 0) {
            throw ValidationException::withMessages([
                'package_unit_name' => 'Satuan dasar dan satuan kemasan harus berbeda.',
            ]);
        }

        $base = ItemUnit::where('item_id', $item->id)->where('is_base', true)->first();
        $existingPackage = ItemUnit::where('item_id', $item->id)->where('is_base', false)->first();
        $packageChanged = $existingPackage && (
            $packageName === ''
            || strcasecmp($existingPackage->name, $packageName) !== 0
            || (int) $existingPackage->conversion_qty !== (int) $payload['package_conversion_qty']
        );
        if ($packageChanged && $this->hasBulkActivity($item->id)) {
            throw ValidationException::withMessages([
                'package_conversion_qty' => 'Satuan atau isi per koli tidak dapat diubah karena item sudah memiliki saldo atau riwayat Gudang Besar.',
            ]);
        }

        ItemUnit::where('item_id', $item->id)
            ->where('is_base', false)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($payload['base_name'])])
            ->delete();

        if ($base) {
            $base->update(['name' => $payload['base_name'], 'uom_id' => $payload['base_uom_id'], 'conversion_qty' => 1]);
        } else {
            $base = ItemUnit::create([
                'item_id' => $item->id,
                'name' => $payload['base_name'],
                'uom_id' => $payload['base_uom_id'],
                'conversion_qty' => 1,
                'is_base' => true,
            ]);
        }

        if ($packageName === '') {
            ItemUnit::where('item_id', $item->id)->where('is_base', false)->delete();
            return;
        }

        ItemUnit::where('item_id', $item->id)
            ->where('is_base', false)
            ->where('name', '!=', $packageName)
            ->delete();
        ItemUnit::updateOrCreate(
            ['item_id' => $item->id, 'name' => $packageName],
            ['uom_id' => $payload['package_uom_id'], 'conversion_qty' => $payload['package_conversion_qty'], 'is_base' => false]
        );
    }

    private function hasBulkActivity(int $itemId): bool
    {
        $bulkWarehouseIds = Warehouse::where('type', Warehouse::TYPE_BULK)->pluck('id');

        return ItemStock::where('item_id', $itemId)
                ->whereIn('warehouse_id', $bulkWarehouseIds)
                ->where('stock', '!=', 0)
                ->exists()
            || DB::table('stock_mutations')
                ->where('item_id', $itemId)
                ->whereIn('warehouse_id', $bulkWarehouseIds)
                ->exists()
            || DB::table('damaged_stock_mutations')
                ->where('item_id', $itemId)
                ->whereIn('warehouse_id', $bulkWarehouseIds)
                ->exists();
    }

    /**
     * Prevent toggling is_bundle if the item already has stock-related history.
     */
    private function assertBundleToggleSafe(Item $item, bool $toBundle): void
    {
        if ($toBundle) {
            // Regular → Bundle: block if item has stock mutations or non-zero stock
            $hasMutations = DB::table('stock_mutations')->where('item_id', $item->id)->exists();
            $hasStock = (int) DB::table('item_stocks')->where('item_id', $item->id)->sum('stock') > 0;
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
                ->sum('stock');
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

}
