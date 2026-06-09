<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'outbound_transaction_id',
        'item_id',
        'unit_id',
        'qty_input',
        'conversion_qty',
        'stock_source',
        'qty',
        'note',
    ];

    public function transaction()
    {
        return $this->belongsTo(OutboundTransaction::class, 'outbound_transaction_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function unit()
    {
        return $this->belongsTo(ItemUnit::class, 'unit_id');
    }
}
