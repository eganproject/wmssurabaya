<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedGood extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'source_type',
        'source_ref',
        'inbound_transaction_id',
        'transacted_at',
        'note',
        'status',
        'approved_at',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(DamagedGoodItem::class, 'damaged_good_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function inboundTransaction()
    {
        return $this->belongsTo(InboundTransaction::class, 'inbound_transaction_id');
    }
}
