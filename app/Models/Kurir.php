<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kurir extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function resis()
    {
        return $this->hasMany(Resi::class, 'kurir_id');
    }

    public function scanOuts()
    {
        return $this->hasMany(PackerScanOut::class, 'kurir_id');
    }
}
