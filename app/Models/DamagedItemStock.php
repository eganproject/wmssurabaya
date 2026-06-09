<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedItemStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'item_id',
        'stock',
        'reserved_stock',
    ];

    protected static function booted(): void
    {
        static::creating(function (DamagedItemStock $stock) {
            $stock->warehouse_id ??= Warehouse::defaultId();
        });
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
