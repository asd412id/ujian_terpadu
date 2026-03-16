@extends('layouts.admin')

@section('title', isset($narasi) ? 'Edit Narasi Soal' : 'Tambah Narasi Soal')

@section('breadcrumb')
    <a href="{{ route('dinas.narasi.index') }}" class="text-gray-500 hover:text-blue-600">Narasi Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ isset($narasi) ? 'Edit' : 'Tambah' }}</span>
@endsection

@section('page-content')
<form action="{{ isset($narasi) ? route('dinas.narasi.update', $narasi->id) : route('dinas.narasi.store') }}"
      method="POST" enctype="multipart/form-data" class="space-y-5 max-w-3xl">
    @csrf
    @if(isset($narasi)) @method('PUT') @endif

    <div class="card space-y-4">
        <h2 class="font-semibold text-gray-900">Data Narasi</h2>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Judul Narasi <span class="text-red-500">*</span></label>
            <input type="text" name="judul" value="{{ old('judul', $narasi->judul ?? '') }}" required
                   placeholder="Contoh: Teks Bacaan 1 — Kearifan Lokal"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            @error('judul') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kategori Soal <span class="text-red-500">*</span></label>
            <select name="kategori_id" required
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Pilih Kategori</option>
                @foreach($kategoris as $kat)
                <option value="{{ $kat->id }}" {{ old('kategori_id', $narasi->kategori_id ?? '') == $kat->id ? 'selected' : '' }}>{{ $kat->nama }}</option>
                @endforeach
            </select>
            @error('kategori_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Isi Narasi / Teks Bacaan <span class="text-red-500">*</span></label>
            <textarea name="konten" rows="12" required
                      placeholder="Ketikkan teks bacaan/paragraf yang akan ditampilkan kepada siswa..."
                      class="rich-editor w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('konten', $narasi->konten ?? '') }}</textarea>
            @error('konten') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Gambar (opsional)</label>
            @if(isset($narasi) && $narasi->gambar)
            <div class="mb-2">
                <img src="{{ Storage::url($narasi->gambar) }}" alt="Gambar Narasi" class="max-h-40 rounded-lg border">
            </div>
            @endif
            <input type="file" name="gambar" accept="image/*"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            @error('gambar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        @if(isset($narasi))
        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $narasi->is_active) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-gray-700">Aktif</span>
            </label>
        </div>
        @endif
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ isset($narasi) ? 'Simpan Perubahan' : 'Tambah Narasi' }}
        </button>
        <a href="{{ route('dinas.narasi.index') }}" class="btn-secondary">Batal</a>
    </div>
</form>
@endsection
