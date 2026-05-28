<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PickerTransitItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'picked_date',
        'qty',
        'remaining_qty',
        'picked_at',
    ];

    protected $casts = [
        'picked_date' => 'date',
        'picked_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
