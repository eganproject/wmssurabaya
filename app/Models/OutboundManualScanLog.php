<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundManualScanLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'outbound_transaction_id',
        'outbound_item_id',
        'item_id',
        'scanned_sku',
        'qty',
        'scanned_at',
        'scanned_by',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(OutboundTransaction::class, 'outbound_transaction_id');
    }

    public function outboundItem()
    {
        return $this->belongsTo(OutboundItem::class, 'outbound_item_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
