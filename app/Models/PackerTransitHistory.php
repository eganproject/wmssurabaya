<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackerTransitHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'resi_id',
        'id_pesanan',
        'no_resi',
        'status',
    ];

    public function resi()
    {
        return $this->belongsTo(Resi::class, 'resi_id');
    }
}
