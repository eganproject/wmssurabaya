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

    private ?int $defaultCategoryId = null;

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
                'file' => 'Header wajib: sku, name. Kolom kategori, satuan, stok gudang, safety_stock, address, dan description bersifat opsional.',
            ]);
        }

        $seenSkus = [];
        $hasCategoryColumns = count(array_intersect(['parent_category', 'category'], $headers)) > 0;
        $hasDescriptionColumn = in_array('description', $headers, true);
        $hasAddressColumn = in_array('address', $headers, true);
        $hasBaseUnitColumn = count(array_intersect(['base_unit', 'satuan_dasar', 'unit_dasar'], $headers)) > 0;
        $hasPackageUnitColumn = count(array_intersect(['package_unit', 'satuan_kemasan', 'unit_kemasan'], $headers)) > 0;
        $hasSmallSettingColumns = count(array_intersect([
            'safety_stock', 'stok_pengaman', 'stock_pengaman', 'min_stock', 'minimum_stock',
            'address', 'small_warehouse_safety_stock', 'small_safety_stock',
            'small_warehouse_location', 'small_location',
        ], $headers)) > 0;
        $hasLargeSettingColumns = count(array_intersect([
            'large_warehouse_safety_stock', 'large_safety_stock',
            'large_warehouse_location', 'large_location',
        ], $headers)) > 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $parentCategoryName = trim((string) ($row['parent_category'] ?? ''));
            $categoryName = trim((string) ($row['category'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $address = isset($row['address']) ? trim((string) ($row['address'] ?? '')) : '';
            $smallStock = $this->parsePositiveInt($row, [
                'small_warehouse_stock',
                'stock_gudang_kecil',
                'stok_gudang_kecil',
                'small_stock',
                'stock',
                'stok',
                'qty',
            ]);
            $largeStockInput = $this->parsePositiveInt($row, [
                'large_warehouse_stock',
                'stock_gudang_besar',
                'stok_gudang_besar',
                'bulk_stock',
            ]);
            $baseUnitName = strtoupper(trim((string) ($row['base_unit'] ?? $row['satuan_dasar'] ?? $row['unit_dasar'] ?? '')));
            $packageUnitName = strtoupper(trim((string) ($row['package_unit'] ?? $row['satuan_kemasan'] ?? $row['unit_kemasan'] ?? '')));
            $packageConversion = $this->parsePositiveInt($row, [
                'package_conversion_qty',
                'isi_per_kemasan',
                'konversi_kemasan',
                'conversion_qty',
            ]);
            $safetyStock = $this->parseSafetyStock($row);

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
            if ($packageUnitName !== '' && $packageConversion < 2) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$rowNumber} SKU {$sku}: package_conversion_qty/isi_per_kemasan wajib minimal 2 jika satuan kemasan diisi.",
                ]);
            }

            $payload = [
                'name' => $name,
            ];
            if ($hasCategoryColumns) {
                $parentCategoryId = 0;
                if ($parentCategoryName !== '') {
                    $parentCategory = $this->findOrCreateCategory($parentCategoryName, 0);
                    $parentCategoryId = $parentCategory?->id ?? 0;
                }
                $catId = $this->getDefaultCategoryId();
                if ($categoryName !== '') {
                    $category = $this->findOrCreateCategory($categoryName, $parentCategoryId);
                    $catId = $category?->id ?? $catId;
                }
                $payload['category_id'] = $catId;
            }
            if ($hasDescriptionColumn) {
                $payload['description'] = $description;
            }
            if (isset($row['address'])) {
                $payload['address'] = $address;
            }
            if ($safetyStock !== null) {
                $payload['safety_stock'] = $safetyStock;
            }

            $item = Item::where('sku', $sku)->first();
            $isNewItem = !$item;
            if ($isNewItem) {
                $item = Item::create(['sku' => $sku, 'category_id' => 0] + $payload);
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
                $packageUnit = ItemUnit::updateOrCreate(
                    ['item_id' => $item->id, 'name' => $packageUnitName, 'is_base' => false],
                    [
                        'conversion_qty' => max(2, $packageConversion),
                    ]
                );
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
            $smallSafety = $this->parseOptionalInt($row, ['small_warehouse_safety_stock', 'small_safety_stock']);
            $largeSafety = $this->parseOptionalInt($row, ['large_warehouse_safety_stock', 'large_safety_stock']);
            $smallLocation = $this->firstString($row, ['small_warehouse_location', 'small_location']);
            $largeLocation = $this->firstString($row, ['large_warehouse_location', 'large_location']);
            if ($isNewItem || $hasSmallSettingColumns) {
                ItemWarehouseSetting::updateOrCreate(
                    ['warehouse_id' => $smallWarehouseId, 'item_id' => $item->id],
                    [
                        'safety_stock' => $smallSafety ?? $safetyStock ?? (int) ($item->safety_stock ?? 0),
                        'location' => $smallLocation ?? ($hasAddressColumn ? $address : $item->address),
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

    protected function parseSafetyStock($row): ?int
    {
        $raw = null;
        $hasKey = false;
        foreach (['safety_stock', 'stok_pengaman', 'stock_pengaman', 'min_stock', 'minimum_stock'] as $key) {
            if (is_array($row) && array_key_exists($key, $row)) {
                $raw = $row[$key];
                $hasKey = true;
                break;
            }
            if ($row instanceof Collection && $row->has($key)) {
                $raw = $row->get($key);
                $hasKey = true;
                break;
            }
            if (isset($row[$key])) {
                $raw = $row[$key];
                $hasKey = true;
                break;
            }
        }
        if (!$hasKey) {
            return null;
        }
        if ($raw === null || $raw === '') {
            return 0;
        }
        $value = is_numeric($raw) ? (int) $raw : (int) preg_replace('/[^0-9\-]/', '', (string) $raw);
        return $value > 0 ? $value : 0;
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
