<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('web')->check()) {
            /** @var User $user */
            $user = Auth::user();
            return redirect()->route($user->getDashboardRoute());
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
            'role'     => 'required|in:admin_dinas,admin_sekolah,pengawas',
        ]);

        $credentials = [
            'email'    => $request->email,
            'password' => $request->password,
            'role'     => $request->role,
            'is_active'=> true,
        ];

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email/password salah atau akun tidak aktif.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->update(['last_login_at' => now()]);

        return redirect()->route($user->getDashboardRoute());
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
