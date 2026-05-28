<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratJalan extends Model
{
    protected $fillable = [
        'code',
        'outbound_transaction_id',
        'note',
        'created_by',
    ];

    public function outboundTransaction()
    {
        return $this->belongsTo(OutboundTransaction::class, 'outbound_transaction_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
