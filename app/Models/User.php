<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'role', 'sekolah_id', 'is_active', 'last_login_at', 'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // Role constants
    const ROLE_SUPER_ADMIN   = 'super_admin';
    const ROLE_ADMIN_DINAS   = 'admin_dinas';
    const ROLE_ADMIN_SEKOLAH = 'admin_sekolah';
    const ROLE_PENGAWAS      = 'pengawas';
    const ROLE_PEMBUAT_SOAL  = 'pembuat_soal';

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function isSuperAdmin(): bool   { return $this->role === self::ROLE_SUPER_ADMIN; }
    public function isAdminDinas(): bool   { return $this->role === self::ROLE_ADMIN_DINAS; }
    public function isAdminSekolah(): bool { return $this->role === self::ROLE_ADMIN_SEKOLAH; }
    public function isPengawas(): bool     { return $this->role === self::ROLE_PENGAWAS; }
    public function isPembuatSoal(): bool  { return $this->role === self::ROLE_PEMBUAT_SOAL; }

    public function isDinas(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN_DINAS]);
    }

    public function getDashboardRoute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN_DINAS   => 'dinas.dashboard',
            self::ROLE_ADMIN_SEKOLAH => 'sekolah.dashboard',
            self::ROLE_PENGAWAS      => 'pengawas.dashboard',
            self::ROLE_PEMBUAT_SOAL  => 'pembuat-soal.dashboard',
            default                  => 'login',
        };
    }
}
