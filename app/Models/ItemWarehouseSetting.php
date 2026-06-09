<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemWarehouseSetting extends Model
{
    protected $fillable = [
        'warehouse_id',
        'item_id',
        'safety_stock',
        'location',
    ];

    protected $casts = [
        'safety_stock' => 'integer',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
