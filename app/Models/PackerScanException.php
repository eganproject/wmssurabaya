<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackerScanException extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'note',
    ];
}
