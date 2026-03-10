@extends('layouts.admin')

@section('title', 'Dashboard Sekolah')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Dashboard</span>
@endsection

@section('page-content')
<div class="space-y-6">

    {{-- Welcome --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Dashboard Sekolah</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ auth()->user()->sekolah->nama }} · {{ now()->isoFormat('dddd, D MMMM Y') }}
            </p>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="card p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_peserta']) }}</p>
            <p class="text-sm text-gray-500 mt-0.5">Total Peserta</p>
        </div>
        <div class="card p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total_paket'] }}</p>
            <p class="text-sm text-gray-500 mt-0.5">Paket Ujian</p>
        </div>
        <div class="card p-5 {{ $stats['sesi_aktif'] > 0 ? 'bg-green-50 border-green-200' : '' }}">
            <div class="flex items-start justify-between mb-3">
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                    @if($stats['sesi_aktif'] > 0)
                        <span class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
                    @else
                        <span class="w-3 h-3 bg-gray-300 rounded-full"></span>
                    @endif
                </div>
            </div>
            <p class="text-2xl font-bold {{ $stats['sesi_aktif'] > 0 ? 'text-green-700' : 'text-gray-900' }}">{{ $stats['sesi_aktif'] }}</p>
            <p class="text-sm text-gray-500 mt-0.5">Sesi Aktif</p>
        </div>
    </div>

    {{-- Sesi Ujian Mendatang --}}
    <div class="grid lg:grid-cols-2 gap-6">
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-900">Sesi Ujian Mendatang</h2>
                <a href="{{ route('sekolah.paket') }}" class="text-blue-600 text-xs hover:underline">Kelola</a>
            </div>
            @forelse($sesiMendatang as $sesi)
            <div class="flex items-center gap-3 py-3 border-b border-gray-100 last:border-0">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ $sesi->nama_sesi }}</p>
                    <p class="text-xs text-gray-500">{{ $sesi->paket->nama }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    @if($sesi->status === 'berlangsung')
                        <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Live</span>
                    @elseif($sesi->status === 'menunggu')
                        <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Menunggu</span>
                    @else
                        <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Selesai</span>
                    @endif
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $sesi->waktu_mulai ? \Carbon\Carbon::parse($sesi->waktu_mulai)->format('d/m H:i') : '—' }}
                    </p>
                </div>
            </div>
            @empty
            <p class="text-gray-400 text-sm text-center py-6">Tidak ada sesi ujian mendatang.</p>
            @endforelse
        </div>

        {{-- Quick Access --}}
        <div class="card">
            <h2 class="font-semibold text-gray-900 mb-4">Akses Cepat</h2>
            <div class="grid grid-cols-2 gap-3">
                @foreach([
                    ['route' => 'sekolah.peserta.import', 'label' => 'Import Peserta', 'icon' => 'upload', 'color' => 'blue'],
                    ['route' => 'sekolah.kartu.index', 'label' => 'Cetak Kartu Login', 'icon' => 'tag', 'color' => 'amber'],
                    ['route' => 'sekolah.paket', 'label' => 'Paket Ujian', 'icon' => 'document', 'color' => 'purple'],
                ] as $qa)
                <a href="{{ route($qa['route']) }}"
                   class="card hover:shadow-md transition-shadow flex flex-col items-center gap-2 p-4 text-center group border border-gray-100">
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

</div>
@endsection
