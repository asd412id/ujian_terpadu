@extends('layouts.admin')

@section('title', 'Paket Ujian Sekolah')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Paket Ujian</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Paket Ujian & Sesi</h1>
            <p class="text-sm text-gray-500 mt-1">Ringkasan paket aktif yang tersedia untuk sekolah Anda.</p>
        </div>
    </div>

    @if($paketList->isEmpty())
    <div class="card text-center py-16">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-gray-500">Belum ada paket ujian yang tersedia.</p>
        <p class="text-gray-400 text-sm mt-1">Hubungi admin dinas untuk mendapatkan akses paket ujian.</p>
    </div>
    @else
    <div class="grid gap-5 lg:grid-cols-2">
        @foreach($paketList as $paket)
        <a href="{{ route('sekolah.paket.show', $paket->id) }}" class="card block hover:shadow-md transition-shadow duration-200">
            <div class="flex flex-col gap-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <h2 class="font-semibold text-gray-900 text-lg leading-tight">{{ $paket->nama }}</h2>
                            <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $paket->jenjang }}</span>
                            <span class="text-xs font-semibold {{ $paket->status === 'aktif' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }} px-2 py-0.5 rounded-full">{{ ucfirst($paket->status) }}</span>
                        </div>
                        <p class="text-sm text-gray-500 line-clamp-2">{{ $paket->deskripsi ?: 'Paket ujian tersedia untuk sekolah ini.' }}</p>
                    </div>
                    <div class="flex-shrink-0 w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="rounded-xl bg-gray-50 px-3 py-2">
                        <p class="text-[11px] uppercase tracking-wide text-gray-400">Durasi</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $paket->durasi_menit }} menit</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2">
                        <p class="text-[11px] uppercase tracking-wide text-gray-400">Soal</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $paket->paketSoal->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2">
                        <p class="text-[11px] uppercase tracking-wide text-gray-400">Sesi</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $paket->sesi->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2">
                        <p class="text-[11px] uppercase tracking-wide text-gray-400">Peserta</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $paket->sesi->sum(fn ($sesi) => $sesi->sesiPeserta->count()) }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                        <span class="px-2 py-1 rounded-full bg-gray-100">{{ $paket->jenis_ujian_label ?? 'Jenis tidak disebutkan' }}</span>
                        @if($paket->tanggal_mulai)
                        <span class="px-2 py-1 rounded-full bg-gray-100">Mulai {{ $paket->tanggal_mulai->format('d/m/Y H:i') }}</span>
                        @endif
                        @if($paket->tanggal_selesai)
                        <span class="px-2 py-1 rounded-full bg-gray-100">Selesai {{ $paket->tanggal_selesai->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>

                    <div class="space-y-2">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Sesi Ujian</p>
                        @forelse($paket->sesi as $sesi)
                        <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 text-sm">{{ $sesi->nama_sesi }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ $sesi->waktu_mulai ? \Carbon\Carbon::parse($sesi->waktu_mulai)->format('d/m/Y H:i') : 'Belum dijadwalkan' }}
                                    </p>
                                </div>
                                <div class="flex-shrink-0">
                                    @if($sesi->status === 'berlangsung')
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2 py-1 rounded-full">
                                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                            Live
                                        </span>
                                    @elseif($sesi->status === 'persiapan')
                                        <span class="text-xs font-semibold bg-blue-100 text-blue-600 px-2 py-1 rounded-full">Persiapan</span>
                                    @elseif($sesi->status === 'selesai')
                                        <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-1 rounded-full">Selesai</span>
                                    @else
                                        <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-1 rounded-full">{{ ucfirst($sesi->status) }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                                <span>{{ $sesi->sesiPeserta->count() }} peserta terdaftar</span>
                                <span>{{ $sesi->sesiPeserta->where('status', 'mengerjakan')->count() }} sedang ujian</span>
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-gray-400 py-2">Belum ada sesi ujian untuk sekolah Anda.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </a>
        @endforeach
    </div>
    @endif

</div>
@endsection