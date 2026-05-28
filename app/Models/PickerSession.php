<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PickerSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'user_id',
        'status',
        'started_at',
        'submitted_at',
        'note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PickerSessionItem::class, 'picker_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
