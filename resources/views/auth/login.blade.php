@extends('layouts.base')

@section('title', 'Login — ' . config('app.name'))

@push('head')
<style>
    .gradient-left {
        background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 50%, #4f46e5 100%);
    }
</style>
@endpush

@section('content')
<div class="min-h-screen flex">

    {{-- LEFT PANEL — Branding --}}
    <div class="hidden lg:flex lg:w-[55%] gradient-left flex-col items-center justify-center p-12 relative overflow-hidden">
        {{-- Decorative circles --}}
        <div class="absolute top-10 right-10 w-64 h-64 bg-white/5 rounded-full"></div>
        <div class="absolute bottom-10 left-10 w-96 h-96 bg-white/5 rounded-full"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-white/3 rounded-full"></div>

        <div class="relative z-10 text-center max-w-md">
            {{-- App Icon --}}
            <div class="w-20 h-20 bg-white/20 rounded-3xl flex items-center justify-center mx-auto mb-6 backdrop-blur-sm">
                <img src="/images/logo.svg" alt="Logo" class="w-14 h-14">
            </div>

            <h1 class="text-4xl font-bold text-white tracking-tight mb-2">{{ strtoupper(config('app.name')) }}</h1>
            <p class="text-blue-200 text-lg mb-10">Platform Ujian Resmi<br>Dinas Pendidikan</p>

            {{-- Feature badges --}}
            <div class="space-y-3">
                @foreach([
                    ['🔒', 'Aman & Terpercaya',    'Enkripsi data end-to-end'],
                    ['📱', 'Akses Kapan Saja',      'Bisa offline, sync otomatis'],
                    ['📊', 'Laporan Real-time',     'Pantau ujian langsung'],
                ] as [$icon, $title, $sub])
                <div class="flex items-center gap-4 bg-white/10 backdrop-blur-sm rounded-xl px-4 py-3 text-left">
                    <span class="text-2xl">{{ $icon }}</span>
                    <div>
                        <p class="text-white font-semibold text-sm">{{ $title }}</p>
                        <p class="text-blue-200 text-xs">{{ $sub }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- RIGHT PANEL — Login Form --}}
    <div class="flex-1 flex flex-col items-center justify-center bg-white px-6 py-10 sm:px-10">

        {{-- Mobile logo --}}
        <div class="lg:hidden mb-8 text-center">
            <div class="w-16 h-16 bg-blue-700 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <img src="/images/logo.svg" alt="Logo" class="w-10 h-10">
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ strtoupper(config('app.name')) }}</h1>
        </div>

        <div class="w-full max-w-md">
            <div class="mb-7">
                <h2 class="text-2xl font-bold text-gray-900">Selamat Datang</h2>
                <p class="text-gray-500 text-sm mt-1">Masuk ke akun Anda untuk melanjutkan</p>
            </div>

            {{-- Login Form --}}
            <div class="mb-6">
                <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label class="form-label">Email</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                </svg>
                            </span>
                            <input type="email" name="email" value="{{ old('email') }}"
                                   class="form-input pl-10"
                                   placeholder="nama@email.com" required autofocus>
                        </div>
                        @error('email')
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
                                   placeholder="••••••••" required>
                            <button type="button" @click="show = !show"
                                    class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg x-show="!show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember"
                                   class="w-4 h-4 text-blue-600 rounded border-gray-300">
                            <span class="text-sm text-gray-600">Ingat saya</span>
                        </label>
                    </div>

                    <button type="submit"
                            class="w-full bg-gradient-to-r from-blue-700 to-indigo-600
                                   hover:from-blue-800 hover:to-indigo-700
                                   text-white font-semibold py-3 rounded-xl
                                   transition-all duration-150 shadow-md hover:shadow-lg
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        MASUK
                    </button>
                </form>
            </div>

            <p class="text-center text-sm text-gray-500 mt-4">
                Peserta ujian?
                <a href="{{ route('ujian.login') }}" class="text-blue-600 hover:underline font-medium">
                    Login di sini →
                </a>
            </p>

            <p class="text-center text-xs text-gray-400 mt-6">
                © {{ date('Y') }} Dinas Pendidikan | {{ config('app.name') }} v2.0
            </p>
        </div>
    </div>
</div>
@endsection
