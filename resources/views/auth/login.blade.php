@extends('layouts.base')

@section('title', 'Login — ' . config('app.name'))

@section('content')
<div class="auth-shell">
    <div class="auth-panel">
        <div class="auth-card"
             x-data="{ submitting: false, showPassword: false }"
             :class="submitting ? 'ring-1 ring-blue-100 shadow-md' : ''">
            <div class="mb-8 text-center">
                <div class="auth-logo mx-auto mb-4">
                    <img src="/images/logo.svg" alt="Logo" class="h-9 w-9">
                </div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-700">Portal Admin</p>
                <h1 class="auth-title mt-3">Masuk ke Dashboard</h1>
                <p class="auth-subtitle">Untuk admin dinas, admin sekolah, pengawas, dan pembuat soal.</p>
            </div>

            @if(session('error'))
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                 role="alert"
                 aria-live="assertive">
                {{ session('error') }}
            </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" class="space-y-5" @submit="showPassword = false; submitting = true">
                @csrf

                <div>
                    <label for="login-email" class="form-label">Email</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                            </svg>
                        </span>
                        <input id="login-email" type="email" name="email" value="{{ old('email') }}"
                               class="form-input pl-10"
                               placeholder="nama@email.com"
                               required autofocus autocomplete="email"
                               @if($errors->has('email')) aria-describedby="login-email-error" @endif
                               aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    </div>
                    @error('email')
                        <p id="login-email-error" class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="login-password" class="form-label">Password</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input id="login-password" type="password" x-bind:type="showPassword ? 'text' : 'password'" name="password"
                               class="form-input pl-10 pr-10"
                               placeholder="Masukkan password"
                               required autocomplete="current-password"
                               @if($errors->has('password')) aria-describedby="login-password-error" @endif
                               aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                        <button x-cloak x-show="!submitting" type="button" @click="showPassword = !showPassword"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 transition hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
                                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                :aria-pressed="showPassword.toString()">
                            <svg x-cloak x-show="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-cloak x-show="showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p id="login-password-error" class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember"
                               class="w-4 h-4 rounded border-gray-300 text-blue-600">
                        <span class="text-sm text-gray-600">Ingat saya</span>
                    </label>
                    <span class="text-xs text-gray-400">Sesi aman di perangkat ini</span>
                </div>

                <div>
                    <button type="submit"
                            :disabled="submitting"
                            :aria-busy="submitting.toString()"
                            class="auth-submit disabled:cursor-not-allowed disabled:opacity-60">
                        <svg x-cloak x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span x-show="!submitting">Masuk</span>
                        <span x-cloak x-show="submitting">Memverifikasi...</span>
                    </button>

                    <div x-cloak x-show="submitting" x-transition class="auth-status mt-3" role="status" aria-live="polite" aria-atomic="true">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span>Sedang memverifikasi akun dan menyiapkan akses dashboard.</span>
                    </div>
                </div>
            </form>

            <div class="mt-6 border-t border-gray-100 pt-5 text-center">
                <p class="auth-footer">
                    Peserta ujian?
                    <a href="{{ route('ujian.login') }}" class="auth-link">Masuk sebagai peserta</a>
                </p>
            </div>
        </div>

        <p class="mt-5 text-center text-xs text-gray-400">
            © {{ date('Y') }} {{ config('app.name') }} | Dinas Pendidikan
        </p>
    </div>
</div>
@endsection
