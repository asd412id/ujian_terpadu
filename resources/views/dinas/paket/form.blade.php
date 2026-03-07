@extends('layouts.admin')

@section('title', isset($paket) ? 'Edit Paket Ujian' : 'Buat Paket Ujian')

@section('breadcrumb')
    <a href="{{ route('dinas.paket.index') }}" class="text-gray-500 hover:text-blue-600">Paket Ujian</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ isset($paket) ? 'Edit' : 'Buat Baru' }}</span>
@endsection

@section('page-content')
<form action="{{ isset($paket) ? route('dinas.paket.update', $paket->id) : route('dinas.paket.store') }}"
      method="POST" class="space-y-5 max-w-2xl">
    @csrf
    @if(isset($paket)) @method('PUT') @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card space-y-5">
        <h2 class="font-semibold text-gray-900">Informasi Paket</h2>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Paket <span class="text-red-500">*</span></label>
            <input type="text" name="nama" value="{{ old('nama', $paket->nama ?? '') }}" required
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Misal: TKA SMA Sesi 1 Tahun 2026">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
            <textarea name="deskripsi" rows="3"
                      class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                      placeholder="Deskripsi singkat paket ujian...">{{ old('deskripsi', $paket->deskripsi ?? '') }}</textarea>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenjang <span class="text-red-500">*</span></label>
                <select name="jenjang" required
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach(['SD', 'SMP', 'SMA', 'SMK', 'MA', 'MTs', 'MI', 'SEMUA'] as $t)
                    <option value="{{ $t }}" {{ old('jenjang', $paket->jenjang ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenis Ujian <span class="text-red-500">*</span></label>
                <select name="jenis_ujian" required
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach(['TKA_SEKOLAH' => 'TKA Sekolah', 'SIMULASI_UTBK' => 'Simulasi UTBK', 'TRYOUT' => 'Try Out', 'ULANGAN' => 'Ulangan', 'PAS' => 'PAS', 'PAT' => 'PAT', 'LAINNYA' => 'Lainnya'] as $val => $label)
                    <option value="{{ $val }}" {{ old('jenis_ujian', $paket->jenis_ujian ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Durasi (menit) <span class="text-red-500">*</span></label>
                <input type="number" name="durasi_menit" min="10" max="480"
                       value="{{ old('durasi_menit', $paket->durasi_menit ?? 90) }}" required
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Sekolah</label>
                <select name="sekolah_id"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Sekolah</option>
                    @foreach($sekolah as $s)
                    <option value="{{ $s->id }}" {{ old('sekolah_id', $paket->sekolah_id ?? '') == $s->id ? 'selected' : '' }}>{{ $s->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="space-y-3">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="acak_soal" value="1"
                       {{ old('acak_soal', $paket->acak_soal ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Acak urutan soal untuk setiap peserta</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="tampilkan_hasil" value="1"
                       {{ old('tampilkan_hasil', $paket->tampilkan_hasil ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Tampilkan hasil ke peserta setelah ujian</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="boleh_kembali" value="1"
                       {{ old('boleh_kembali', $paket->boleh_kembali ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Peserta boleh kembali ke soal sebelumnya</span>
            </label>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ isset($paket) ? 'Simpan Perubahan' : 'Buat Paket' }}
        </button>
        <a href="{{ route('dinas.paket.index') }}"
           class="border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-medium px-4 py-2.5 rounded-xl transition-colors">
            Batal
        </a>
    </div>
</form>
@endsection
