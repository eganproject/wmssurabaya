<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\ItemWarehouseSetting;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Support\ItemBulkUpdateFields;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Update sebagian field master item berdasarkan SKU.
 * Semua baris divalidasi lebih dulu; jika ada satu saja yang salah,
 * tidak ada perubahan yang disimpan. SKU dan informasi koli tidak pernah diubah.
 */
class ItemsBulkUpdateImport implements ToCollection, WithHeadingRow, WithMultipleSheets, SkipsEmptyRows
{
    private const MAX_REPORTED_ERRORS = 50;

    public int $updated = 0;
    public int $unchanged = 0;

    private array $errors = [];

    /** @var array<string,array<int,int>> nama kategori (lowercase) => daftar id */
    private array $categoryIds = [];

    /** @var array<string,Uom> kode UOM (lowercase) => UOM aktif */
    private array $uoms = [];

    private int $smallWarehouseId = 0;
    private int $largeWarehouseId = 0;

    public function __construct(private readonly array $fields) {}

    /** Hanya sheet pertama ("Update Item") yang diproses; sheet panduan/referensi diabaikan. */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            $this->fail('File tidak berisi baris data. Isi minimal satu baris di bawah header.');
        }

        $headers = array_keys($rows->first()->toArray());
        $missing = array_diff(array_merge([ItemBulkUpdateFields::KEY_COLUMN], $this->fields), $headers);
        if ($missing) {
            $this->fail('Kolom berikut tidak ditemukan di file: '.implode(', ', $missing).'. Pastikan template yang diunduh sesuai dengan field yang dipilih.');
        }

        $this->loadLookups();
        $items = $this->loadItems($rows);

        $plans = [];
        $seenSkus = [];
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $sku = $this->text($row[ItemBulkUpdateFields::KEY_COLUMN] ?? null);
            if ($sku === '') {
                $this->addError($rowNumber, null, 'SKU wajib diisi.');
                continue;
            }

            $skuKey = mb_strtolower($sku);
            if (isset($seenSkus[$skuKey])) {
                $this->addError($rowNumber, $sku, "SKU duplikat (sudah ada di baris {$seenSkus[$skuKey]}).");
                continue;
            }
            $seenSkus[$skuKey] = $rowNumber;

            $item = $items->get($skuKey);
            if (!$item) {
                $this->addError($rowNumber, $sku, 'SKU tidak terdaftar di master item.');
                continue;
            }

            $plan = $this->buildPlan($item, $row, $rowNumber);
            if ($plan !== null) {
                $plans[] = $plan;
            }
        }

        if ($this->errors) {
            $total = count($this->errors);
            $messages = array_slice($this->errors, 0, self::MAX_REPORTED_ERRORS);
            if ($total > self::MAX_REPORTED_ERRORS) {
                $messages[] = '... dan '.($total - self::MAX_REPORTED_ERRORS).' kesalahan lainnya.';
            }
            throw ValidationException::withMessages(['file' => $messages]);
        }

        foreach ($plans as $plan) {
            $this->applyPlan($plan) ? $this->updated++ : $this->unchanged++;
        }
    }

    private function buildPlan(Item $item, Collection $row, int $rowNumber): ?array
    {
        $sku = $item->sku;
        $errorCount = count($this->errors);
        $attributes = [];
        $baseUom = null;
        $settings = [];

        foreach ($this->fields as $field) {
            $raw = $row[$field] ?? null;
            $value = $this->text($raw);

            switch ($field) {
                case 'name':
                    if ($value === '') {
                        $this->addError($rowNumber, $sku, 'Nama item wajib diisi.');
                    } elseif (mb_strlen($value) > 150) {
                        $this->addError($rowNumber, $sku, 'Nama item maksimal 150 karakter.');
                    } else {
                        $attributes['name'] = $value;
                    }
                    break;

                case 'category':
                    if ($value === '') {
                        $attributes['category_id'] = null;
                        break;
                    }
                    $ids = $this->categoryIds[mb_strtolower($value)] ?? [];
                    if (!$ids) {
                        $this->addError($rowNumber, $sku, "Kategori \"{$value}\" tidak terdaftar. Tambahkan dulu di menu Kategori.");
                    } else {
                        // Jika nama kategori kembar, pertahankan kategori item saat ini bila cocok.
                        $attributes['category_id'] = in_array((int) $item->category_id, $ids, true) ? (int) $item->category_id : $ids[0];
                    }
                    break;

                case 'procurement_source':
                    $source = match (mb_strtolower($value)) {
                        'nanggewer', 'produksi', 'production', 'nanggewer (produksi)' => Item::PROCUREMENT_NANGGEWER,
                        'import', 'impor' => Item::PROCUREMENT_IMPORT,
                        default => null,
                    };
                    if ($source === null) {
                        $this->addError($rowNumber, $sku, 'Sumber pengadaan harus nanggewer atau import.');
                    } else {
                        $attributes['procurement_source'] = $source;
                    }
                    break;

                case 'sale_status':
                    $saleStatus = match (mb_strtolower($value)) {
                        'lanjut jual', 'lanjut_jual' => 'lanjut_jual',
                        'tidak lanjut jual', 'tidak_lanjut_jual' => 'tidak_lanjut_jual',
                        default => null,
                    };
                    if ($saleStatus === null) {
                        $this->addError($rowNumber, $sku, 'Status jual harus Lanjut Jual atau Tidak Lanjut Jual.');
                    } else {
                        $attributes['sale_status'] = $saleStatus;
                    }
                    break;

                case 'status':
                    $status = match (mb_strtolower($value)) {
                        'aktif', 'active', '1', 'ya', 'yes', 'true' => true,
                        'nonaktif', 'non aktif', 'non-aktif', 'tidak aktif', 'inactive', '0', 'tidak', 'no', 'false' => false,
                        default => null,
                    };
                    if ($status === null) {
                        $this->addError($rowNumber, $sku, 'Status harus aktif atau nonaktif.');
                    } else {
                        $attributes['is_active'] = $status;
                    }
                    break;

                case 'description':
                    $attributes['description'] = $value !== '' ? $value : null;
                    break;

                case 'base_unit':
                    if ($item->is_bundle) {
                        break; // Item bundle tidak memiliki satuan sendiri.
                    }
                    if ($value === '') {
                        $this->addError($rowNumber, $sku, 'UOM dasar wajib diisi.');
                        break;
                    }
                    $uom = $this->uoms[mb_strtolower($value)] ?? null;
                    $packageUnit = $item->units->firstWhere('is_base', false);
                    if (!$uom) {
                        $this->addError($rowNumber, $sku, "UOM dasar \"{$value}\" tidak terdaftar atau tidak aktif.");
                    } elseif ($packageUnit && strcasecmp($packageUnit->name, $uom->code) === 0) {
                        $this->addError($rowNumber, $sku, "UOM dasar tidak boleh sama dengan satuan kemasan ({$packageUnit->name}).");
                    } else {
                        $baseUom = $uom;
                    }
                    break;

                case 'koli_length_cm':
                case 'koli_width_cm':
                case 'koli_height_cm':
                    try {
                        $attributes[$field] = Item::parseKoliDimension($raw);
                    } catch (\InvalidArgumentException $e) {
                        $this->addError($rowNumber, $sku, ItemBulkUpdateFields::all()[$field]['label'].' '.$e->getMessage().'.');
                    }
                    break;

                case 'small_warehouse_safety_stock':
                case 'large_warehouse_safety_stock':
                    $number = $this->nonNegativeInt($raw);
                    if ($number === null) {
                        $this->addError($rowNumber, $sku, 'Safety stock harus angka bulat 0 atau lebih.');
                    } else {
                        $settings[$this->warehouseFor($field)]['safety_stock'] = $number;
                    }
                    break;

                case 'small_warehouse_location':
                case 'large_warehouse_location':
                    if (mb_strlen($value) > 255) {
                        $this->addError($rowNumber, $sku, 'Lokasi maksimal 255 karakter.');
                    } else {
                        $settings[$this->warehouseFor($field)]['location'] = $value !== '' ? $value : null;
                    }
                    break;
            }
        }

        if (count($this->errors) > $errorCount) {
            return null;
        }

        return compact('item', 'attributes', 'baseUom', 'settings');
    }

    /** @return bool true jika ada data yang benar-benar berubah. */
    private function applyPlan(array $plan): bool
    {
        /** @var Item $item */
        $item = $plan['item'];
        $changed = false;

        $item->fill($plan['attributes']);
        if ($item->isDirty()) {
            $item->save();
            $changed = true;
        }

        if ($uom = $plan['baseUom']) {
            $base = $item->units->firstWhere('is_base', true)
                ?? new ItemUnit(['item_id' => $item->id, 'is_base' => true, 'conversion_qty' => 1]);
            $base->fill(['name' => $uom->code, 'uom_id' => $uom->id]);
            if (!$base->exists || $base->isDirty()) {
                $base->save();
                $changed = true;
            }
        }

        foreach ($plan['settings'] as $warehouseId => $values) {
            $setting = $item->warehouseSettings->firstWhere('warehouse_id', $warehouseId)
                ?? new ItemWarehouseSetting(['warehouse_id' => $warehouseId, 'item_id' => $item->id, 'safety_stock' => 0]);
            $setting->fill($values);
            if (!$setting->exists || $setting->isDirty()) {
                $setting->save();
                $changed = true;
            }
        }

        return $changed;
    }

    private function loadLookups(): void
    {
        if (in_array('category', $this->fields, true)) {
            Category::query()->orderBy('id')->get(['id', 'name'])->each(function ($category) {
                $this->categoryIds[mb_strtolower(trim((string) $category->name))][] = (int) $category->id;
            });
        }

        if (in_array('base_unit', $this->fields, true)) {
            $this->uoms = Uom::where('is_active', true)->get()
                ->keyBy(fn ($uom) => mb_strtolower($uom->code))
                ->all();
        }

        $this->smallWarehouseId = Warehouse::defaultId();
        $this->largeWarehouseId = (int) Warehouse::where('type', Warehouse::TYPE_BULK)
            ->where('is_active', true)
            ->value('id');

        $usesSmall = (bool) array_intersect(['small_warehouse_safety_stock', 'small_warehouse_location'], $this->fields);
        $usesLarge = (bool) array_intersect(['large_warehouse_safety_stock', 'large_warehouse_location'], $this->fields);
        if ($usesSmall && $this->smallWarehouseId <= 0) {
            $this->fail('Gudang Kecil (gudang default) tidak ditemukan.');
        }
        if ($usesLarge && $this->largeWarehouseId <= 0) {
            $this->fail('Gudang Besar aktif tidak ditemukan.');
        }
    }

    /** @return Collection<string,Item> keyed by SKU lowercase */
    private function loadItems(Collection $rows): Collection
    {
        $skus = $rows->map(fn ($row) => mb_strtolower($this->text($row[ItemBulkUpdateFields::KEY_COLUMN] ?? null)))
            ->filter(fn ($sku) => $sku !== '')
            ->unique()
            ->values();

        // SKU dicocokkan tanpa membedakan huruf besar/kecil, apa pun collation database-nya.
        return $skus->chunk(1000)
            ->flatMap(fn ($chunk) => Item::with(['units', 'warehouseSettings'])
                ->whereIn(DB::raw('LOWER(sku)'), $chunk->all())
                ->get())
            ->keyBy(fn ($item) => mb_strtolower($item->sku));
    }

    private function warehouseFor(string $field): int
    {
        return str_starts_with($field, 'small_') ? $this->smallWarehouseId : $this->largeWarehouseId;
    }

    /** Excel sering membaca angka sebagai float (mis. 101.0); ubah ke teks tanpa desimal. */
    private function text(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) ($value ?? ''));
    }

    private function nonNegativeInt(mixed $value): ?int
    {
        $text = $this->text($value);
        if ($text === '') {
            return 0;
        }

        return preg_match('/^\d+$/', $text) ? (int) $text : null;
    }

    private function addError(int $rowNumber, ?string $sku, string $message): void
    {
        $this->errors[] = "Baris {$rowNumber}".($sku !== null ? " (SKU {$sku})" : '').": {$message}";
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => [$message]]);
    }
}
