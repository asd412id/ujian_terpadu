@extends('layouts.admin')

@section('title', 'Penilaian Essay')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Penilaian Essay</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Penilaian Soal Essay</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                <span class="font-semibold text-amber-600">{{ $totalBelumDinilai }}</span> jawaban menunggu penilaian
            </p>
        </div>
        {{-- Filter --}}
        <form method="GET" action="{{ route('dinas.grading') }}" class="flex items-center gap-2">
            <select name="paket_id" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Paket</option>
                @foreach($paketList as $p)
                <option value="{{ $p->id }}" {{ request('paket_id') == $p->id ? 'selected' : '' }}>{{ $p->nama }}</option>
                @endforeach
            </select>
            <div class="w-56" x-data x-on:change="$el.closest('form').submit()">
                <x-searchable-select
                    name="sekolah_id"
                    :options="$sekolahList->map(fn($s) => ['id' => $s->id, 'text' => $s->nama])"
                    :value="request('sekolah_id')"
                    placeholder="Semua Sekolah" />
            </div>
        </form>
    </div>

    @forelse($jawabans as $jawaban)
    <div class="card space-y-4" id="jawaban-{{ $jawaban->id }}">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <p class="font-semibold text-gray-900">{{ $jawaban->sesiPeserta->peserta->nama ?? '—' }}</p>
                <p class="text-xs text-gray-500">
                    {{ $jawaban->sesiPeserta->peserta->sekolah->nama ?? '—' }} ·
                    Kelas {{ $jawaban->sesiPeserta->peserta->kelas ?? '—' }}
                </p>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="text-xs text-gray-500">Soal #{{ $loop->iteration }}</p>
                <p class="text-xs text-gray-400">Bobot: {{ $jawaban->soal->bobot }}</p>
            </div>
        </div>

        {{-- Pertanyaan --}}
        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
            <p class="text-xs text-blue-500 font-medium uppercase tracking-wide mb-2">Pertanyaan</p>
            <div class="text-sm text-gray-800 ck-content mathjax-process">
                {!! $jawaban->soal->pertanyaan !!}
            </div>
            @if($jawaban->soal->gambar_soal && !str_contains($jawaban->soal->pertanyaan ?? '', '<img '))
            <img src="{{ Storage::url($jawaban->soal->gambar_soal) }}" alt="Gambar soal"
                 class="mt-3 max-h-40 rounded-lg border border-blue-200 object-contain">
            @endif
        </div>

        {{-- Jawaban Peserta --}}
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-2">Jawaban Peserta</p>
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $jawaban->jawaban_teks ?? '(Tidak dijawab)' }}</p>
        </div>

        {{-- Panduan Penilaian --}}
        @if($jawaban->soal->pembahasan)
        <div class="bg-green-50 border border-green-100 rounded-xl p-4">
            <p class="text-xs text-green-600 font-medium uppercase tracking-wide mb-2">Panduan Penilaian</p>
            <p class="text-sm text-gray-700">{{ $jawaban->soal->pembahasan }}</p>
        </div>
        @endif

        {{-- Form Nilai --}}
        <form action="{{ route('dinas.grading.nilai', $jawaban->id) }}" method="POST"
              class="flex items-end gap-3 flex-wrap"
              x-data="{ nilai: {{ $jawaban->skor_manual ?? 0 }}, maks: {{ $jawaban->soal->bobot }} }">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Nilai (0 – <span x-text="maks">{{ $jawaban->soal->bobot }}</span>)
                </label>
                    <div class="flex items-center gap-3">
                        <input type="range" x-model="nilai"
                               :min="0" :max="maks" :step="maks > 10 ? 1 : 0.5"
                               class="flex-1 accent-blue-600">
                        <span class="w-10 text-center font-bold text-lg text-blue-700" x-text="nilai"></span>
                    <input type="hidden" name="skor_manual" :value="nilai">
                </div>
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Catatan Penilai (opsional)</label>
                <input type="text" name="catatan_penilai"
                       value="{{ $jawaban->catatan_penilai ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Umpan balik untuk peserta...">
            </div>
            <button type="submit"
                    class="btn-primary flex-shrink-0 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Simpan Nilai
            </button>
        </form>
    </div>
    @empty
    <div class="card text-center py-16">
        <svg class="w-12 h-12 text-green-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-gray-500 font-medium">Semua jawaban essay sudah dinilai!</p>
        <p class="text-gray-400 text-sm mt-1">Tidak ada jawaban yang menunggu penilaian.</p>
    </div>
    @endforelse

    @if($jawabans->hasPages())
    <div>{{ $jawabans->withQueryString()->links() }}</div>
    @endif

</div>
@endsection
