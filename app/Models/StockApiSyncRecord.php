<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockApiSyncRecord extends Model
{
    protected $fillable = [
        'warehouse_id', 'item_id', 'sku', 'name', 'category', 'uom',
        'qty', 'min_qty', 'status', 'source_updated_at',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'min_qty' => 'decimal:3',
        'source_updated_at' => 'datetime',
    ];
}
