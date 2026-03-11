<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PesertaLoginController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function showLogin()
    {
        if (Auth::guard('peserta')->check()) {
            return redirect()->route('ujian.lobby');
        }
        return view('auth.peserta-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $result = $this->authService->loginPeserta([
            'username' => $request->username,
            'password' => $request->password,
        ]);

        $request->session()->regenerate();
        $request->session()->put('device_token', $result['device_token']);

        return redirect()->route('ujian.lobby');
    }

    public function logout(Request $request)
    {
        $peserta = Auth::guard('peserta')->user();
        if ($peserta) {
            $peserta->update(['device_token' => null]);
        }

        $this->authService->logout('peserta');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('ujian.login');
    }
}
