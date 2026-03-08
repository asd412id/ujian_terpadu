<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Peserta;

class AuthRepository
{
    /**
     * Find user by email (for admin/pengawas login).
     */
    public function findUserByUsername(string $email): ?User
    {
        return User::where('email', $email)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find peserta by token ujian (for session validation).
     */
    public function findPesertaByToken(string $token): ?Peserta
    {
        return Peserta::where('token_ujian', $token)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find peserta by credentials (username_ujian, NIS, or NISN).
     */
    public function findPesertaByCredentials(string $username): ?Peserta
    {
        return Peserta::where(function ($q) use ($username) {
                $q->where('username_ujian', $username)
                  ->orWhere('nis', $username)
                  ->orWhere('nisn', $username);
            })
            ->where('is_active', true)
            ->first();
    }

    /**
     * Update last login timestamp for a user.
     */
    public function updateLastLogin(User $user): bool
    {
        return $user->update(['last_login_at' => now()]);
    }
}
