<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemBundle extends Model
{
    protected $fillable = [
        'bundle_item_id',
        'component_item_id',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function bundleItem()
    {
        return $this->belongsTo(Item::class, 'bundle_item_id');
    }

    public function componentItem()
    {
        return $this->belongsTo(Item::class, 'component_item_id');
    }
}
