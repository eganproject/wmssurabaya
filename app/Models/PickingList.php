<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PickingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'list_date',
        'sku',
        'qty',
        'remaining_qty',
    ];

    protected $casts = [
        'list_date' => 'date',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'sku', 'sku');
    }
}
