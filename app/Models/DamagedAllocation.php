<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'allocation_type',
        'ref_no',
        'transacted_at',
        'note',
        'status',
        'approved_at',
        'created_by',
        'approved_by',
        'outbound_transaction_id',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(DamagedAllocationItem::class, 'damaged_allocation_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function outboundTransaction()
    {
        return $this->belongsTo(OutboundTransaction::class, 'outbound_transaction_id');
    }
}
