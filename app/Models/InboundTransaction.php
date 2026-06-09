<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboundTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'code',
        'type',
        'ref_no',
        'transacted_at',
        'note',
        'status',
        'approved_at',
        'finalized_at',
        'created_by',
        'approved_by',
        'finalized_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'approved_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (InboundTransaction $transaction) {
            $transaction->warehouse_id ??= Warehouse::defaultId();
        });
    }

    public function items()
    {
        return $this->hasMany(InboundItem::class, 'inbound_transaction_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
