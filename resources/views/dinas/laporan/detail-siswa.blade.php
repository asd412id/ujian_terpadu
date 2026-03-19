@extends('layouts.admin')

@section('title', 'Detail Jawaban - ' . $sesiPeserta->peserta->nama)

@section('breadcrumb')
    <a href="{{ route('dinas.laporan') }}" class="text-gray-500 hover:text-blue-600">Laporan</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Detail Siswa</span>
@endsection

@section('page-content')
@php
    $peserta = $sesiPeserta->peserta;
    $paket = $sesiPeserta->sesi->paket;
    $totalBenar = collect($detail)->where('is_benar', true)->count();
    $totalTerjawab = collect($detail)->where('is_terjawab', true)->count();
    $totalSkor = collect($detail)->sum('skor');
@endphp
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $peserta->nama }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $peserta->nis ?? $peserta->nisn }} · {{ $peserta->sekolah?->nama }} · {{ $peserta->kelas }}
            </p>
        </div>
        <a href="{{ url()->previous() }}" class="btn-secondary">Kembali</a>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-gray-900">{{ count($detail) }}</p>
            <p class="text-xs text-gray-500 mt-1">Total Soal</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $totalTerjawab }}</p>
            <p class="text-xs text-gray-500 mt-1">Dijawab</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $totalBenar }}</p>
            <p class="text-xs text-gray-500 mt-1">Benar</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-red-500">{{ $totalTerjawab - $totalBenar }}</p>
            <p class="text-xs text-gray-500 mt-1">Salah</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold {{ ($sesiPeserta->nilai_akhir ?? 0) >= 70 ? 'text-green-600' : 'text-red-600' }}">
                {{ number_format($sesiPeserta->nilai_akhir ?? 0, 1) }}
            </p>
            <p class="text-xs text-gray-500 mt-1">Nilai Akhir</p>
        </div>
    </div>

    {{-- Detail Table --}}
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">Detail Jawaban Per Soal</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $paket->nama }} · {{ $sesiPeserta->sesi->nama_sesi }}</p>
        </div>
        {{-- Desktop table --}}
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-center w-12">No</th>
                        <th class="px-4 py-3 text-left">Soal</th>
                        <th class="px-4 py-3 text-center">Tipe</th>
                        <th class="px-4 py-3 text-center">Kunci</th>
                        <th class="px-4 py-3 text-center">Jawaban</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Skor</th>
                        <th class="px-4 py-3 text-center">Bobot</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($detail as $item)
                    <tr class="hover:bg-gray-50 cursor-pointer"
                        x-data="{ open: false }"
                        @click="open = !open">
                        <td class="px-4 py-3 text-center text-gray-400 text-xs">{{ $item['nomor'] }}</td>
                        <td class="px-4 py-3 text-gray-900 text-xs max-w-xs truncate">{{ $item['pertanyaan'] ?: '(tanpa teks)' }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $tipeLabel = match($item['tipe']) {
                                    'pg' => 'PG',
                                    'pg_kompleks' => 'PGK',
                                    'menjodohkan' => 'Jodoh',
                                    'benar_salah' => 'BS',
                                    'isian' => 'Isian',
                                    'essay' => 'Essay',
                                    default => $item['tipe'],
                                };
                            @endphp
                            <span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $tipeLabel }}</span>
                        </td>
                        <td class="px-4 py-3 text-center text-xs font-mono text-blue-700 font-semibold">
                            {{ $item['kunci'] }}
                        </td>
                        <td class="px-4 py-3 text-center text-xs font-mono">
                            @if($item['jawaban_display'] !== '-' && $item['jawaban_display'] !== $item['jawaban'])
                                <span class="text-gray-400">{{ $item['jawaban_display'] }}</span>
                            @else
                                {{ $item['jawaban'] }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if(!$item['is_terjawab'])
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Kosong</span>
                            @elseif($item['is_benar'])
                                <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Benar</span>
                            @else
                                <span class="text-xs font-semibold bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Salah</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center font-bold {{ $item['skor'] > 0 ? 'text-green-600' : 'text-gray-400' }}">{{ $item['skor'] }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $item['bobot'] }}</td>
                    </tr>
                    {{-- Expandable detail row --}}
                    @if(!empty($item['opsi']))
                    <tr x-show="open" x-cloak x-transition class="bg-gray-50/50">
                        <td></td>
                        <td colspan="7" class="px-4 py-3">
                            <div class="text-xs space-y-1">
                                <p class="font-semibold text-gray-600 mb-1.5">Opsi jawaban:</p>
                                @foreach($item['opsi'] as $opsi)
                                <div class="flex items-start gap-2 py-0.5 {{ $opsi['is_benar'] ? 'text-green-700 font-semibold' : 'text-gray-600' }}">
                                    <span class="font-mono w-5 shrink-0">{{ $opsi['label'] }}</span>
                                    @if($opsi['display_label'] !== $opsi['label'])
                                        <span class="text-gray-400 w-8 shrink-0">({{ $opsi['display_label'] }})</span>
                                    @endif
                                    <span>{{ $opsi['teks'] }}</span>
                                    @if($opsi['is_benar'])
                                        <svg class="w-3.5 h-3.5 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @foreach($detail as $item)
            @php
                $tipeLabel = match($item['tipe']) {
                    'pg' => 'PG',
                    'pg_kompleks' => 'PGK',
                    'menjodohkan' => 'Jodoh',
                    'benar_salah' => 'BS',
                    'isian' => 'Isian',
                    'essay' => 'Essay',
                    default => $item['tipe'],
                };
            @endphp
            <div class="px-4 py-3" x-data="{ open: false }">
                <div class="flex items-start justify-between gap-2" @click="open = !open">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-bold text-gray-400">#{{ $item['nomor'] }}</span>
                            <span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $tipeLabel }}</span>
                            @if(!$item['is_terjawab'])
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Kosong</span>
                            @elseif($item['is_benar'])
                                <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Benar</span>
                            @else
                                <span class="text-xs font-semibold bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Salah</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-700 line-clamp-2">{{ $item['pertanyaan'] ?: '(tanpa teks)' }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-bold {{ $item['skor'] > 0 ? 'text-green-600' : 'text-gray-400' }}">{{ $item['skor'] }}</p>
                        <p class="text-xs text-gray-400">/ {{ $item['bobot'] }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                    <span>Kunci: <span class="font-mono font-semibold text-blue-700">{{ $item['kunci'] }}</span></span>
                    <span>Jawaban: <span class="font-mono font-semibold text-gray-900">
                        @if($item['jawaban_display'] !== '-' && $item['jawaban_display'] !== $item['jawaban'])
                            {{ $item['jawaban_display'] }}
                        @else
                            {{ $item['jawaban'] }}
                        @endif
                    </span></span>
                </div>
                {{-- Expandable options --}}
                @if(!empty($item['opsi']))
                <div x-show="open" x-cloak x-transition class="mt-2 pt-2 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-600 mb-1">Opsi jawaban:</p>
                    @foreach($item['opsi'] as $opsi)
                    <div class="flex items-start gap-2 py-0.5 text-xs {{ $opsi['is_benar'] ? 'text-green-700 font-semibold' : 'text-gray-600' }}">
                        <span class="font-mono w-5 shrink-0">{{ $opsi['label'] }}</span>
                        @if($opsi['display_label'] !== $opsi['label'])
                            <span class="text-gray-400 w-8 shrink-0">({{ $opsi['display_label'] }})</span>
                        @endif
                        <span>{{ $opsi['teks'] }}</span>
                        @if($opsi['is_benar'])
                            <svg class="w-3.5 h-3.5 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
