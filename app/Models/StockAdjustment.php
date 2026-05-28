<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
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
        return $this->hasMany(StockAdjustmentItem::class, 'stock_adjustment_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
