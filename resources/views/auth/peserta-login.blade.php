@extends('layouts.base')

@section('title', 'Login Peserta — ' . config('app.name'))

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
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-700">Peserta Ujian</p>
                <h1 class="auth-title mt-3">Login Peserta Ujian</h1>
                <p class="auth-subtitle">Gunakan NIS, NISN, atau username ujian serta password dari kartu ujian.</p>
            </div>

            <div id="offline-notice"
                 class="hidden mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <div class="flex items-start gap-2.5">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 4.93a10 10 0 0114.14 14.14M9.88 9.88a3 3 0 014.24 4.24M1 1l22 22"/>
                    </svg>
                    <p>Tidak ada koneksi. Login peserta membutuhkan internet aktif.</p>
                </div>
            </div>

            @if(session('error'))
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ session('error') }}
            </div>
            @endif

            <form action="{{ route('ujian.login.post') }}" method="POST" class="space-y-5" @submit="showPassword = false; submitting = true">
                @csrf

                <div>
                    <label for="peserta-username" class="form-label">NIS / NISN / Username</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                            </svg>
                        </span>
                        <input id="peserta-username" type="text" name="username" value="{{ old('username') }}"
                               class="form-input pl-10"
                               placeholder="Contoh: 12345 atau 0012345678"
                               required autocomplete="username" inputmode="text"
                               aria-describedby="peserta-username-error"
                               aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}">
                    </div>
                    @error('username')
                        <p id="peserta-username-error" class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="peserta-password" class="form-label">Password</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input id="peserta-password" type="password" x-bind:type="showPassword ? 'text' : 'password'" name="password"
                               class="form-input pl-10 pr-10"
                               placeholder="Password dari kartu ujian"
                               required autocomplete="current-password"
                               aria-describedby="peserta-password-error"
                               aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                        <button x-cloak x-show="!submitting" type="button" @click="showPassword = !showPassword"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 transition hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
                                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                :aria-pressed="showPassword.toString()">
                            <svg x-cloak x-show="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-cloak x-show="showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p id="peserta-password-error" class="form-error">{{ $message }}</p>
                    @enderror
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
                        <span x-show="!submitting">Masuk ke Ujian</span>
                        <span x-cloak x-show="submitting">Menyiapkan sesi...</span>
                    </button>

                    <div x-cloak x-show="submitting" x-transition class="auth-status mt-3" aria-live="polite">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span>Sedang memverifikasi akun dan menyiapkan sesi ujian.</span>
                    </div>
                </div>
            </form>

            <div class="mt-6 border-t border-gray-100 pt-5 text-center">
                <p class="auth-footer">
                    Admin sekolah atau pengawas?
                    <a href="{{ route('login') }}" class="auth-link">Login Admin</a>
                </p>
            </div>
        </div>

        <p class="mt-5 text-center text-xs text-gray-400">
            © {{ date('Y') }} {{ config('app.name') }} | Dinas Pendidikan
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function updateOnlineStatus() {
        const notice = document.getElementById('offline-notice');
        if (notice) {
            notice.classList.toggle('hidden', navigator.onLine);
        }
    }

    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    updateOnlineStatus();
</script>
@endpush
