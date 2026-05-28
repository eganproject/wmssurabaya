<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackerScanOut extends Model
{
    use HasFactory;

    protected $fillable = [
        'resi_id',
        'kurir_id',
        'scan_type',
        'scan_code',
        'scan_date',
        'scanned_at',
        'scanned_by',
    ];

    protected $casts = [
        'scan_date' => 'date',
        'scanned_at' => 'datetime',
    ];

    public function resi()
    {
        return $this->belongsTo(Resi::class, 'resi_id');
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function kurir()
    {
        return $this->belongsTo(Kurir::class, 'kurir_id');
    }
}
