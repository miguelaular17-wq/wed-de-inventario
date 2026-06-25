<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_TELEFONIA = 'telefonia';
    public const ROLE_COMPRADOR = 'comprador';
    public const ROLE_SEDE = 'sede';
    public const ROLE_VENDEDOR = 'vendedor';
    public const ROLE_MARKETING = 'marketing';

    protected $fillable = [
        'name',
        'email',
        'password',
        'password_plain',
        'role',
        'sede',
        'tutorial_step',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    public function isTelefonia(): bool
    {
        return $this->role === self::ROLE_TELEFONIA;
    }


    public function isComprador(): bool
    {
        return $this->role === self::ROLE_COMPRADOR;
    }

    public function isSede(): bool
    {
        return $this->role === self::ROLE_SEDE;
    }

    public function isVendedor(): bool
    {
        return $this->role === self::ROLE_VENDEDOR;
    }

    public function isMarketing(): bool
    {
        return $this->role === self::ROLE_MARKETING;
    }

    public function hasAccessToSedeViews(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPERVISOR, self::ROLE_TELEFONIA, self::ROLE_SEDE, self::ROLE_COMPRADOR], true);
    }

    public function hasAccessToMovimientos(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'receiver_id')->orderBy('created_at', 'desc');
    }
}
