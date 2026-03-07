@extends('layouts.admin')

@section('title', 'Paket Ujian Sekolah')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Paket Ujian</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <h1 class="text-xl font-bold text-gray-900">Paket Ujian & Sesi</h1>

    @if($paketList->isEmpty())
    <div class="card text-center py-16">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-gray-500">Belum ada paket ujian yang tersedia.</p>
        <p class="text-gray-400 text-sm mt-1">Hubungi admin dinas untuk mendapatkan akses paket ujian.</p>
    </div>
    @else
    @foreach($paketList as $paket)
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <h2 class="font-semibold text-gray-900">{{ $paket->nama }}</h2>
                    <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $paket->jenjang }}</span>
                </div>
                <p class="text-sm text-gray-500">{{ $paket->durasi_menit }} menit · {{ $paket->paketSoal->count() }} soal</p>
            </div>
        </div>

        {{-- Sesi Ujian untuk paket ini --}}
        <div class="space-y-2">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Sesi Ujian</p>
            @forelse($paket->sesi as $sesi)
            <div class="flex items-center gap-3 bg-gray-50 rounded-xl px-4 py-3">
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 text-sm">{{ $sesi->nama_sesi }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $sesi->waktu_mulai ? \Carbon\Carbon::parse($sesi->waktu_mulai)->format('d/m/Y H:i') : 'Belum dijadwalkan' }}
                    </p>
                </div>
                <div class="flex-shrink-0">
                    @if($sesi->status === 'berlangsung')
                        <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2 py-1 rounded-full">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                            Live
                        </span>
                    @elseif($sesi->status === 'menunggu')
                        <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-1 rounded-full">Menunggu</span>
                    @else
                        <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-1 rounded-full">Selesai</span>
                    @endif
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-xs font-medium text-gray-700">{{ $sesi->sesiPeserta->count() }}</p>
                    <p class="text-xs text-gray-400">peserta</p>
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-400 py-2">Belum ada sesi ujian untuk sekolah Anda.</p>
            @endforelse
        </div>
    </div>
    @endforeach
    @endif

</div>
@endsection
