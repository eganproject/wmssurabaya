<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResiImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_code',
        'file_name',
        'uploaded_by',
        'uploaded_at',
        'total_resis',
        'total_details',
        'status',
        'deleted_at',
        'deleted_by',
        'delete_reason',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(ResiImportBatchItem::class, 'resi_import_batch_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
