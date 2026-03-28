@extends('layouts.base')

@section('title', 'Ganti Password — ' . config('app.name'))

@section('content')
<div class="auth-shell">
    <div class="auth-panel">
        <div class="auth-card" x-data="{ submitting: false, showPassword: false }">
            <div class="mb-8 text-center">
                <div class="auth-logo mx-auto mb-4">
                    <svg class="h-9 w-9 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h1 class="auth-title mt-3">Ganti Password</h1>
                <p class="auth-subtitle">Demi keamanan, Anda harus mengganti password sebelum melanjutkan.</p>
            </div>

            @if(session('error'))
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                {{ session('error') }}
            </div>
            @endif

            @if($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form action="{{ route('account.force-change-password.update') }}" method="POST" class="space-y-5" @submit="submitting = true">
                @csrf
                @method('PUT')

                <div>
                    <label for="password" class="form-label">Password Baru</label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" name="password" id="password"
                               class="form-input pr-10" required autocomplete="new-password" minlength="8">
                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                            <svg x-show="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg x-show="showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1.5">Minimal 8 karakter</p>
                </div>

                <div>
                    <label for="password_confirmation" class="form-label">Konfirmasi Password Baru</label>
                    <input :type="showPassword ? 'text' : 'password'" name="password_confirmation" id="password_confirmation"
                           class="form-input" required autocomplete="new-password" minlength="8">
                </div>

                <button type="submit"
                        class="auth-submit-btn w-full"
                        :disabled="submitting"
                        :class="submitting ? 'opacity-60 cursor-wait' : ''">
                    <template x-if="submitting">
                        <svg class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </template>
                    <template x-if="!submitting">
                        <span>Simpan Password Baru</span>
                    </template>
                </button>
            </form>

            <div class="mt-6 text-center">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 underline">Logout</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
