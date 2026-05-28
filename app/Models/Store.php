<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'pic_id',
        'logo',
        'address',
    ];

    public function pic()
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function getLogoUrlAttribute(): string
    {
        if ($this->logo) {
            return asset($this->logo);
        }
        return asset('metronic/media/logos/logo-demo11.svg');
    }
}
