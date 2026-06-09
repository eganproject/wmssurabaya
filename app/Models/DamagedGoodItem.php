<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedGoodItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'damaged_good_id',
        'item_id',
        'unit_id',
        'qty_input',
        'conversion_qty',
        'qty',
        'note',
    ];

    public function damagedGood()
    {
        return $this->belongsTo(DamagedGood::class, 'damaged_good_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function unit()
    {
        return $this->belongsTo(ItemUnit::class, 'unit_id');
    }
}
