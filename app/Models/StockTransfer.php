<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'source_warehouse_id',
        'destination_warehouse_id',
        'status',
        'transacted_at',
        'shipped_at',
        'received_at',
        'cancelled_at',
        'note',
        'discrepancy_note',
        'created_by',
        'shipped_by',
        'received_by',
        'cancelled_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function sourceWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }
}
