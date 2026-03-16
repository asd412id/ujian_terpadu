@extends('layouts.admin')

@section('title', 'Preview Semua Soal')

@push('head')
@endpush

@section('breadcrumb')
    <a href="{{ route('dinas.soal.index') }}" class="text-gray-500 hover:text-blue-600">Bank Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Preview Semua Soal</span>
@endsection

@section('page-content')
<div class="max-w-4xl mx-auto space-y-5" x-data="{ showNav: false }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Preview Semua Soal</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $soalList->count() }} soal ditampilkan</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('dinas.soal.index') }}"
               class="btn-secondary inline-flex items-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali
            </a>
        </div>
    </div>

    {{-- Filter Kategori --}}
    <form method="GET" action="{{ route('dinas.soal.preview-all') }}"
          class="card flex flex-col sm:flex-row gap-3 p-4">
        <select name="kategori"
                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            @foreach($kategori as $kat)
            <option value="{{ $kat->id }}" {{ request('kategori') == $kat->id ? 'selected' : '' }}>
                {{ $kat->nama }} ({{ $soalList->where('kategori_id', $kat->id)->count() }})
            </option>
            @endforeach
        </select>
        @if(request('kategori'))
        <a href="{{ route('dinas.soal.preview-all') }}" class="btn-secondary text-center text-sm">Reset</a>
        @endif
    </form>

    {{-- Navigasi Soal (floating) --}}
    <button @click="showNav = !showNav"
            class="fixed bottom-6 right-6 z-40 w-12 h-12 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg flex items-center justify-center transition-colors print:hidden"
            title="Navigasi Soal">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
        </svg>
    </button>

    <div x-show="showNav" x-cloak x-transition
         @click.outside="showNav = false"
         class="fixed bottom-20 right-6 z-40 bg-white rounded-xl shadow-2xl border border-gray-200 p-4 max-h-[60vh] overflow-y-auto w-72 print:hidden">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">Navigasi Soal</h3>
        <div class="space-y-1">
            @foreach($soalList as $idx => $s)
            <a href="#soal-{{ $idx + 1 }}"
               @click="showNav = false"
               class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs hover:bg-blue-50 transition-colors group">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-100 group-hover:bg-blue-100 text-gray-600 group-hover:text-blue-700 flex items-center justify-center font-bold text-xs">{{ $idx + 1 }}</span>
                <span class="truncate text-gray-700">{{ strip_tags($s->pertanyaan) }}</span>
            </a>
            @endforeach
        </div>
    </div>

    {{-- Soal List --}}
    @forelse($soalList as $idx => $soal)
    <div id="soal-{{ $idx + 1 }}" class="scroll-mt-20 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            {{-- Soal Header --}}
            <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <span class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold">{{ $idx + 1 }}</span>
                    <div class="flex items-center gap-2 flex-wrap">
                        @php
                            $tipeLabel = [
                                'pg' => ['PG', 'blue'], 'pilihan_ganda' => ['PG', 'blue'],
                                'pg_kompleks' => ['PGK', 'purple'], 'pilihan_ganda_kompleks' => ['PGK', 'purple'],
                                'benar_salah' => ['B/S', 'indigo'],
                                'isian' => ['Isian', 'green'],
                                'essay' => ['Essay', 'amber'],
                                'menjodohkan' => ['Jodoh', 'pink'],
                            ];
                            [$tLabel, $tColor] = $tipeLabel[$soal->tipe_soal] ?? [$soal->tipe_soal, 'gray'];
                        @endphp
                        <span class="text-xs font-semibold bg-{{ $tColor }}-100 text-{{ $tColor }}-700 px-2 py-0.5 rounded-full">{{ $tLabel }}</span>
                        <span class="text-xs text-gray-500">{{ $soal->kategori->nama ?? '—' }}</span>
                        <span class="text-xs text-gray-400">· {{ ucfirst($soal->tingkat_kesulitan ?? 'sedang') }} · Bobot {{ $soal->bobot }}</span>
                    </div>
                </div>
                <a href="{{ route('dinas.soal.edit', $soal->id) }}"
                   class="text-xs text-blue-600 hover:text-blue-800 font-medium flex-shrink-0">Edit</a>
            </div>

            <div class="p-5 space-y-4">
                {{-- Narasi --}}
                @if($soal->narasi)
                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-2">
                    <div class="flex items-center gap-2 mb-1.5">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="text-xs font-semibold text-indigo-700">{{ $soal->narasi->judul }}</span>
                    </div>
                    <div class="prose prose-sm max-w-none text-gray-700">{!! $soal->narasi->konten !!}</div>
                </div>
                @endif

                {{-- Pertanyaan --}}
                <div class="ck-content text-gray-800 mathjax-process">
                    @if($soal->pertanyaan === strip_tags($soal->pertanyaan))
                        {!! nl2br(e($soal->pertanyaan)) !!}
                    @else
                        {!! $soal->pertanyaan !!}
                    @endif
                </div>

                @php $hasInlineImg = str_contains($soal->pertanyaan ?? '', '<img '); @endphp
                @if($soal->gambar_soal && !$hasInlineImg)
                <div>
                    <img src="{{ asset('storage/' . $soal->gambar_soal) }}" alt="Gambar soal" class="max-h-48 rounded-lg border">
                </div>
                @endif

                {{-- Pilihan Ganda / PG Kompleks --}}
                @if(in_array($soal->tipe_soal, ['pg', 'pilihan_ganda', 'pg_kompleks', 'pilihan_ganda_kompleks']))
                <div class="space-y-1.5">
                    @foreach($soal->opsiJawaban->sortBy('urutan') as $opsi)
                    <div class="flex items-start gap-2.5 px-3 py-2 rounded-lg {{ $opsi->is_benar ? 'bg-green-50 border border-green-200' : 'bg-gray-50' }}">
                        <span class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ $opsi->is_benar ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600' }}">{{ $opsi->label }}</span>
                        <div class="flex-1 min-w-0">
                            @if($opsi->teks)
                            <span class="text-sm text-gray-800 ck-content mathjax-process">{!! $opsi->teks === strip_tags($opsi->teks) ? e($opsi->teks) : $opsi->teks !!}</span>
                            @endif
                            @if($opsi->gambar)
                            <img src="{{ asset('storage/' . $opsi->gambar) }}" alt="Gambar opsi {{ $opsi->label }}" class="{{ $opsi->teks ? 'mt-1' : '' }} max-h-24 rounded border">
                            @endif
                            @if(!$opsi->teks && !$opsi->gambar)
                            <span class="text-sm text-gray-400 italic">—</span>
                            @endif
                        </div>
                        @if($opsi->is_benar)
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Menjodohkan --}}
                @if($soal->tipe_soal === 'menjodohkan')
                <div class="space-y-1.5">
                    @foreach($soal->pasangan as $i => $p)
                    <div class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 rounded-lg">
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">{{ $i + 1 }}</span>
                        <div class="flex-1">
                            @if($p->kiri_gambar)<img src="{{ asset('storage/' . $p->kiri_gambar) }}" class="max-h-12 rounded border mb-0.5">@endif
                            <span class="text-sm text-gray-800">{{ $p->kiri_teks }}</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                        <div class="flex-1">
                            @if($p->kanan_gambar)<img src="{{ asset('storage/' . $p->kanan_gambar) }}" class="max-h-12 rounded border mb-0.5">@endif
                            <span class="text-sm text-gray-800">{{ $p->kanan_teks }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Benar / Salah --}}
                @if($soal->tipe_soal === 'benar_salah')
                <div class="space-y-1.5">
                    @foreach($soal->opsiJawaban->sortBy('urutan') as $opsi)
                    <div class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 rounded-lg border border-gray-200">
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">{{ $opsi->label }}</span>
                        <div class="flex-1">
                            <span class="text-sm text-gray-800 ck-content mathjax-process">{!! $opsi->teks === strip_tags($opsi->teks) ? e($opsi->teks) : $opsi->teks !!}</span>
                        </div>
                        @if($opsi->is_benar)
                        <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">BENAR</span>
                        @else
                        <span class="text-xs font-semibold bg-red-100 text-red-700 px-2 py-0.5 rounded-full">SALAH</span>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Isian / Essay --}}
                @if(in_array($soal->tipe_soal, ['isian', 'essay']))
                <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Kunci Jawaban</p>
                    <p class="text-sm text-gray-800">{{ $soal->kunci_jawaban ?? '—' }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="card p-12 text-center text-gray-400">
        <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Tidak ada soal ditemukan.
    </div>
    @endforelse

    {{-- Back to top --}}
    @if($soalList->count() > 5)
    <div class="text-center py-4">
        <a href="#" class="text-sm text-blue-600 hover:text-blue-800 font-medium">↑ Kembali ke atas</a>
    </div>
    @endif

</div>
@endsection
