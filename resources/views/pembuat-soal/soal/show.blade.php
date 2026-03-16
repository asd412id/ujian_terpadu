@extends('layouts.admin')

@section('title', 'Preview Soal')

@push('head')
@endpush

@section('breadcrumb')
    <a href="{{ route('pembuat-soal.soal.index') }}" class="text-gray-500 hover:text-blue-600">Bank Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Preview Soal</span>
@endsection

@section('page-content')
<div class="max-w-4xl mx-auto space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900">Preview Soal</h1>
        <div class="flex items-center gap-2">
            @if($soal->is_verified)
            <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2.5 py-1 rounded-full">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Terverifikasi
            </span>
            @else
            <span class="inline-flex items-center gap-1 text-xs font-semibold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 002 0V6zm-1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                Belum Diverifikasi
            </span>
            @endif
            <a href="{{ route('pembuat-soal.soal.edit', $soal->id) }}"
               class="btn-primary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
            </a>
            <a href="{{ route('pembuat-soal.soal.index') }}"
               class="btn-secondary inline-flex items-center gap-2">
                Kembali
            </a>
        </div>
    </div>

    {{-- Meta Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @php
                $tipeLabel = [
                    'pg' => ['Pilihan Ganda', 'blue'],
                    'pilihan_ganda' => ['Pilihan Ganda', 'blue'],
                    'pg_kompleks' => ['PG Kompleks', 'purple'],
                    'pilihan_ganda_kompleks' => ['PG Kompleks', 'purple'],
                    'benar_salah' => ['Benar / Salah', 'indigo'],
                    'isian' => ['Isian Singkat', 'green'],
                    'essay' => ['Essay', 'amber'],
                    'menjodohkan' => ['Menjodohkan', 'pink'],
                ];
                [$tLabel, $tColor] = $tipeLabel[$soal->tipe_soal] ?? [$soal->tipe_soal, 'gray'];
            @endphp
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Jenis Soal</p>
                <span class="inline-block mt-1 text-xs font-semibold bg-{{ $tColor }}-100 text-{{ $tColor }}-700 px-2.5 py-1 rounded-full">{{ $tLabel }}</span>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Kategori</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $soal->kategori->nama ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Tingkat Kesulitan</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ ucfirst($soal->tingkat_kesulitan ?? '—') }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Bobot</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $soal->bobot }}</p>
            </div>
        </div>
    </div>

    {{-- Pertanyaan --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Pertanyaan</h2>
        <div class="ck-content text-gray-800 mathjax-process">
            @if($soal->pertanyaan === strip_tags($soal->pertanyaan))
                {!! nl2br(e($soal->pertanyaan)) !!}
            @else
                {!! $soal->pertanyaan !!}
            @endif
        </div>
        @if($soal->gambar_soal && !str_contains($soal->pertanyaan ?? '', '<img '))
        <div class="mt-4">
            <img src="{{ asset('storage/' . $soal->gambar_soal) }}" alt="Gambar soal" class="max-h-64 rounded-lg border">
        </div>
        @endif
    </div>

    {{-- Pilihan Ganda / PG Kompleks --}}
    @if(in_array($soal->tipe_soal, ['pg', 'pilihan_ganda', 'pg_kompleks', 'pilihan_ganda_kompleks']))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Pilihan Jawaban</h2>
        @if(in_array($soal->tipe_soal, ['pg_kompleks', 'pilihan_ganda_kompleks']))
        <p class="text-xs text-purple-600 font-medium mb-2">Pilihan ganda kompleks — bisa memilih lebih dari satu jawaban benar</p>
        @endif
        <div class="space-y-2">
            @foreach($soal->opsiJawaban->sortBy('urutan') as $opsi)
            <div class="flex items-start gap-3 p-3 rounded-lg {{ $opsi->is_benar ? 'bg-green-50 border border-green-200' : 'bg-gray-50' }}">
                @if(in_array($soal->tipe_soal, ['pg_kompleks', 'pilihan_ganda_kompleks']))
                <span class="flex-shrink-0 w-7 h-7 rounded flex items-center justify-center {{ $opsi->is_benar ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500' }}">
                    @if($opsi->is_benar)
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    @else
                    <span class="w-3.5 h-3.5 border-2 border-gray-400 rounded-sm"></span>
                    @endif
                </span>
                @else
                <span class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold {{ $opsi->is_benar ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                    {{ $opsi->label }}
                </span>
                @endif
                <div class="flex-1">
                    @if($opsi->teks)
                    <p class="text-sm text-gray-800 ck-content mathjax-process">{!! $opsi->teks === strip_tags($opsi->teks) ? e($opsi->teks) : $opsi->teks !!}</p>
                    @endif
                    @if($opsi->gambar)
                    <img src="{{ asset('storage/' . $opsi->gambar) }}" alt="Gambar opsi {{ $opsi->label }}" class="{{ $opsi->teks ? 'mt-2' : '' }} max-h-32 rounded border">
                    @endif
                    @if(!$opsi->teks && !$opsi->gambar)
                    <p class="text-sm text-gray-400 italic">—</p>
                    @endif
                </div>
                @if($opsi->is_benar)
                <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Menjodohkan --}}
    @if($soal->tipe_soal === 'menjodohkan')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Pasangan Soal</h2>
        <div class="space-y-2">
            @foreach($soal->pasangan as $i => $p)
            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">{{ $i + 1 }}</span>
                <div class="flex-1">
                    @if($p->kiri_gambar)
                    <img src="{{ asset('storage/' . $p->kiri_gambar) }}" alt="Gambar kiri" class="max-h-20 rounded border mb-1">
                    @endif
                    <span class="text-sm text-gray-800">{{ $p->kiri_teks }}</span>
                </div>
                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
                <div class="flex-1">
                    @if($p->kanan_gambar)
                    <img src="{{ asset('storage/' . $p->kanan_gambar) }}" alt="Gambar kanan" class="max-h-20 rounded border mb-1">
                    @endif
                    <span class="text-sm text-gray-800">{{ $p->kanan_teks }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Benar / Salah --}}
    @if($soal->tipe_soal === 'benar_salah')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Pernyataan Benar / Salah</h2>
        <div class="space-y-2">
            @foreach($soal->opsiJawaban->sortBy('urutan') as $opsi)
            <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 border border-gray-200">
                <span class="flex-shrink-0 w-auto min-w-[1.75rem] h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold px-1.5">{{ $loop->iteration }})</span>
                <div class="flex-1">
                    <span class="text-sm text-gray-800 ck-content mathjax-process">{!! $opsi->teks === strip_tags($opsi->teks) ? e($opsi->teks) : $opsi->teks !!}</span>
                    @if($opsi->gambar)
                    <img src="{{ asset('storage/' . $opsi->gambar) }}" alt="Gambar pernyataan" class="mt-1 max-h-24 rounded border">
                    @endif
                </div>
                @if($opsi->is_benar)
                <span class="text-xs font-semibold bg-green-100 text-green-700 px-2.5 py-1 rounded-full">BENAR</span>
                @else
                <span class="text-xs font-semibold bg-red-100 text-red-700 px-2.5 py-1 rounded-full">SALAH</span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Isian / Essay --}}
    @if(in_array($soal->tipe_soal, ['isian', 'essay']))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Kunci Jawaban</h2>
        <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
            <div class="text-sm text-gray-800 ck-content mathjax-process">{!! $soal->kunci_jawaban ?? '—' !!}</div>
        </div>
        @if($soal->pembahasan)
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mt-4 mb-2">Pembahasan / Panduan Penilaian</h2>
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
            <div class="text-sm text-gray-800 ck-content mathjax-process">{!! $soal->pembahasan !!}</div>
        </div>
        @endif
    </div>
    @endif

</div>
@endsection
