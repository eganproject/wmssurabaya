<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'item_id',
        'unit_id',
        'qty_input',
        'conversion_qty',
        'qty_base',
        'qty_received_base',
        'received_unit_id',
        'qty_received_unit',
        'qty_discrepancy_base',
        'note',
        'discrepancy_note',
    ];

    public function transfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function unit()
    {
        return $this->belongsTo(ItemUnit::class, 'unit_id');
    }

    public function receivedUnit()
    {
        return $this->belongsTo(ItemUnit::class, 'received_unit_id');
    }
}
