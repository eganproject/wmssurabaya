<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'uom_id',
        'name',
        'conversion_qty',
        'is_base',
        'barcode',
    ];

    protected $casts = [
        'conversion_qty' => 'integer',
        'is_base' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function getIsPackageAttribute(): bool
    {
        return !$this->is_base;
    }
}
