<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedStockMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'item_id',
        'direction',
        'qty',
        'source_type',
        'source_subtype',
        'source_id',
        'source_code',
        'note',
        'occurred_at',
        'created_by',
        'idempotency_key',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
