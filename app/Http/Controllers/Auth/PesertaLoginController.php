<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Peserta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PesertaLoginController extends Controller
{
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

        // Cari peserta berdasarkan NIS, NISN, atau username_ujian
        $peserta = Peserta::where(function ($q) use ($request) {
                        $q->where('username_ujian', $request->username)
                          ->orWhere('nis', $request->username)
                          ->orWhere('nisn', $request->username);
                    })
                    ->where('is_active', true)
                    ->first();

        if (! $peserta || ! Hash::check($request->password, $peserta->password_ujian)) {
            throw ValidationException::withMessages([
                'username' => 'NIS/NISN/Username atau password tidak valid.',
            ]);
        }

        Auth::guard('peserta')->login($peserta);
        $request->session()->regenerate();

        return redirect()->route('ujian.lobby');
    }

    public function logout(Request $request)
    {
        Auth::guard('peserta')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('ujian.login');
    }
}
