<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'divisi_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }

    public static function defaultAvatar(): string
    {
        $choices = [
            'metronic/media/avatars/150-1.jpg',
            'metronic/media/avatars/150-10.jpg',
            'metronic/media/avatars/150-11.jpg',
            'metronic/media/avatars/150-12.jpg',
            'metronic/media/avatars/150-13.jpg',
            'metronic/media/avatars/150-14.jpg',
            'metronic/media/avatars/150-15.jpg',
            'metronic/media/avatars/150-16.jpg',
            'metronic/media/avatars/150-17.jpg',
            'metronic/media/avatars/150-18.jpg',
            'metronic/media/avatars/150-19.jpg',
            'metronic/media/avatars/150-2.jpg',
            'metronic/media/avatars/150-20.jpg',
            'metronic/media/avatars/150-21.jpg',
            'metronic/media/avatars/150-22.jpg',
            'metronic/media/avatars/150-23.jpg',
            'metronic/media/avatars/150-24.jpg',
            'metronic/media/avatars/150-25.jpg',
            'metronic/media/avatars/150-26.jpg',
            'metronic/media/avatars/150-3.jpg',
            'metronic/media/avatars/150-4.jpg',
            'metronic/media/avatars/150-5.jpg',
            'metronic/media/avatars/150-6.jpg',
            'metronic/media/avatars/150-7.jpg',
            'metronic/media/avatars/150-8.jpg',
            'metronic/media/avatars/150-9.jpg',
        ];

        return collect($choices)->shuffle()->first();
    }

    public function getAvatarUrlAttribute(): string
    {
        $path = $this->avatar ?: 'metronic/media/avatars/blank.png';
        return asset($path);
    }
}
