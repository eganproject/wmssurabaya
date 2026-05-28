<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'route_name',
        'method',
        'url',
        'ip_address',
        'user_agent',
        'payload',
        'context',
    ];

    protected $casts = [
        'payload' => 'array',
        'context' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
