@extends('layouts.admin')

@section('title', isset($peserta) ? 'Edit Peserta' : 'Tambah Peserta')

@section('breadcrumb')
    <a href="{{ route('dinas.peserta.index') }}" class="text-gray-500 hover:text-blue-600">Peserta</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ isset($peserta) ? 'Edit' : 'Tambah' }}</span>
@endsection

@section('page-content')
<form action="{{ isset($peserta) ? route('dinas.peserta.update', $peserta->id) : route('dinas.peserta.store') }}"
      method="POST" class="space-y-5 max-w-xl">
    @csrf
    @if(isset($peserta)) @method('PUT') @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="card space-y-4">
        <h2 class="font-semibold text-gray-900">Data Peserta</h2>

        {{-- Sekolah (wajib untuk dinas admin) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Sekolah <span class="text-red-500">*</span></label>
            <x-searchable-select
                name="sekolah_id"
                :required="true"
                :options="$sekolahList->map(fn($s) => ['id' => $s->id, 'text' => '[' . $s->jenjang . '] ' . $s->nama])"
                :value="old('sekolah_id', $peserta->sekolah_id ?? '')"
                placeholder="— Pilih Sekolah —" />
        </div>

        {{-- Nama --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
            <input type="text" name="nama" value="{{ old('nama', $peserta->nama ?? '') }}" required
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('nama') border-red-400 @enderror"
                   placeholder="Nama lengkap peserta">
        </div>

        {{-- NIS & NISN --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">NIS</label>
                <input type="text" name="nis" value="{{ old('nis', $peserta->nis ?? '') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Nomor Induk Siswa">
                <p class="text-xs text-gray-400 mt-1">Digunakan sebagai username login.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">NISN</label>
                <input type="text" name="nisn" value="{{ old('nisn', $peserta->nisn ?? '') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="10 digit NISN">
            </div>
        </div>

        {{-- Kelas & Jurusan --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Kelas</label>
                <input type="text" name="kelas" value="{{ old('kelas', $peserta->kelas ?? '') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Misal: XII IPA 1">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenis Kelamin</label>
                <select name="jenis_kelamin"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">—</option>
                    <option value="L" {{ old('jenis_kelamin', $peserta->jenis_kelamin ?? '') === 'L' ? 'selected' : '' }}>Laki-laki</option>
                    <option value="P" {{ old('jenis_kelamin', $peserta->jenis_kelamin ?? '') === 'P' ? 'selected' : '' }}>Perempuan</option>
                </select>
            </div>
        </div>

        {{-- Password --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Password Ujian
                @if(!isset($peserta))
                    <span class="text-gray-400 font-normal">(kosong = generate otomatis)</span>
                @else
                    <span class="text-gray-400 font-normal">(kosong = tidak diubah)</span>
                @endif
            </label>
            <input type="text" name="password_ujian"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                   placeholder="{{ isset($peserta) ? '••••••••' : 'Kosongkan untuk generate otomatis' }}">
        </div>

        @if(isset($peserta))
        {{-- Status aktif --}}
        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $peserta->is_active ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Peserta aktif (dapat login ujian)</span>
            </label>
        </div>
        @endif
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ isset($peserta) ? 'Simpan Perubahan' : 'Tambah Peserta' }}
        </button>
        <a href="{{ route('dinas.peserta.index') }}"
           class="btn-secondary">
            Batal
        </a>
    </div>
</form>
@endsection
