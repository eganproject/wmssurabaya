<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundTransaction extends Model
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
        'scan_status',
        'scan_completed_at',
        'approved_at',
        'created_by',
        'scan_completed_by',
        'approved_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'scan_completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OutboundTransaction $transaction) {
            $transaction->warehouse_id ??= Warehouse::defaultId();
        });
    }

    public function items()
    {
        return $this->hasMany(OutboundItem::class, 'outbound_transaction_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scanCompleter()
    {
        return $this->belongsTo(User::class, 'scan_completed_by');
    }

    public function suratJalan()
    {
        return $this->hasOne(SuratJalan::class, 'outbound_transaction_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function manualScanLogs()
    {
        return $this->hasMany(OutboundManualScanLog::class, 'outbound_transaction_id');
    }
}
