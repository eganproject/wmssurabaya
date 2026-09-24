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
        'description',
        'is_bundle',
        'is_active',
    ];

    protected $casts = [
        'is_bundle' => 'boolean',
        'is_active' => 'boolean',
    ];

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
