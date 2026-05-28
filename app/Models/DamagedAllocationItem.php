<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedAllocationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'damaged_allocation_id',
        'item_id',
        'qty',
        'note',
    ];

    public function allocation()
    {
        return $this->belongsTo(DamagedAllocation::class, 'damaged_allocation_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
