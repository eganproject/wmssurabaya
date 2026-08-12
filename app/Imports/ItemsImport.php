<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ItemUnit;
use App\Models\ItemWarehouseSetting;
use App\Models\StockMutation;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $created = 0;
    public int $updated = 0;
    /**
     * @var array<int,array<int,array{
     *     qty_base:int,
     *     qty_input:int,
     *     unit_id:?int,
     *     conversion_qty:int
     * }>>
     */
    public array $initialStocks = [];

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File kosong',
            ]);
        }

        $first = $rows->first();
        $headers = array_keys($first?->toArray() ?? []);
        $required = ['sku', 'name'];
        if (array_diff($required, $headers)) {
            throw ValidationException::withMessages([
                'file' => 'Header wajib: sku dan name.',
            ]);
        }
        $ambiguousHeaders = array_intersect([
            'stock', 'stok', 'qty', 'bulk_stock',
            'safety_stock', 'stok_pengaman', 'stock_pengaman', 'min_stock', 'minimum_stock',
            'address',
        ], $headers);
        if ($ambiguousHeaders) {
            throw ValidationException::withMessages([
                'file' => 'Header ambigu tidak didukung: '.implode(', ', $ambiguousHeaders).'. Gunakan kolom small_warehouse_* atau large_warehouse_* yang eksplisit.',
            ]);
        }

        $seenSkus = [];
        $hasCategoryColumns = count(array_intersect(['parent_category', 'category'], $headers)) > 0;
        $hasDescriptionColumn = in_array('description', $headers, true);
        $hasBaseUnitColumn = count(array_intersect(['base_unit', 'satuan_dasar', 'unit_dasar'], $headers)) > 0;
        $hasPackageUnitColumn = count(array_intersect(['package_unit', 'satuan_kemasan', 'unit_kemasan'], $headers)) > 0;
        $hasSmallSettingColumns = count(array_intersect([
            'small_warehouse_safety_stock',
            'small_warehouse_location',
        ], $headers)) > 0;
        $hasLargeSettingColumns = count(array_intersect([
            'large_warehouse_safety_stock',
            'large_warehouse_location',
        ], $headers)) > 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $parentCategoryName = trim((string) ($row['parent_category'] ?? ''));
            $categoryName = trim((string) ($row['category'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $smallStock = $this->parsePositiveInt($row, [
                'small_warehouse_stock',
            ]);
            $largeStockInput = $this->parsePositiveInt($row, [
                'large_warehouse_stock',
            ]);
            $baseUnitName = strtoupper(trim((string) ($row['base_unit'] ?? $row['satuan_dasar'] ?? $row['unit_dasar'] ?? '')));
            $packageUnitName = strtoupper(trim((string) ($row['package_unit'] ?? $row['satuan_kemasan'] ?? $row['unit_kemasan'] ?? '')));
            $packageConversion = $this->parsePositiveInt($row, [
                'package_conversion_qty',
                'isi_per_kemasan',
                'konversi_kemasan',
                'conversion_qty',
            ]);
            if ($sku === '' || $name === '') {
                continue;
            }
            $normalizedSku = mb_strtolower($sku);
            if (isset($seenSkus[$normalizedSku])) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$rowNumber} SKU {$sku}: SKU duplikat dalam file import.",
                ]);
            }
            $seenSkus[$normalizedSku] = true;

            if ($largeStockInput > 0) {
                $packageUnitName = $packageUnitName ?: 'KOLI';
            }
            if ($baseUnitName !== '' && $packageUnitName !== '' && strcasecmp($baseUnitName, $packageUnitName) === 0) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$rowNumber} SKU {$sku}: satuan dasar dan satuan kemasan harus berbeda.",
                ]);
            }
            if ($packageUnitName !== '' && $packageConversion < 1) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$rowNumber} SKU {$sku}: package_conversion_qty/isi_per_kemasan wajib minimal 1 jika satuan kemasan diisi.",
                ]);
            }

            $payload = [
                'name' => $name,
            ];
            if ($hasCategoryColumns) {
                $catId = null;
                if ($categoryName !== '') {
                    $parentCategoryId = null;
                    if ($parentCategoryName !== '') {
                        $parentCategory = $this->findOrCreateCategory($parentCategoryName);
                        $parentCategoryId = $parentCategory?->id;
                    }
                    $category = $this->findOrCreateCategory($categoryName, $parentCategoryId);
                    $catId = $category?->id;
                }
                $payload['category_id'] = $catId;
            }
            if ($hasDescriptionColumn) {
                $payload['description'] = $description;
            }
            $item = Item::where('sku', $sku)->first();
            $isNewItem = !$item;
            if ($isNewItem) {
                $item = Item::create(['sku' => $sku, 'category_id' => null] + $payload);
            } else {
                $item->update($payload);
            }

            $baseUnit = ItemUnit::where('item_id', $item->id)->where('is_base', true)->first();
            $effectiveBaseName = $baseUnitName !== ''
                ? $baseUnitName
                : ($baseUnit?->name ?? 'PCS');
            $conflictingUnit = ItemUnit::where('item_id', $item->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($effectiveBaseName)])
                ->when($baseUnit, fn ($q) => $q->where('id', '!=', $baseUnit->id))
                ->exists();
            if ($conflictingUnit) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$rowNumber} SKU {$sku}: satuan dasar {$effectiveBaseName} sudah digunakan sebagai satuan kemasan.",
                ]);
            }
            if (!$baseUnit) {
                $baseUnit = ItemUnit::create([
                    'item_id' => $item->id,
                    'name' => $effectiveBaseName,
                    'conversion_qty' => 1,
                    'is_base' => true,
                ]);
            } elseif ($hasBaseUnitColumn && $baseUnitName !== '') {
                $baseUnit->update(['name' => $effectiveBaseName, 'conversion_qty' => 1]);
            }

            $packageUnit = null;
            if ($packageUnitName !== '') {
                if (strcasecmp($effectiveBaseName, $packageUnitName) === 0) {
                    throw ValidationException::withMessages([
                        'file' => "Baris {$rowNumber} SKU {$sku}: satuan dasar dan satuan kemasan harus berbeda.",
                    ]);
                }
                $packageUnit = ItemUnit::where('item_id', $item->id)->where('is_base', false)->first();
                if ($packageUnit) {
                    $packageChanged = strcasecmp($packageUnit->name, $packageUnitName) !== 0
                        || (int) $packageUnit->conversion_qty !== max(1, $packageConversion);
                    if ($packageChanged && $this->hasBulkStockBalance($item->id)) {
                        throw ValidationException::withMessages([
                            'file' => "Baris {$rowNumber} SKU {$sku}: satuan atau isi per koli tidak dapat diubah karena masih ada saldo di Gudang Besar.",
                        ]);
                    }
                    $packageUnit->update([
                        'name' => $packageUnitName,
                        'conversion_qty' => max(1, $packageConversion),
                    ]);
                } else {
                    $packageUnit = ItemUnit::create([
                        'item_id' => $item->id,
                        'name' => $packageUnitName,
                        'conversion_qty' => max(1, $packageConversion),
                        'is_base' => false,
                    ]);
                }
            } elseif (!$hasPackageUnitColumn && $largeStockInput > 0) {
                $packageUnit = ItemUnit::where('item_id', $item->id)
                    ->where('is_base', false)
                    ->orderBy('conversion_qty')
                    ->first();
            }

            $smallWarehouseId = Warehouse::defaultId();
            $largeWarehouseId = (int) Warehouse::where('type', Warehouse::TYPE_BULK)
                ->where('is_active', true)
                ->value('id');
            if ($largeStockInput > 0 && $largeWarehouseId <= 0) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$rowNumber} SKU {$sku}: Gudang Besar aktif tidak ditemukan.",
                ]);
            }
            ItemStock::firstOrCreate([
                'warehouse_id' => $smallWarehouseId,
                'item_id' => $item->id,
            ], ['stock' => 0]);
            if ($largeWarehouseId > 0) {
                ItemStock::firstOrCreate([
                    'warehouse_id' => $largeWarehouseId,
                    'item_id' => $item->id,
                ], ['stock' => 0]);
            }
            $smallSafety = $this->parseOptionalInt($row, ['small_warehouse_safety_stock']);
            $largeSafety = $this->parseOptionalInt($row, ['large_warehouse_safety_stock']);
            $smallLocation = $this->firstString($row, ['small_warehouse_location']);
            $largeLocation = $this->firstString($row, ['large_warehouse_location']);
            if ($isNewItem || $hasSmallSettingColumns) {
                ItemWarehouseSetting::updateOrCreate(
                    ['warehouse_id' => $smallWarehouseId, 'item_id' => $item->id],
                    [
                        'safety_stock' => $smallSafety ?? 0,
                        'location' => $smallLocation,
                    ]
                );
            }
            if ($largeWarehouseId > 0 && ($isNewItem || $hasLargeSettingColumns)) {
                ItemWarehouseSetting::updateOrCreate(
                    ['warehouse_id' => $largeWarehouseId, 'item_id' => $item->id],
                    [
                        'safety_stock' => $largeSafety ?? 0,
                        'location' => $largeLocation,
                    ]
                );
            }
            $isNewItem ? $this->created++ : $this->updated++;

            if (($smallStock > 0 || $largeStockInput > 0) && !$isNewItem) {
                $hasStockHistory = StockMutation::where('item_id', $item->id)->exists();
                $hasCurrentStock = ItemStock::where('item_id', $item->id)->where('stock', '!=', 0)->exists();
                if ($hasStockHistory || $hasCurrentStock) {
                    throw ValidationException::withMessages([
                        'file' => "Baris {$rowNumber} SKU {$sku}: stok awal tidak boleh diimport ulang karena item sudah memiliki saldo atau riwayat mutasi.",
                    ]);
                }
            }

            if ($smallStock > 0) {
                $this->addInitialStock(
                    $smallWarehouseId,
                    $item->id,
                    $smallStock,
                    $smallStock,
                    $baseUnit->id,
                    1
                );
            }
            if ($largeStockInput > 0 && $largeWarehouseId > 0 && $packageUnit) {
                $this->addInitialStock(
                    $largeWarehouseId,
                    $item->id,
                    $largeStockInput * $packageUnit->conversion_qty,
                    $largeStockInput,
                    $packageUnit->id,
                    $packageUnit->conversion_qty
                );
            }
        }
    }

    protected function parsePositiveInt($row, array $keys): int
    {
        $raw = null;
        foreach ($keys as $key) {
            if (is_array($row) && array_key_exists($key, $row)) {
                $raw = $row[$key];
                break;
            }
            if ($row instanceof Collection && $row->has($key)) {
                $raw = $row->get($key);
                break;
            }
            if (isset($row[$key])) {
                $raw = $row[$key];
                break;
            }
        }
        if ($raw === null || $raw === '') {
            return 0;
        }
        $value = is_numeric($raw) ? (int) $raw : (int) preg_replace('/[^0-9\-]/', '', (string) $raw);
        return $value > 0 ? $value : 0;
    }

    protected function addInitialStock(
        int $warehouseId,
        int $itemId,
        int $qtyBase,
        int $qtyInput,
        ?int $unitId,
        int $conversionQty
    ): void {
        $existing = $this->initialStocks[$warehouseId][$itemId] ?? null;
        if ($existing) {
            $qtyBase += $existing['qty_base'];
            $qtyInput += $existing['qty_input'];
        }

        $this->initialStocks[$warehouseId][$itemId] = [
            'qty_base' => $qtyBase,
            'qty_input' => $qtyInput,
            'unit_id' => $unitId,
            'conversion_qty' => $conversionQty,
        ];
    }

    protected function parseOptionalInt($row, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (($row instanceof Collection && $row->has($key)) || (is_array($row) && array_key_exists($key, $row))) {
                $raw = $row[$key];
                if ($raw === null || $raw === '') {
                    return 0;
                }
                return max(0, (int) $raw);
            }
        }
        return null;
    }

    protected function firstString($row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (($row instanceof Collection && $row->has($key)) || (is_array($row) && array_key_exists($key, $row))) {
                return trim((string) ($row[$key] ?? '')) ?: null;
            }
        }
        return null;
    }

    protected function findOrCreateCategory(string $name, ?int $parentId = null): ?Category
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }
        $normalized = mb_strtolower($trimmed);
        $category = Category::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($category) {
            if ($parentId !== null && $category->parent_id !== $parentId) {
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

    /**
     * Isi per koli hanya dikunci selama masih ada saldo berjalan di Gudang Besar.
     * Riwayat mutasi tidak ikut mengunci karena tiap mutasi menyimpan
     * conversion_qty-nya sendiri, sehingga histori lama tetap akurat.
     */
    protected function hasBulkStockBalance(int $itemId): bool
    {
        $bulkWarehouseIds = Warehouse::where('type', Warehouse::TYPE_BULK)->pluck('id');

        return ItemStock::where('item_id', $itemId)
                ->whereIn('warehouse_id', $bulkWarehouseIds)
                ->where('stock', '!=', 0)
                ->exists()
            || DB::table('damaged_item_stocks')
                ->where('item_id', $itemId)
                ->whereIn('warehouse_id', $bulkWarehouseIds)
                ->where('stock', '!=', 0)
                ->exists();
    }

}
