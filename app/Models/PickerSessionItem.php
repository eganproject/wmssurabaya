<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PickerSessionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'picker_session_id',
        'item_id',
        'qty',
        'note',
    ];

    public function session()
    {
        return $this->belongsTo(PickerSession::class, 'picker_session_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
