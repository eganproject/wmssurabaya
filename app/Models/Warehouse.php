<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    public const TYPE_BULK = 'bulk';
    public const TYPE_FULFILLMENT = 'fulfillment';
    public const DEFAULT_CODE = 'WH-SMALL';

    protected $fillable = [
        'code',
        'name',
        'type',
        'address',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public static function defaultId(): int
    {
        return (int) static::query()
            ->where('is_default', true)
            ->value('id')
            ?: (int) static::query()->where('code', self::DEFAULT_CODE)->value('id');
    }

    public function stocks()
    {
        return $this->hasMany(ItemStock::class);
    }
}
