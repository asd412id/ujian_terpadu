@extends('layouts.admin')

@section('title', 'Detail Paket Ujian')

@section('breadcrumb')
    <a href="{{ route('sekolah.paket') }}" class="text-gray-500 hover:text-blue-600">Paket Ujian</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ $paket->nama }}</span>
@endsection

@section('page-content')
<div class="space-y-6">
    <div class="card">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-5">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $paket->nama }}</h1>
                    <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $paket->jenjang }}</span>
                    <span class="text-xs font-semibold {{ $paket->status === 'aktif' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }} px-2 py-0.5 rounded-full">{{ ucfirst($paket->status) }}</span>
                </div>
                <p class="text-sm text-gray-500 max-w-3xl">{{ $paket->deskripsi ?: 'Detail paket ujian yang tersedia untuk sekolah Anda.' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-6">
            <div class="rounded-xl bg-gray-50 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-gray-400">Jenis Ujian</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $paket->jenis_ujian_label ?? '—' }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-gray-400">Durasi</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $paket->durasi_menit }} menit</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-gray-400">Jumlah Soal</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $paket->paketSoal->count() }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-gray-400">Total Peserta</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $paket->sesi->sum(fn ($sesi) => $sesi->sesiPeserta->count()) }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 text-xs text-gray-500 mb-6">
            @if($paket->tanggal_mulai)
            <span class="px-2 py-1 rounded-full bg-gray-100">Mulai {{ $paket->tanggal_mulai->format('d/m/Y H:i') }}</span>
            @endif
            @if($paket->tanggal_selesai)
            <span class="px-2 py-1 rounded-full bg-gray-100">Selesai {{ $paket->tanggal_selesai->format('d/m/Y H:i') }}</span>
            @endif
            @if($paket->acak_soal)
            <span class="px-2 py-1 rounded-full bg-gray-100">Acak soal</span>
            @endif
            @if($paket->acak_opsi)
            <span class="px-2 py-1 rounded-full bg-gray-100">Acak opsi</span>
            @endif
            @if($paket->boleh_kembali)
            <span class="px-2 py-1 rounded-full bg-gray-100">Boleh kembali</span>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Sesi Ujian</h2>
            <span class="text-sm text-gray-500">{{ $paket->sesi->count() }} sesi</span>
        </div>
        <div class="space-y-3">
            @forelse($paket->sesi as $sesi)
            <div class="border border-gray-200 rounded-xl p-4">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                    <div>
                        <h3 class="font-medium text-gray-900">{{ $sesi->nama_sesi }}</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $sesi->waktu_mulai ? \Carbon\Carbon::parse($sesi->waktu_mulai)->format('d/m/Y H:i') : 'Belum dijadwalkan' }}
                            · {{ $sesi->sesiPeserta->count() }} peserta
                        </p>
                    </div>
                    <div>
                        @if($sesi->status === 'berlangsung')
                            <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-1 rounded-full">Live</span>
                        @elseif($sesi->status === 'persiapan')
                            <span class="text-xs font-semibold bg-blue-100 text-blue-600 px-2 py-1 rounded-full">Persiapan</span>
                        @elseif($sesi->status === 'selesai')
                            <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-1 rounded-full">Selesai</span>
                        @else
                            <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-1 rounded-full">{{ ucfirst($sesi->status) }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-500">Belum ada sesi ujian.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection