<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResiDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'resi_id',
        'sku',
        'qty',
    ];

    public function resi()
    {
        return $this->belongsTo(Resi::class, 'resi_id');
    }
}
