<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResiImportBatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'resi_import_batch_id',
        'resi_id',
        'id_pesanan',
        'no_resi',
        'action',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(ResiImportBatch::class, 'resi_import_batch_id');
    }
}
