@extends('layouts.admin')

@section('title', 'Dashboard Dinas')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Dashboard</span>
@endsection

@section('page-content')
<div class="space-y-6">

    {{-- Welcome --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Selamat Datang, {{ auth()->user()->name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ now()->isoFormat('dddd, D MMMM Y') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('dinas.monitoring') }}"
               class="btn-primary inline-flex items-center gap-1.5">
                <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                Monitoring Live
            </a>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Sekolah --}}
        <div class="card p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total_sekolah'] }}</p>
            <p class="text-sm text-gray-500 mt-0.5">Total Sekolah</p>
            <p class="text-xs text-green-600 font-medium mt-2">{{ $stats['sekolah_aktif'] }} aktif</p>
        </div>

        {{-- Total Peserta --}}
        <div class="card p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_peserta']) }}</p>
            <p class="text-sm text-gray-500 mt-0.5">Total Peserta</p>
            <p class="text-xs text-gray-400 mt-2">dari {{ $stats['total_sekolah'] }} sekolah</p>
        </div>

        {{-- Paket Ujian --}}
        <div class="card p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total_paket'] }}</p>
            <p class="text-sm text-gray-500 mt-0.5">Paket Ujian</p>
            <p class="text-xs text-gray-400 mt-2">{{ $stats['paket_aktif'] }} aktif</p>
        </div>

        {{-- Sesi Berlangsung --}}
        <div class="card p-5 {{ $stats['sesi_berlangsung'] > 0 ? 'bg-green-50 border-green-200' : '' }}">
            <div class="flex items-start justify-between mb-3">
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <span class="w-3 h-3 bg-green-500 rounded-full {{ $stats['sesi_berlangsung'] > 0 ? 'animate-pulse' : '' }}"></span>
                </div>
            </div>
            <p class="text-2xl font-bold {{ $stats['sesi_berlangsung'] > 0 ? 'text-green-700' : 'text-gray-900' }}">{{ $stats['sesi_berlangsung'] }}</p>
            <p class="text-sm text-gray-500 mt-0.5">Sesi Berjalan</p>
            <p class="text-xs text-green-600 font-medium mt-2">{{ $stats['peserta_online'] }} peserta online</p>
        </div>
    </div>

    {{-- Grid: Sesi Aktif + Soal Essay --}}
    <div class="grid lg:grid-cols-5 gap-6">

        {{-- Sesi Ujian Aktif (3/5) --}}
        <div class="lg:col-span-3 card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-900">Sesi Ujian Aktif</h2>
                <a href="{{ route('dinas.monitoring') }}" class="text-blue-600 text-xs hover:underline">Lihat Semua</a>
            </div>
            @forelse($sesiAktif as $sesi)
            <div class="flex items-center gap-3 py-3 border-b border-gray-100 last:border-0">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse flex-shrink-0"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ $sesi->nama_sesi }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ $sesi->paket?->nama ?? '–' }} — {{ $sesi->paket?->sekolah?->nama ?? 'Dinas' }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-sm font-bold text-gray-900">{{ $sesi->jumlah_aktif }}</p>
                    <p class="text-xs text-gray-400">online</p>
                </div>
                <a href="{{ route('dinas.monitoring.sesi', $sesi->id) }}"
                   class="flex-shrink-0 text-blue-500 hover:text-blue-700">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            @empty
            <div class="text-center py-8">
                <p class="text-gray-400 text-sm">Tidak ada sesi ujian yang sedang berlangsung</p>
            </div>
            @endforelse
        </div>

        {{-- Essay Belum Dinilai (2/5) --}}
        <div class="lg:col-span-2 card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-900">Essay Belum Dinilai</h2>
                <a href="{{ route('dinas.grading') }}" class="text-blue-600 text-xs hover:underline">Nilai Sekarang</a>
            </div>
            @if($stats['essay_belum_dinilai'] > 0)
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center mb-3">
                <p class="text-3xl font-bold text-amber-600">{{ $stats['essay_belum_dinilai'] }}</p>
                <p class="text-sm text-amber-700 mt-1">jawaban menunggu penilaian</p>
            </div>
            @else
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center mb-3">
                <svg class="w-8 h-8 text-green-500 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-green-700">Semua essay sudah dinilai</p>
            </div>
            @endif

            {{-- Bank Soal --}}
            <div class="border-t border-gray-100 pt-4 space-y-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Bank Soal</p>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">Total Soal</span>
                    <span class="font-semibold text-gray-900">{{ number_format($stats['total_soal']) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">Kategori</span>
                    <span class="font-semibold text-gray-900">{{ $stats['total_kategori'] }}</span>
                </div>
            </div>
        </div>

    </div>

    {{-- Quick Access --}}
    <div>
        <h2 class="font-semibold text-gray-900 mb-3">Akses Cepat</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach([
                ['route' => 'dinas.paket.create', 'label' => 'Buat Paket Ujian', 'icon' => 'plus', 'color' => 'blue'],
                ['route' => 'dinas.soal.create', 'label' => 'Tambah Soal', 'icon' => 'pencil', 'color' => 'purple'],
                ['route' => 'dinas.sekolah.create', 'label' => 'Tambah Sekolah', 'icon' => 'office', 'color' => 'green'],
                ['route' => 'dinas.laporan', 'label' => 'Unduh Laporan', 'icon' => 'download', 'color' => 'amber'],
            ] as $qa)
            <a href="{{ route($qa['route']) }}"
               class="card hover:shadow-md transition-shadow flex flex-col items-center gap-2 p-4 text-center group">
                <div class="w-10 h-10 bg-{{ $qa['color'] }}-50 group-hover:bg-{{ $qa['color'] }}-100
                            rounded-xl flex items-center justify-center transition-colors">
                    <x-nav-icon :name="$qa['icon']" class="w-5 h-5 text-{{ $qa['color'] }}-600"/>
                </div>
                <span class="text-xs font-medium text-gray-700">{{ $qa['label'] }}</span>
            </a>
            @endforeach
        </div>
    </div>

</div>
@endsection
