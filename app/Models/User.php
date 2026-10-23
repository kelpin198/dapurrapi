<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['role' => 'user'];

    protected function casts(): array
    {
        return ['last_login_at' => 'datetime'];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function transaksis()
    {
        return $this->hasMany(Transaksis::class);
    }

    public function ulasans()
    {
        return $this->hasMany(Ulasans::class, 'user_id');
    }
}
