@extends('layouts.admin')

@section('title', 'Pengaturan Akun')

@section('breadcrumb')
    <span>Pengaturan Akun</span>
@endsection

@section('page-content')
<div class="max-w-2xl mx-auto space-y-6">

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <p class="text-green-700 text-sm font-medium">{{ session('success') }}</p>
    </div>
    @endif

    {{-- Profile Info --}}
    <form action="{{ route('account.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-900">Informasi Profil</h2>
                <p class="text-sm text-gray-500 mt-0.5">Perbarui nama dan alamat email akun Anda.</p>
            </div>

            <div class="px-6 py-5 space-y-5">
                <div>
                    <label for="name" class="form-label">Nama</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}"
                           class="form-input" required>
                    @error('name')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}"
                           class="form-input" required>
                    @error('email')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label text-gray-400">Role</label>
                    <p class="text-sm font-medium text-gray-700 bg-gray-50 rounded-lg px-3.5 py-2.5 border border-gray-200">
                        {{ str_replace('_', ' ', ucwords($user->role, '_')) }}
                        @if($user->sekolah)
                            <span class="text-gray-400">— {{ $user->sekolah->nama }}</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="px-6 py-5 border-t border-gray-100 bg-gray-50/50">
                <h3 class="text-base font-bold text-gray-900 mb-1">Ganti Password</h3>
                <p class="text-sm text-gray-500 mb-5">Kosongkan jika tidak ingin mengubah password.</p>

                <div class="space-y-5">
                    <div>
                        <label for="current_password" class="form-label">Password Saat Ini</label>
                        <input type="password" name="current_password" id="current_password"
                               class="form-input" autocomplete="current-password">
                        @error('current_password')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="password" class="form-label">Password Baru</label>
                            <input type="password" name="password" id="password"
                                   class="form-input" autocomplete="new-password">
                            @error('password')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                   class="form-input" autocomplete="new-password">
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold
                               px-5 py-2.5 rounded-xl transition-colors text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan Perubahan
                </button>
            </div>
        </div>
    </form>

    {{-- Account Info --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-5">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Info Akun</h3>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-400">Login Terakhir</dt>
                    <dd class="text-gray-900 font-medium mt-0.5">
                        {{ $user->last_login_at?->translatedFormat('d M Y, H:i') ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-400">Status</dt>
                    <dd class="mt-0.5">
                        @if($user->is_active)
                            <span class="inline-flex items-center gap-1 text-green-700 font-medium">
                                <span class="w-2 h-2 bg-green-500 rounded-full"></span> Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-red-700 font-medium">
                                <span class="w-2 h-2 bg-red-500 rounded-full"></span> Nonaktif
                            </span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</div>
@endsection
