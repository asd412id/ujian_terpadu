@extends('layouts.base')

@section('title', 'Login Peserta — Ujian Terpadu TKA')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-700 to-indigo-800 flex items-center justify-center p-4">

    {{-- Decorative bg circles --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-20 -right-20 w-80 h-80 bg-white/5 rounded-full"></div>
        <div class="absolute -bottom-20 -left-20 w-96 h-96 bg-white/5 rounded-full"></div>
    </div>

    <div class="relative w-full max-w-md">
        {{-- Card --}}
        <div class="bg-white rounded-3xl shadow-2xl p-8">

            {{-- Logo --}}
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-blue-700 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-9 h-9 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-gray-900">Login Peserta Ujian</h1>
                <p class="text-gray-500 text-sm mt-1">Masukkan NIS/NISN dan password dari kartu ujian</p>
            </div>

            {{-- Offline indicator --}}
            <div id="offline-notice"
                 class="hidden mb-4 bg-amber-50 border border-amber-200 rounded-xl p-3 text-center">
                <p class="text-amber-700 text-xs font-medium">
                    ⚠️ Tidak ada koneksi — Login membutuhkan internet
                </p>
            </div>

            @if(session('error'))
            <div class="mb-4 bg-red-50 border border-red-200 rounded-xl p-3 text-center">
                <p class="text-red-700 text-sm">{{ session('error') }}</p>
            </div>
            @endif

            <form action="{{ route('ujian.login.post') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label class="form-label">NIS / NISN / Username</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                            </svg>
                        </span>
                        <input type="text" name="username" value="{{ old('username') }}"
                               class="form-input pl-10"
                               placeholder="Contoh: 12345 atau 0012345678"
                               required autocomplete="username"
                               inputmode="text">
                    </div>
                    @error('username')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div x-data="{ show: false }">
                    <label class="form-label">Password</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input :type="show ? 'text' : 'password'" name="password"
                               class="form-input pl-10 pr-10"
                               placeholder="Password dari kartu ujian"
                               required autocomplete="current-password">
                        <button type="button" @click="show = !show"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full bg-gradient-to-r from-blue-700 to-indigo-600
                               hover:from-blue-800 hover:to-indigo-700
                               text-white font-bold py-3.5 rounded-xl
                               transition-all duration-150 shadow-md hover:shadow-lg text-base
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    MASUK KE UJIAN
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-gray-100 text-center">
                <p class="text-xs text-gray-400">
                    Admin sekolah?
                    <a href="{{ route('login') }}" class="text-blue-600 hover:underline">Login Admin</a>
                </p>
            </div>
        </div>

        <p class="text-center text-blue-200/70 text-xs mt-5">
            © {{ date('Y') }} Ujian Terpadu TKA | Dinas Pendidikan
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Detect offline saat login peserta
    function updateOnlineStatus() {
        const notice = document.getElementById('offline-notice');
        if (notice) {
            notice.classList.toggle('hidden', navigator.onLine);
        }
    }
    window.addEventListener('online',  updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    updateOnlineStatus();
</script>
@endpush
