<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'address',
        'description',
        'safety_stock',
        'is_bundle',
    ];

    protected $casts = [
        'safety_stock' => 'integer',
        'is_bundle' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function stock()
    {
        return $this->hasOne(ItemStock::class, 'item_id');
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
