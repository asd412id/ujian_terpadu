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

{{-- Status & Publish/Draft (hanya saat edit) --}}
@if(isset($paket))
<div class="card max-w-4xl mb-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-gray-900">Status Paket</h2>
            @if($paket->status === 'draft')
                <span class="text-xs font-semibold bg-gray-200 text-gray-700 px-3 py-1 rounded-full">Draft</span>
            @elseif($paket->status === 'aktif')
                <span class="text-xs font-semibold bg-green-100 text-green-700 px-3 py-1 rounded-full">Aktif / Published</span>
            @elseif($paket->status === 'selesai')
                <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-3 py-1 rounded-full">Selesai</span>
            @else
                <span class="text-xs font-semibold bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full">{{ ucfirst($paket->status) }}</span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if($paket->status === 'draft')
            <form action="{{ route('dinas.paket.publish', $paket->id) }}" method="POST"
                  onsubmit="return confirm('Publikasikan paket ujian ini?')">
                @csrf
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Publish Paket
                </button>
            </form>
            @elseif($paket->status === 'aktif')
            <form action="{{ route('dinas.paket.draft', $paket->id) }}" method="POST"
                  onsubmit="return confirm('Kembalikan paket ke Draft?')">
                @csrf
                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Set ke Draft
                </button>
            </form>
            @endif
        </div>
    </div>
</div>
@endif

<form action="{{ isset($paket) ? route('dinas.paket.update', $paket->id) : route('dinas.paket.store') }}"
      method="POST" class="space-y-5 max-w-4xl">
    @csrf
    @if(isset($paket)) @method('PUT') @endif

    {{-- Informasi Paket --}}
    <div class="card space-y-5">
        <h2 class="font-semibold text-gray-900">Informasi Paket</h2>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Paket <span class="text-red-500">*</span></label>
            <input type="text" name="nama" value="{{ old('nama', $paket->nama ?? '') }}" required
                   class="form-input"
                   placeholder="Misal: TKA SMA Sesi 1 Tahun 2026">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
            <textarea name="deskripsi" rows="3"
                      class="form-input resize-y"
                      placeholder="Deskripsi singkat paket ujian...">{{ old('deskripsi', $paket->deskripsi ?? '') }}</textarea>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenjang <span class="text-red-500">*</span></label>
                <select name="jenjang" required class="form-input">
                    @foreach(['SD', 'SMP', 'SMA', 'SMK', 'MA', 'MTs', 'MI', 'SEMUA'] as $t)
                    <option value="{{ $t }}" {{ old('jenjang', $paket->jenjang ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenis Ujian <span class="text-red-500">*</span></label>
                <select name="jenis_ujian" required class="form-input">
                    @foreach(['TKA_SEKOLAH' => 'TKA Sekolah', 'SIMULASI_UTBK' => 'Simulasi UTBK', 'TRYOUT' => 'Try Out', 'ULANGAN' => 'Ulangan', 'PAS' => 'PAS', 'PAT' => 'PAT', 'LAINNYA' => 'Lainnya'] as $val => $label)
                    <option value="{{ $val }}" {{ old('jenis_ujian', $paket->jenis_ujian ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Durasi (menit) <span class="text-red-500">*</span></label>
                <input type="number" name="durasi_menit" min="10" max="480"
                       value="{{ old('durasi_menit', $paket->durasi_menit ?? 90) }}" required
                       class="form-input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Sekolah</label>
                <select name="sekolah_id" class="form-input">
                    <option value="">Semua Sekolah</option>
                    @foreach($sekolah as $s)
                    <option value="{{ $s->id }}" {{ old('sekolah_id', $paket->sekolah_id ?? '') == $s->id ? 'selected' : '' }}>{{ $s->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Pengaturan Waktu / Sesi --}}
    <div class="card space-y-5">
        <h2 class="font-semibold text-gray-900">Pengaturan Sesi</h2>
        <p class="text-sm text-gray-500 -mt-3">Atur jadwal pelaksanaan ujian dan batasan peserta.</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tanggal & Waktu Mulai</label>
                <input type="datetime-local" name="tanggal_mulai"
                       value="{{ old('tanggal_mulai', isset($paket) && $paket->tanggal_mulai ? $paket->tanggal_mulai->format('Y-m-d\TH:i') : '') }}"
                       class="form-input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tanggal & Waktu Selesai</label>
                <input type="datetime-local" name="tanggal_selesai"
                       value="{{ old('tanggal_selesai', isset($paket) && $paket->tanggal_selesai ? $paket->tanggal_selesai->format('Y-m-d\TH:i') : '') }}"
                       class="form-input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Maks. Peserta</label>
                <input type="number" name="max_peserta" min="1"
                       value="{{ old('max_peserta', $paket->max_peserta ?? '') }}"
                       class="form-input"
                       placeholder="Kosongkan = tanpa batas">
            </div>
        </div>

        @if(!isset($paket))
        <hr class="border-gray-200">
        <h3 class="text-sm font-semibold text-gray-700">Sesi Ujian Awal <span class="text-gray-400 text-xs font-normal">(opsional)</span></h3>
        <p class="text-sm text-gray-500 -mt-3">Buat sesi ujian langsung saat membuat paket. Sesi tambahan bisa dibuat nanti.</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Sesi</label>
                <input type="text" name="nama_sesi" value="{{ old('nama_sesi') }}"
                       class="form-input"
                       placeholder="Misal: Sesi 1 Pagi">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Ruangan</label>
                <input type="text" name="ruangan" value="{{ old('ruangan') }}"
                       class="form-input"
                       placeholder="Misal: R-01">
            </div>
        </div>
        @endif
    </div>

    {{-- Pengaturan Ujian --}}
    <div class="card space-y-4">
        <h2 class="font-semibold text-gray-900">Pengaturan Ujian</h2>

        <div class="space-y-3">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="acak_soal" value="1"
                       {{ old('acak_soal', $paket->acak_soal ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Acak urutan soal untuk setiap peserta</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="acak_opsi" value="1"
                       {{ old('acak_opsi', $paket->acak_opsi ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Acak urutan opsi jawaban (pilihan ganda)</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="tampilkan_hasil" value="1"
                       {{ old('tampilkan_hasil', $paket->tampilkan_hasil ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Tampilkan hasil ke peserta setelah ujian</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="boleh_kembali" value="1"
                       {{ old('boleh_kembali', $paket->boleh_kembali ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Peserta boleh kembali ke soal sebelumnya</span>
            </label>
        </div>
    </div>

    {{-- Action Buttons --}}
    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ isset($paket) ? 'Simpan Perubahan' : 'Buat Paket' }}
        </button>
        <a href="{{ route('dinas.paket.index') }}" class="btn-secondary">
            Batal
        </a>
    </div>
</form>
@endsection
