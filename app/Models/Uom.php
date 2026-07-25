<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Uom extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function itemUnits()
    {
        return $this->hasMany(ItemUnit::class);
    }
}
