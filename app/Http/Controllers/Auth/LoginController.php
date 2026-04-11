<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function showLogin()
    {
        if (Auth::guard('web')->check()) {
            /** @var \App\Models\User $user */
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
            'role'     => 'nullable|string|in:super_admin,admin_dinas,admin_sekolah,pengawas,pembuat_soal',
        ]);

        $result = $this->authService->loginUser([
            'email'    => $request->email,
            'password' => $request->password,
            'role'     => $request->role,
            'remember' => $request->boolean('remember'),
        ]);

        // Regenerate session after successful auth to prevent session fixation
        try {
            $request->session()->regenerate();
        } catch (\Exception $e) {
            // If session regeneration fails (e.g. Redis down), logout to prevent
            // authenticated state without a fresh session
            $this->authService->logout('web');
            return back()->withErrors(['email' => 'Terjadi kesalahan saat login. Silakan coba lagi.']);
        }

        return redirect()->route($result['dashboard_route']);
    }

    public function logout(Request $request)
    {
        $this->authService->logout('web');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
