<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resi extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_pesanan',
        'tanggal_pesanan',
        'tanggal_upload',
        'no_resi',
        'kurir_id',
        'catatan_pembeli',
        'status',
        'canceled_at',
        'canceled_by',
        'cancel_reason',
        'uncanceled_at',
        'uncanceled_by',
        'uploader_id',
    ];

    protected $casts = [
        'tanggal_pesanan' => 'date',
        'tanggal_upload' => 'date',
        'canceled_at' => 'datetime',
        'uncanceled_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(ResiDetail::class, 'resi_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function canceler()
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function uncanceler()
    {
        return $this->belongsTo(User::class, 'uncanceled_by');
    }

    public function kurir()
    {
        return $this->belongsTo(Kurir::class, 'kurir_id');
    }
}
