@extends('layouts.admin')

@section('title', isset($kategori) ? 'Edit Kategori Soal' : 'Tambah Kategori Soal')

@section('breadcrumb')
    <a href="{{ route('dinas.kategori.index') }}" class="text-gray-500 hover:text-blue-600">Kategori Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ isset($kategori) ? 'Edit' : 'Tambah' }}</span>
@endsection

@section('page-content')
<form action="{{ isset($kategori) ? route('dinas.kategori.update', $kategori->id) : route('dinas.kategori.store') }}"
      method="POST" class="space-y-5 max-w-xl">
    @csrf
    @if(isset($kategori)) @method('PUT') @endif

    <div class="card space-y-4">
        <h2 class="font-semibold text-gray-900">Data Kategori Soal</h2>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama <span class="text-red-500">*</span></label>
            <input type="text" name="nama" value="{{ old('nama', $kategori->nama ?? '') }}" required
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode</label>
            <input type="text" name="kode" value="{{ old('kode', $kategori->kode ?? '') }}"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenjang <span class="text-red-500">*</span></label>
            <select name="jenjang" required
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                @foreach(['SD','SMP','SMA','SMK','MA','MTs','MI','SEMUA'] as $j)
                <option value="{{ $j }}" {{ old('jenjang', $kategori->jenjang ?? '') === $j ? 'selected' : '' }}>{{ $j }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kelompok</label>
            <input type="text" name="kelompok" value="{{ old('kelompok', $kategori->kelompok ?? '') }}"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kurikulum <span class="text-red-500">*</span></label>
            <input type="text" name="kurikulum" value="{{ old('kurikulum', $kategori->kurikulum ?? '') }}" required
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Urutan</label>
            <input type="number" name="urutan" value="{{ old('urutan', $kategori->urutan ?? 0) }}" min="0"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ isset($kategori) ? 'Simpan Perubahan' : 'Tambah Kategori' }}
        </button>
        <a href="{{ route('dinas.kategori.index') }}"
           class="border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-medium px-4 py-2.5 rounded-xl transition-colors">
            Batal
        </a>
    </div>
</form>
@endsection
