@extends('layouts.admin')

@section('title', isset($user) ? 'Edit Pengguna' : 'Tambah Pengguna')

@section('breadcrumb')
    <a href="{{ route('dinas.users.index') }}" class="text-gray-500 hover:text-blue-600">Pengguna</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ isset($user) ? 'Edit' : 'Tambah' }}</span>
@endsection

@section('page-content')
<form action="{{ isset($user) ? route('dinas.users.update', $user->id) : route('dinas.users.store') }}"
      method="POST" class="space-y-5 max-w-xl">
    @csrf
    @if(isset($user)) @method('PUT') @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card space-y-4">
        <h2 class="font-semibold text-gray-900">Data Pengguna</h2>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
            <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Password @if(isset($user)) <span class="text-gray-400 font-normal">(kosong = tidak diubah)</span>@endif
            </label>
            <input type="password" name="password"
                   @if(!isset($user)) required @endif
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Role <span class="text-red-500">*</span></label>
            <select name="role" required
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="admin_dinas" {{ old('role', $user->role ?? '') === 'admin_dinas' ? 'selected' : '' }}>Admin Dinas</option>
                <option value="operator_sekolah" {{ old('role', $user->role ?? '') === 'operator_sekolah' ? 'selected' : '' }}>Operator Sekolah</option>
                <option value="pengawas" {{ old('role', $user->role ?? '') === 'pengawas' ? 'selected' : '' }}>Pengawas</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Sekolah (untuk Operator/Pengawas)</label>
            <select name="sekolah_id"
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">— Dinas Pendidikan —</option>
                @foreach($sekolahList as $sekolah)
                <option value="{{ $sekolah->id }}" {{ old('sekolah_id', $user->sekolah_id ?? '') == $sekolah->id ? 'selected' : '' }}>
                    {{ $sekolah->nama_sekolah }}
                </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Akun aktif</span>
            </label>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ isset($user) ? 'Simpan Perubahan' : 'Tambah Pengguna' }}
        </button>
        <a href="{{ route('dinas.users.index') }}"
           class="border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-medium px-4 py-2.5 rounded-xl transition-colors">
            Batal
        </a>
    </div>
</form>
@endsection
