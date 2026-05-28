<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QcScanResi extends Model
{
    protected $fillable = [
        'resi_id',
        'status',
        'scanned_at',
        'scanned_by',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function resi()
    {
        return $this->belongsTo(Resi::class, 'resi_id');
    }

    public function items()
    {
        return $this->hasMany(QcScanResiItem::class, 'qc_scan_resi_id');
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
