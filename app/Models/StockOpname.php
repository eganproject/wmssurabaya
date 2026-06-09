<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'code',
        'transacted_at',
        'note',
        'status',
        'completed_at',
        'created_by',
        'completed_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockOpname $opname) {
            $opname->warehouse_id ??= Warehouse::defaultId();
        });
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
