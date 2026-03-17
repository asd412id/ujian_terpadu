@extends('layouts.admin')

@section('title', isset($narasi) ? 'Edit Narasi Soal' : 'Tambah Narasi Soal')

@section('breadcrumb')
    <a href="{{ route('pembuat-soal.soal.index', ['tab' => 'narasi']) }}" class="text-gray-500 hover:text-blue-600">Bank Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ isset($narasi) ? 'Edit' : 'Tambah' }}</span>
@endsection

@section('page-content')
<form action="{{ isset($narasi) ? route('pembuat-soal.narasi.update', $narasi->id) : route('pembuat-soal.narasi.store') }}"
      method="POST" class="space-y-5 max-w-3xl">
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
            <div x-data="richEditor({
                name: 'konten',
                content: @js(old('konten', $narasi->konten ?? '')),
                placeholder: 'Ketikkan teks bacaan/paragraf yang akan ditampilkan kepada siswa... (Ctrl+V untuk paste gambar)',
                uploadUrl: '{{ route('pembuat-soal.narasi.upload-image') }}',
                minimal: false
            })">
                <div class="ck-editor-wrap">
                    <div x-ref="editorEl"></div>
                </div>
                <input type="hidden" name="konten" x-ref="hiddenInput">
            </div>
            <p class="text-xs text-gray-400 mt-1">Paste gambar langsung dari clipboard (Ctrl+V). Mendukung format teks kaya, tabel, dan gambar inline.</p>
            @error('konten') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
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
        <a href="{{ route('pembuat-soal.soal.index', ['tab' => 'narasi']) }}" class="btn-secondary">Batal</a>
    </div>
</form>
@endsection
