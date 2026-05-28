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
}
