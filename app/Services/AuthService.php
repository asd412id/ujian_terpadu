<?php

namespace App\Services;

use App\Models\Peserta;
use App\Models\User;
use App\Repositories\AuthRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected AuthRepository $repository
    ) {}

    /**
     * Authenticate a user (admin/dinas/pengawas/sekolah).
     *
     * @throws ValidationException
     */
    public function loginUser(array $credentials): array
    {
        $email = $credentials['email'] ?? '';
        $password = $credentials['password'] ?? '';
        $role = $credentials['role'] ?? null;
        $remember = $credentials['remember'] ?? false;

        $attemptCredentials = [
            'email'     => $email,
            'password'  => $password,
            'is_active' => true,
        ];

        if ($role) {
            $attemptCredentials['role'] = $role;
        }

        if (!Auth::guard('web')->attempt($attemptCredentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Email/password salah atau akun tidak aktif.',
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $user->update(['last_login_at' => now()]);

        return [
            'user'            => $user,
            'dashboard_route' => $user->getDashboardRoute(),
        ];
    }

    /**
     * Authenticate a peserta (student) login.
     *
     * @throws ValidationException
     */
    public function loginPeserta(array $credentials): array
    {
        $username = $credentials['username'] ?? '';
        $password = $credentials['password'] ?? '';

        // Find peserta by NIS, NISN, or username_ujian
        $peserta = Peserta::where(function ($q) use ($username) {
                $q->where('username_ujian', $username)
                  ->orWhere('nis', $username)
                  ->orWhere('nisn', $username);
            })
            ->where('is_active', true)
            ->first();

        if (!$peserta || !Hash::check($password, $peserta->password_ujian)) {
            throw ValidationException::withMessages([
                'username' => 'NIS/NISN/Username atau password tidak valid.',
            ]);
        }

        Auth::guard('peserta')->login($peserta);

        $deviceToken = Str::random(64);
        $peserta->update(['device_token' => $deviceToken]);

        return [
            'peserta'      => $peserta,
            'device_token' => $deviceToken,
        ];
    }

    /**
     * Logout from a specific guard.
     */
    public function logout(string $guard = 'web'): void
    {
        Auth::guard($guard)->logout();
    }

    /**
     * Validate a peserta token for API authentication.
     *
     * @throws ValidationException
     */
    public function validatePesertaToken(string $token): mixed
    {
        $peserta = Peserta::where('api_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$peserta) {
            throw ValidationException::withMessages([
                'token' => 'Token tidak valid atau akun tidak aktif.',
            ]);
        }

        return $peserta;
    }
}
