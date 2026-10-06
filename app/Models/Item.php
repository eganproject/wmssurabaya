<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Item extends Model
{
    use HasFactory;

    public const PROCUREMENT_NANGGEWER = 'nanggewer';

    public const PROCUREMENT_IMPORT = 'import';

    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'procurement_source',
        'sale_status',
        'koli_length_cm',
        'koli_width_cm',
        'koli_height_cm',
        'description',
        'is_bundle',
        'is_active',
    ];

    protected $casts = [
        'is_bundle' => 'boolean',
        'is_active' => 'boolean',
        'koli_length_cm' => 'float',
        'koli_width_cm' => 'float',
        'koli_height_cm' => 'float',
    ];

    /** Kolom dimensi koli (cm) yang dipakai untuk menghitung CBM per koli. */
    public const KOLI_DIMENSION_FIELDS = ['koli_length_cm', 'koli_width_cm', 'koli_height_cm'];

    /** Batas atas dimensi (cm), menyesuaikan kolom decimal(8,2). */
    public const KOLI_DIMENSION_MAX = 99999.99;

    /**
     * CBM (m³) per koli = P × L × T (cm) / 1.000.000.
     * Null jika salah satu dimensi belum diisi.
     */
    public function getCbmPerKoliAttribute(): ?float
    {
        if ($this->koli_length_cm === null || $this->koli_width_cm === null || $this->koli_height_cm === null) {
            return null;
        }

        return round($this->koli_length_cm * $this->koli_width_cm * $this->koli_height_cm / 1_000_000, 6);
    }

    /**
     * Ubah nilai dimensi dari Excel menjadi cm (2 desimal). Kosong berarti null;
     * koma diterima sebagai pemisah desimal (mis. "30,5").
     *
     * @throws \InvalidArgumentException jika bukan angka > 0 atau melebihi batas.
     */
    public static function parseKoliDimension(mixed $raw): ?float
    {
        if (is_int($raw) || is_float($raw)) {
            $value = (float) $raw;
        } else {
            $text = str_replace(' ', '', trim((string) ($raw ?? '')));
            if ($text === '') {
                return null;
            }
            if (!str_contains($text, '.')) {
                $text = str_replace(',', '.', $text);
            }
            if (!is_numeric($text)) {
                throw new \InvalidArgumentException('harus berupa angka (cm)');
            }
            $value = (float) $text;
        }

        $value = round($value, 2);
        if ($value <= 0) {
            throw new \InvalidArgumentException('harus lebih dari 0 cm');
        }
        if ($value > self::KOLI_DIMENSION_MAX) {
            throw new \InvalidArgumentException('maksimal '.self::KOLI_DIMENSION_MAX.' cm');
        }

        return $value;
    }

    public static function saleStatuses(): array
    {
        return ['lanjut_jual' => 'Lanjut Jual', 'tidak_lanjut_jual' => 'Tidak Lanjut Jual'];
    }

    public function getSaleStatusLabelAttribute(): string
    {
        return self::saleStatuses()[$this->sale_status] ?? 'Lanjut Jual';
    }

    public static function procurementSources(): array
    {
        return [
            self::PROCUREMENT_NANGGEWER => 'Nanggewer (Produksi)',
            self::PROCUREMENT_IMPORT => 'Import',
        ];
    }

    public function getProcurementSourceLabelAttribute(): string
    {
        return self::procurementSources()[$this->procurement_source]
            ?? self::procurementSources()[self::PROCUREMENT_NANGGEWER];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function stock()
    {
        return $this->hasOne(ItemStock::class, 'item_id')
            ->where('warehouse_id', Warehouse::defaultId());
    }

    public function stocks()
    {
        return $this->hasMany(ItemStock::class, 'item_id');
    }

    public function units()
    {
        return $this->hasMany(ItemUnit::class);
    }

    public function warehouseSettings()
    {
        return $this->hasMany(ItemWarehouseSetting::class);
    }

    public function baseUnit()
    {
        return $this->hasOne(ItemUnit::class)->where('is_base', true);
    }

    public function packageUnit()
    {
        return $this->hasOne(ItemUnit::class)->where('is_base', false);
    }

    /** Components that make up this bundle (only meaningful when is_bundle = true). */
    public function bundleComponents()
    {
        return $this->hasMany(ItemBundle::class, 'bundle_item_id')->with('componentItem');
    }

    /** Bundles that include this item as a component. */
    public function bundleParents()
    {
        return $this->hasMany(ItemBundle::class, 'component_item_id');
    }
}
