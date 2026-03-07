@extends('layouts.admin')

@section('title', isset($sekolah) ? 'Edit Sekolah' : 'Tambah Sekolah')

@section('breadcrumb')
    <a href="{{ route('dinas.sekolah.index') }}" class="text-gray-500 hover:text-blue-600">Sekolah</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ isset($sekolah) ? 'Edit' : 'Tambah' }}</span>
@endsection

@section('page-content')
<form action="{{ isset($sekolah) ? route('dinas.sekolah.update', $sekolah->id) : route('dinas.sekolah.store') }}"
      method="POST" class="space-y-5 max-w-xl">
    @csrf
    @if(isset($sekolah)) @method('PUT') @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card space-y-4">
        <h2 class="font-semibold text-gray-900">Data Sekolah</h2>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Sekolah <span class="text-red-500">*</span></label>
            <input type="text" name="nama" value="{{ old('nama', $sekolah->nama ?? '') }}" required
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Nama lengkap sekolah">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">NPSN</label>
                <input type="text" name="npsn" value="{{ old('npsn', $sekolah->npsn ?? '') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                       placeholder="8 digit NPSN">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenjang <span class="text-red-500">*</span></label>
                <select name="jenjang" required
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach(['SD', 'SMP', 'SMA', 'SMK', 'MA', 'MTs', 'MI'] as $t)
                    <option value="{{ $t }}" {{ old('jenjang', $sekolah->jenjang ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Alamat</label>
            <textarea name="alamat" rows="2"
                      class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                      placeholder="Alamat lengkap sekolah">{{ old('alamat', $sekolah->alamat ?? '') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nomor Telepon</label>
            <input type="text" name="telepon" value="{{ old('telepon', $sekolah->telepon ?? '') }}"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="0xxx-xxxx-xxxx">
        </div>

        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                 <input type="checkbox" name="is_active" value="1"
                        {{ old('is_active', $sekolah->is_active ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Sekolah aktif</span>
            </label>
        </div>
    </div>

    {{-- Akun Operator Sekolah --}}
    @if(!isset($sekolah))
    <div class="card space-y-4">
        <h2 class="font-semibold text-gray-900">Akun Operator Sekolah</h2>
        <p class="text-xs text-gray-500">Opsional — buat akun untuk operator sekolah ini sekarang.</p>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Operator</label>
            <input type="text" name="operator_name" value="{{ old('operator_name') }}"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Operator</label>
            <input type="email" name="operator_email" value="{{ old('operator_email') }}"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Password Operator</label>
            <input type="text" name="operator_password"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono">
        </div>
    </div>
    @endif

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ isset($sekolah) ? 'Simpan Perubahan' : 'Tambah Sekolah' }}
        </button>
        <a href="{{ route('dinas.sekolah.index') }}"
           class="border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-medium px-4 py-2.5 rounded-xl transition-colors">
            Batal
        </a>
    </div>
</form>
@endsection
