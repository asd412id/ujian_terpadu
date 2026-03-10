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
        <a href="{{ route('dinas.laporan') }}" class="btn-secondary">Kembali ke Laporan</a>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-gray-900">{{ $paket->jumlah_soal }}</p>
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
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-center w-12">No</th>
                        <th class="px-4 py-3 text-left">Soal</th>
                        <th class="px-4 py-3 text-center">Tipe</th>
                        <th class="px-4 py-3 text-center">Jawaban</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Skor</th>
                        <th class="px-4 py-3 text-center">Bobot</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($detail as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-center text-gray-400 text-xs">{{ $item['nomor'] }}</td>
                        <td class="px-4 py-3 text-gray-900 text-xs max-w-xs truncate">{{ $item['pertanyaan'] ?: '(tanpa teks)' }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $tipeLabel = match($item['tipe']) {
                                    'pilihan_ganda' => 'PG',
                                    'pilihan_ganda_kompleks' => 'PGK',
                                    'menjodohkan' => 'Jodoh',
                                    'benar_salah' => 'BS',
                                    'isian' => 'Isian',
                                    'essay' => 'Essay',
                                    default => $item['tipe'],
                                };
                            @endphp
                            <span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $tipeLabel }}</span>
                        </td>
                        <td class="px-4 py-3 text-center text-xs font-mono">
                            @if(is_array($item['jawaban']))
                                {{ implode(', ', $item['jawaban']) }}
                            @else
                                {{ Str::limit((string) $item['jawaban'], 30) }}
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
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
