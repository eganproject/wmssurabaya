<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QcTransitItem extends Model
{
    protected $fillable = [
        'item_id',
        'transit_date',
        'qty',
        'remaining_qty',
        'last_qc_at',
    ];

    protected $casts = [
        'transit_date' => 'date',
        'last_qc_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
