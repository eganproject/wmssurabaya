<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'permission_menu')
            ->withPivot(['can_view','can_create','can_update','can_delete'])
            ->withTimestamps();
    }
}

